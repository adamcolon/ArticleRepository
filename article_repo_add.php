<?php
define('DEBUG', false);

file_put_contents('test.log', time(), FILE_APPEND);
file_put_contents('test.log', print_r($_POST, true), FILE_APPEND);

	echo '<html style="height:25px;"><body>';

	$article = new Article();
	if($article->add()){
		echo '<div style="white-space:nowrap;">Successfully Added Article.</div>';
	}else{
		echo '<div style="white-space:nowrap;">Failed to Add Article.</div>';
	}

	echo '</body></html>';

	class Article {
		var $dataloaded = false;
		
		var $title = '';
		var $tags = array();
		var $summary = '';
		var $url = '';
		var $image_url = '';
		var $hash = '';
		
		var $db = null;
		
		function __construct(){
			// Construct from $_POST
			
			$this->db = new DataSource();
			if($this->validatePost()){
				$this->hash = md5($_POST['url']);
				$this->title = $this->db->escape_string($_POST['title']);
				$this->url = $_POST['url'];
				$this->summary = $this->db->escape_string($_POST['summary']);
				$this->image_url = $_POST['image_url'];
				
				$tags = str_replace(array('#', ' ', ', '), array('', ',', ','), $_POST['tags']);
				$tags = str_replace(',,', ',', $tags);
				$this->tags = explode(',', $this->db->escape_string($tags));
				
				$this->dataloaded = true;
			}
		}
		
		function validatePost(){
			$valid = false;
			if(!empty($_POST['title']) &&  !empty($_POST['url'])){
				$valid = true;
				if(DEBUG) echo __METHOD__." Post Validated.<br/>";
			}
			return $valid;
		}
		
		function exists(){
			$exists = false;
			$sql = "SELECT * FROM articles where hash='{$this->hash}';";
			if($this->db->Query($sql)){
				$exists = true;
			}
				
			return $exists;
		}
		
		function add(){
			$success = false;
			
			if($this->dataloaded){
				if(!$this->exists()){
					$sql = "INSERT INTO articles (title, hash, url, image_url, summary) VALUES('{$this->title}', '{$this->hash}', '{$this->url}', '{$this->image_url}', '{$this->summary}');";
					$result = $this->db->Execute($sql);
					if($result['rows_affected'] > 0) $success = true;
					
					$this->addTags($result['id'], $this->tags);
				}else{
					if(DEBUG) echo __METHOD__." Hash Exists<br/>";
				}
			}
			
			return $success;
		}
		
		function addTags($article_id, $tags){
			if($this->dataloaded){
				$tags = array_unique($tags);
				foreach ($tags as $tag){
					$this->addTag($article_id, $tag);
				}
			}
		}
		
		function addTag($article_id, $tag){
			if(DEBUG) echo __METHOD__." adding tag [article_id={$article_id}, tag:{$tag}]<br/>";
			$sql = "INSERT INTO article_tags (article_id, tag) VALUES({$article_id}, '{$tag}');";
			return $this->db->Execute($sql);
		}
	}
	
	class DataSource{
		var $host = 'xxxxxxxxx';
		var $db_name = 'article_repo';
		var $user = 'xxxxxxxxx';
		var $password = 'xxxxxxxxx';
		var $connection = null;
		
		function __construct(){
			$this->connection = mysql_connect($this->host, $this->user, $this->password) or die('Could not connect: ' . mysql_error());
			if(DEBUG) echo __METHOD__." connected to {$this->host}.<br/>";
			
			mysql_select_db($this->db_name) or die('Could not select database');
			if(DEBUG) echo __METHOD__." Database Selected: {$this->db_name}.<br/>";
		}
		
		function __destruct(){
			mysql_close($this->connection);
		}
		
		function Query($sql){
			if(DEBUG) echo __METHOD__." Running: {$sql}.<br/>";
			$dataset = array();
			
			$result = mysql_query($sql) or die('['.__METHOD__.'::'.__LINE__.'] Query failed: ' . mysql_error());
			while($rs = mysql_fetch_array($result, MYSQL_ASSOC)){
				$dataset[] = $rs;
			}
			
			if(DEBUG) echo __METHOD__." Results:".print_r($dataset, true)."<br/>";
			return $dataset;
		}

		function Execute($sql){
			if(DEBUG) echo __METHOD__." Running: {$sql}<br/>.";
			
			$result = mysql_query($sql) or die('['.__METHOD__.'::'.__LINE__.'] Query failed: ' . mysql_error());
			
			if(DEBUG) echo __METHOD__." Last Inserted Id: ".mysql_insert_id().", Rows Affected:".mysql_affected_rows()."<br/>";
			return array('id'=>mysql_insert_id(), 'rows_affected'=>mysql_affected_rows());
		}
		
		function escape_string($string){
			return mysql_real_escape_string($string, $this->connection);
		}
	}
?>