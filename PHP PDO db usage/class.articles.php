<?php
/*
=================================================
Class:Articles
Description:
Author:DozenCreation

Methods:

addColumn(array $columnOptions)
addGroup(string $id, string $label)
getArticle(string $id)
getArticleNum(array [$where])
getPageNav()
form(string $id, array [$options])
view(string $id, array [$options])
list(array [$options])
data(array [$option])
insert(array $data)
update(string $where, array $data)
delete(string $where)
sort(string $list, [$sortName, [$sortValue]])




//Database initialization
try
{
	$DB = new PDO('mysql:host='.CONFIG_DB_HOST.';dbname='.CONFIG_DB_NAME.';charset=utf8', CONFIG_DB_USER, CONFIG_DB_PASS);
	//$DB = new LoggedPDO('mysql:host='.CONFIG_DB_HOST.';dbname='.CONFIG_DB_NAME.';charset=utf8', CONFIG_DB_USER, CONFIG_DB_PASS);
	$DB->setAttribute(PDO::ATTR_PERSISTENT, false);
	$DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$DB->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	$DB->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, 'SET NAMES utf8mb4');
	$DB->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('EPDOStatement', array($DB)));
}
catch(PDOException $e)
{
   echo $e->getMessage();
}




=================================================
*/

class Articles
{	
	
	
	public $table;
	public $editable_language = array();
	
	protected $db;	
	protected $columns = array();
	protected $nav = false;	
	
	function __construct($db, $table) {
		
		$this->db = $db;
		$this->table = $table;
		
	}
	
	/*
	==================================
	Public functions
	==================================
	*/
	
	
	public function addColumn($column){
		if(isset($column['group'])){
			$this->columns[$column['group']]['columns'][$column['name']] = $column;
		}else{
			$this->columns[$column['name']] = $column;
		}
	}
	
	public function addGroup($id, $label, $description = ''){
		$this->columns[$id]['label'] = $label;
		$this->columns[$id]['description'] = $description;
	}
	

	

	
	public function view($id = NULL, $options = array()){
		
		$defaults = array(
						'ignore' => array(),
						'ignore_group' => array()
					);
					
		$settings = array_merge($defaults, $options);
		
		
		if($id)
			$data = $this->getArticle($id);
		else
			return false;
		
		$exist_column_groups = array();
		
		$html = '';
		
		foreach($this->columns as $key => $col){
			
			if(in_array($key, $settings['ignore']) || in_array($col['input'], array('hidden', 'password'))){//Ignore
				continue;
			}else if(isset($col['columns'])){//Is group
				
				if(in_array($key, $settings['ignore_group'])){
					continue;
				}
				
				$html .= '<fieldset id="group-'.$key.'">';
				
				if(isset($col['label'])){
					$html .= '<legend>'.$col['label'].'</legend>';
				}
				
				foreach($col['columns'] as $sub_key => $sub_col){
					
					if(isset($sub_col['column_group']) && in_array($sub_col['column_group'], $exist_column_groups)){
						continue; //Ignore column group member
					}else{
						$exist_column_groups[] = $sub_col['column_group'];
					}
				
					if(in_array($sub_key, $settings['ignore']) || !isset($sub_col['input'])){//Ignore
						continue;
					}
					
					if(is_array($sub_col['lang'])){//Multilang
						foreach($sub_col['lang'] as $lang_key => $lang_label){
							$html .= $this->_genViewColumnHTML($sub_col, $data, $lang_key);
						}
					}else{//Non-multilang
						$html .= $this->_genViewColumnHTML($sub_col, $data);
					}
				}
				
				$html .= '</fieldset>';
				
			}else{//Is column				
			
				if(isset($col['column_group']) && in_array($col['column_group'], $exist_column_groups)){
					continue; //Ignore column group member
				}else{
					$exist_column_groups[] = $col['column_group'];
				}
			
				if(!isset($col['input'])){
					continue;
				}
			
				if(is_array($col['lang'])){//Multilang
					foreach($col['lang'] as $lang_key => $lang_label){
							$html .= $this->_genViewColumnHTML($col, $data, $lang_key);
					}
				}else{//Non-multilang
					$html .= $this->_genViewColumnHTML($col, $data);
				}
			}
		}

	
		return $html;
	}
	
	
	public function listArticles($options = array()){
		
		$defaults = array(
						'where' => '',
						'order' => 'ord ASC, id DESC',
						'field' => array('id', 'title'),
						'header' => array('id'=>'ID', 'title'=>'TITLE'),
						'button' => array(),
						'detail_page' => 'view.php',
						'albums' => NULL,
						'thumb_folder' => 'thumb',
						'use_video_thumb' => false,
						'video_thumb_field' => 'video_thumb',
						'num_per_page' => 24,
						'page' => 1,
						'max_link' => 5,
						'str_length' => 180,
						'list_file_num' => true,
						'list_file_num_label' => 'Files'
					);
					
		$settings = array_merge($defaults, $options);
		
		if($settings['use_video_thumb']){
			$settings['field'][] = $settings['video_thumb_field'];
		}
				
		$data = $this->data(array(
								'where' => $settings['where'],
								'order' => $settings['order'],
								'field' => $settings['field'],
								'albums' => $settings['albums'],
								'num_per_page' => $settings['num_per_page'],
								'page' => $settings['page'],
								'max_link' => $settings['max_link']
							));
						
		$html = '';
		
		if(count($settings['header']) > 0){
		
			$self = $this->_urlQuery();
			
			$html .= '<li class="header-row row">';
			foreach($settings['header'] as $key => $label){
				$html .= '<div class="col"><a href="'.$self.'sortby='.$key.'" class="'.($_SESSION['sort']['field'] == $key?strtolower($_SESSION['sort']['order']):'').'">'.$label.'</a></div>';
			}
			
			if($settings['albums']){
				if($settings['albums']->type == 'file' && $settings['list_file_num']){					
					$html .= '<div class="col">'.$settings['list_file_num_label'].'</div>';					
				}
			}
			
			if(count($settings['button']) > 0){
				$html .= '<div class="col">&nbsp;</div>';
			}
			$html .= '</li>';
		}
		
		
		
		//Go through each row
		$column_array = $this->_getColumnArray();
		
		foreach($data as $row){
			
			$display = (isset($row['display']) && !$row['display']) ? 'no-display' : '';
			$has_image = false;
			$thumb_src = '';
			
			if($settings['use_video_thumb'] && $row[$settings['video_thumb_field']] != ''){
				$has_image = true;
				$thumb_src = $row[$settings['video_thumb_field']];
			}else if($settings['albums'] && $row['path'] && file_exists($settings['albums']->upload_path.$settings['thumb_folder'].'/'.$row['path'])){
				$has_image = true;
				$thumb_src = $settings['albums']->upload_path.$settings['thumb_folder'].'/'.$row['path'];
			} 
			
			$html .= '<li id="list-'.$row['id'].'" class="row '.$display.' '.($has_image?'has-image':'').'">';
			
			if(($settings['albums'] && $settings['albums']->type == 'image') || $settings['use_video_thumb']){
				
					$html .= '<div class="col thumb '.(!$has_image?'no-image':'').'">';
					if($settings['detail_page']){ $html .= '<a href="'.$settings['detail_page'].'?id='.$row['id'].'">'; }
					if($thumb_src){ $html .= '<img src="'.$thumb_src.'" />'; }
					if($settings['detail_page']){ $html .= '</a>'; }
					$html .= '</div>';
			}
			
			//Go through each field
			$exist_column_groups = array();
			
			foreach($settings['field'] as $field){
				
				if($field == $settings['video_thumb_field']){//Don't output video thumb field
					continue;
				}
				
				if(isset($column_array[$field]['columns'])){//Is group
					foreach($column_array[$field]['columns'] as $sub_key => $sub_col){
						if(isset($sub_col['column_group']) && in_array($sub_col['column_group'], $exist_column_groups)){
							continue; //Ignore column group member
						}else{
							$exist_column_groups[] = $sub_col['column_group'];
						}
					}
				}else{
					if(isset($column_array[$field]['column_group']) && in_array($column_array[$field]['column_group'], $exist_column_groups)){
						continue; //Ignore column group member
					}else{
						$exist_column_groups[] = $column_array[$field]['column_group'];
					}
				}
				
				$html .= '<div data-col="'.$field.'" class="col">';
				
				if(is_array($column_array[$field]['lang'])){//Mulitlang				
					
					foreach($column_array[$field]['lang'] as $lang_key => $lang_label){
						$html .= $this->_genListColumnHTML($column_array[$field], $row, $lang_key, $settings);
					}
					
				}else if(isset($column_array[$field])){//Non-mulitlang
					
					$html .= $this->_genListColumnHTML($column_array[$field], $row, NULL, $settings);
					
				}else{//Field not in column_array : id, ord .etc
					$html .= '<p>';
					if($key == 'title' && $settings['detail_page']){ $html .= '<a href="'.$settings['detail_page'].'?id='.$row['id'].'">';}
					$html .= $row[$field].'&nbsp;';
					if($key == 'title' && $settings['detail_page']){ $html .= '</a>';}
					$html .= '</p>';
				}
				
				$html .= '</div>';
				
			}
			
			if($settings['albums']){
				if($settings['albums']->type == 'file' && $settings['list_file_num']){					
					$html .= '<div class="col file-num">'.$settings['albums']->getItemNum($row['id']).'</div>';					
				}
			}
			
			
			if(count($settings['button']) > 0){
				$html .= '<div class="col buttons">';
				foreach($settings['button'] as $btn){
					$sep = strpos($btn['path'], '?')===false?'?':'&';
					$param = isset($btn['param']) ? $btn['param'] : 'id';			
					$html .= '<a href="'.$btn['path'].$sep.$param.'='.$row['id'].'" class="button '.$btn['class'].'">'.$btn['label'].'</a>';
				}
				$html .= '</div>';
			}
			
			$html .= '</li>';
		}
		
		return $html;
	}
	
	public function data($options = array()){
		
		$defaults = array(
						'where' => '',
						'field' => '',
						'order' => '',
						'group' => '',
						'having' => '',
						'join' => '',
						'num_per_page' => -1,
						'page' => 1,
						'max_link' => 5,
						'previous_text' => '&lt;',
						'next_text' => '&gt;',
						'albums' => NULL
					);
		
		$settings = array_merge($defaults, $options);
		
		//Get all number
		$num = $this->getArticleNum($settings['where'], array(
			'join' => $settings['join'],
			'group' => $settings['group']
		));
		
		//Show all records
		if($settings['num_per_page'] == -1)
			 $settings['num_per_page'] = $num;
			 	
		//Pagination
		if(isset($_GET['page']))
			$settings['page'] = $_GET['page'];
		
		if($num > 0){	 
			$this->nav = new Pagination($num, $settings['num_per_page'], $settings['page'], $settings['max_link']);
			$this->nav->previous_text = $settings['previous_text'];
			$this->nav->next_text = $settings['next_text'];
		}
		
		//Fields
		if(is_array($settings['field'])){
			
			$result_field = array();
			
			if(!in_array('id', $settings['field'])){
				array_push($result_field, $this->table.'.id');
			}
			if(isset($this->columns['display']) && !in_array('display', $settings['field'])){
				array_push($result_field, $this->table.'.display');
			}
			
			$column_array = $this->_getColumnArray();
			
			foreach($settings['field'] as $f){
				if(is_array($column_array[$f]['lang'])){
					foreach($column_array[$f]['lang'] as $lang_key => $lang_label){
						$result_field[] = $this->table.'.'.$f.'_'.$lang_key;
					}
				}else{
					if(strpos($f, 'MATCH') === false){
						$result_field[] = $this->table.'.'.$f;
					}else{
						$result_field[] = $f;
					}
				}
			}
			$field = implode(',', $result_field);
		}else if($settings['field'] == ''){
			$field = '*';
		}else{
			$field = $settings['field'];
		}
		
		//Order
		if($settings['order'] instanceof Category){
			$order = 'ORDER BY '.$settings['order']->table.'.ord ASC ,'.$this->table.'.ord ASC, '.$settings['order']->table.'.id ASC';
			$join = 'LEFT JOIN '.$settings['order']->table.' ON '.$this->table.'.category = '.$settings['order']->table.'.id';
		}else{
			$order = $settings['order'] ? 'ORDER BY '.$settings['order'] : '';
			$join = $settings['join'] ? $settings['join'] : '';
		}
		
		
		//Where
		$where = isset($settings['where']['query']) ? 'WHERE '.$settings['where']['query'] : (is_string($settings['where']) && $settings['where'] != '' ? 'WHERE '.$settings['where'] : '');
		$group = $settings['group'] ? ('GROUP BY '.$settings['group']) : '';
		$having = $settings['having'] ? ('HAVING '.$settings['having']) : '';
		
		
		$query = 'SELECT '.$field.' FROM '.$this->table.' '.$join.' '.$where.' '.$group.'  '.$having.' '.$order.' '.($num > 0 ? $this->nav->limit() : '');
		
		$stmt = $this->db->prepare($query); 
		
		if(isset($settings['where']['params'])){			
			foreach($settings['where']['params'] as $key => $val){
				$stmt->bindValue($key+1, $val);
			}			
		}
		
		$stmt->execute();
		
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
			
			//Output escape
			foreach($this->columns as $key => $col){
				
				if($this->columns[$key]['richtext'] == true || (isset($this->columns[$key]['format']) && $this->columns[$key]['format'] == 'url') || (isset($this->columns[$key]['escape']) && $this->columns[$key]['escape'] === false) ){
					continue;
				}
				
				if(is_array($col['lang'])){//Multilang
					foreach($col['lang'] as $lang_key => $lang_label){
						$name = $col['name'].($lang_key?'_'.$lang_key:'');
						$row[$name] = htmlspecialchars($row[$name], ENT_QUOTES);
					}
				}else{//Non-multilang
					$row[$col['name']] = htmlspecialchars($row[$col['name']], ENT_QUOTES);
				}
			}
				
			
			if($settings['albums']){
				$row['path'] = $settings['albums']->getCover($row['id']);
			}
			
			if(isset($row['id'])){
				yield $row['id'] => $row;
			}else{
				yield $row;
			}
		}
	}
	 
	public function dataArray($options = array()){
		 
		 $result = array();
		 
		 foreach($this->data($options) as $key => $val){
			 $result[$key] = $val;
		 }
		 
		 return $result;
	}
	 
	 
	public function insert($data = array())
	{
	  	if(count($data) == 0)
			return false;
		
		$fields = array();
		$placeholder = array();
		$values = array();
		
		foreach($this->_getFieldArray() as $key => $col){
			
			if($col['input'] == 'checkbox' || ($col['input'] == 'select' && $col['multiple'] == true)){
				$fields[] 		= $key;
				$placeholder[] 	= '?';
				$values[] 		= isset($data[$key])?(is_array($data[$key]) ? implode(',',$data[$key]) : $data[$key]):'';
				$types[] 		= $col['type'];
			}else{				
				if(isset($data[$key])){
					$fields[] 		= $key;
					$placeholder[] 	= '?';
					$values[] 		= $data[$key];
					$types[] 		= $col['type'];
				}
			}
		}
		
		$query = 'INSERT INTO '.$this->table.'('.implode(',',$fields).') VALUES ('.implode(',',$placeholder).')';		
		$stmt = $this->db->prepare($query);
		foreach($values as $key => $val){
			$type = $this->_getSQLType($val != '' ? $types[$key] : 'null');
			$stmt->bindValue($key+1, $val, $type);
		}
		$stmt->execute();
		
		return $this->db->lastInsertId();
	}
	
	public function update($where = NULL, $data = array(), $ignore_empty_checkbox = false)
	{
	  	if(!$where || count($data) == 0)
			return false;
		
		$fields = array();
		$values = array();
		
		foreach($this->_getFieldArray() as $key => $col){
			
			if((!$ignore_empty_checkbox && $col['input'] == 'checkbox') || (!$ignore_empty_checkbox && $col['input'] == 'select' && $col['multiple'] == true)){
				$fields[] = $key.' = ?';
				$values[] = isset($data[$key])?implode(',',$data[$key]):'';
				$types[]  = $col['type'];
			}else{				
				if(isset($data[$key])){
					$fields[] = $key.' = ?';
					$values[] = $data[$key];
					$types[]  = $col['type'];
				}
			}
		}
		
		if(is_array($where)){
			
			$stmt = $this->db->prepare('UPDATE '.$this->table.' SET '.implode(',',$fields).' WHERE '.$where['query']);
			
			foreach($values as $key => $val){
				$type = $this->_getSQLType($val != '' ? $types[$key] : 'null');
				$stmt->bindValue($key+1, $val, $type);
			}
			
			if(is_array($where['params'])){
				foreach($where['params'] as $key => $val){
					$stmt->bindValue(count($values) + ($key+1), $val);
							}
			}
			
		}else{
			
			$stmt = $this->db->prepare('UPDATE '.$this->table.' SET '.implode(',',$fields).' WHERE id = ?');
			
			foreach($values as $key => $val){
				$type = $this->_getSQLType($val != '' ? $types[$key] : 'null');
				$stmt->bindValue($key+1, $val, $type);
			}
			$stmt->bindValue(count($values)+1, $where);
		}
		
		$stmt->execute();
		
	}
	
	public function delete($where = NULL)
	{
		if(!$where)
			return false;
			
	  	if(is_array($where)){
			
			$stmt = $this->db->prepare('DELETE FROM '.$this->table.' WHERE '.$where['query']);
			
			foreach($where['params'] as $key => $val){
				$stmt->bindValue($key+1, $val);
			}
			
		}else{
			
			$stmt = $this->db->prepare('DELETE FROM '.$this->table.' WHERE id = ?');
			$stmt->bindValue(1, $where);
		}
		
		$stmt->execute();
	}
	
	public function sort($list, $order_field = 'ord', $sort_field = 'id'){
		foreach($list as $key => $val){
			
			$stmt = $this->db->prepare('UPDATE '.$this->table.' SET '.$order_field.' = ? WHERE '.$sort_field.' = ?');
			$stmt->bindValue(1, $key+1);
			$stmt->bindValue(2, $val);
			
			$stmt->execute();
   		}
	}
	
	public function outputOptions($options = array()){
		
		$defaults = array(
						'where' => '',
						'order' => 'ord ASC',
						'field' => 'title',
						'lang' => '',
						'output' => array()
					);
					
		$settings = array_merge($defaults, $options);
		
		$data = $this->data(array('where' => $settings['where'], 'order' => $settings['order']));
		
		foreach($data as $row){
			
			$label = '';
			
			if(is_string($settings['field'])){
				
				if(is_array($this->columns[$settings['field']]['lang'])){
					foreach($this->columns[$settings['field']]['lang'] as $lang_key => $lang_label){
						$label .= '<span class="col-lang '.$lang_key.'">'.$row[$settings['field'].'_'.$lang_key].' </span>';
					}
				}else{
					$label = '<span>'.$row[$settings['field']].'</span>';
				}
				
			}else if(is_array($settings['field'])){
				
				$count = 0;
				
				foreach($settings['field'] as $f){
					
					if(is_array($this->columns[$f]['lang'])){
						foreach($this->columns[$f]['lang'] as $lang_key => $lang_label){
							if($count == 0){
								$label .= '<span class="col-lang '.$lang_key.'">'.$row[$f.'_'.$lang_key].'</span>';
							}else{
								$label .= '<span class="col-lang '.$lang_key.'"> ('.$row[$f.'_'.$lang_key].')</span>';
							}
						}
					}else{
						if($count == 0){
							$label .= '<span>'.$row[$f].'</span>';
						}else{
							$label .= '<span> ('.$row[$f].')</span>';
						}
					}
					
					$count++;
				}
			}
			
			$settings['output'][$row['id']] = $label;
		}
		return $settings['output'];
	}
	/*
	==================================
	Private functions
	==================================
	*/

}

/*

Usage:

require 'class.pagination.php';

try 
{
	$db = new PDO('mysql:host=localhost;dbname=dctest;charset=utf8', 'root', '0000');
}
catch(PDOException $e)
{
   echo $e->getMessage();
}

$lang = array('zh'=>'zh','en'=>'en');
$Post = new Articles($db, 'dc_post');
$Post -> addGroup('g1', 'Group1');
$Post -> addColumn(array('group'=>'g1', 'name'=>'contents', 'type'=>'text', 'label'=>'Content_en', 'input'=>'textarea', 'lang'=>$lang));
$Post -> addColumn(array('group'=>'g2', 'name'=>'title', 'type'=>'text', 'label'=>'Title_en', 'input'=>'text', 'lang'=>$lang));
$Post -> addColumn(array('name'=>'category', 'type'=>'int', 'label'=>'Category', 'input'=>'select', 'options'=>array('1'=>'Female','2'=>'Male')));
$Post->update(4, 5 ,array('category'=>1));


//echo $Post->form();
//echo $Post->view(1);
//echo $Post->listArticles();
//$Post->insert(array('title_en'=>'titleEN', 'title_zh'=>'titleZH', 'contents_en'=>'contentsEN', 'contents_zh'=>'contentsZH', 'category'=>2));
//$Post->update(4, array('category'=>1));
//$Post->update(array('query'=>'category = ?', 'params'=>array(1)), array('category'=>3));
//$Post->delete(5);
//$Post->delete(array('query'=>'category = ?', 'params'=>array(3)));
//foreach($Post->data() as $key => $val){ print_r($val); }

*/		
?>