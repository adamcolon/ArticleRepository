<?php
require_once('data_source.php');

class Article {
	var $dataloaded = false;

	var $title = '';
	var $tags = array();
	var $summary = '';
	var $url = '';
	var $image_url = '';
	var $hash = '';

	var $db = null;

	function __construct($data = null){
		$db_settings = array(
			'host' => 'mysql.devwarrior.com'
			,'db_name' => 'article_repo'
			,'user' => 'devwarrior'
			,'password' => 'devwarrior01'
		);
		$this->db = new DataSource($db_settings);

		if($data){
			if($this->validatePost($data)){
				$this->hash = md5($data['url']);
				$this->title = $this->db->escape_string($data['title']);
				$this->url = $data['url'];
				$this->summary = $this->db->escape_string($data['summary']);
				$this->image_url = $data['image_url'];
					
				$tags = str_replace(array('#', ' ', ', '), array('', ',', ','), $data['tags']);
				$tags = str_replace(',,', ',', $tags);
				$this->tags = explode(',', $this->db->escape_string($tags));
					
				$this->dataloaded = true;
			}else{
				echo "Failed Validation<br>";
			}
		}
	}

	/**
	 * return true if both title and url are part of the data
	 * @param array $data
	 */
	function validatePost($data){
		$valid = false;
		if(!empty($data['title']) &&  !empty($data['url']) &&  !empty($data['tags'])){
			$valid = true;
			if(DEBUG) echo __METHOD__." Post Validated.<br/>";
		}
		return $valid;
	}

	/**
	 * determines if article exists based on hash
	 * @return boolean
	 */
	function exists(){
		$exists = false;
		$sql = "SELECT * FROM articles where hash='{$this->hash}';";
		if($this->db->Query($sql)){
			$exists = true;
		}

		return $exists;
	}

	/**
	 * Adds article to database
	 * @return boolean
	 */
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

	/**
	 * loops through every unique tag in the list and calls addTag
	 * @param int $article_id
	 * @param array $tags
	 */
	function addTags($article_id, $tags){
		if($this->dataloaded){
			$tags = array_unique($tags);
			foreach ($tags as $tag){
				$this->addTag($article_id, $tag);
			}
		}
	}

	/**
	 * aligns a tag to an article
	 * @param int $article_id
	 * @param string $tag
	 */
	function addTag($article_id, $tag){
		if(DEBUG) echo __METHOD__." adding tag [article_id={$article_id}, tag:{$tag}]<br/>";
		$sql = "INSERT INTO article_tags (article_id, tag) VALUES({$article_id}, '{$tag}');";
		return $this->db->Execute($sql);
	}
	
	/**
	 * returns an array of articles that are ready to be added to the domain
	 * @param string $domain
	 * @param array $tags
	 */
	function getPostData($domain, $tags){
		echo __METHOD__." domain:{$domain}, tags:".implode("','", $tags)."<br>";
		if($domain && $tags){
			$domain_hash = md5($domain);
	
			if($tag_list = implode("','", $tags)){
				$tag_list = "'{$tag_list}'";
			}
			
			$sql = "SELECT * FROM articles WHERE id IN (SELECT article_id FROM article_tags WHERE article_id=articles.id AND tag in ({$tag_list})) AND id NOT IN (SELECT article_id FROM domain_articles WHERE article_id=articles.id AND domain_hash='{$domain_hash}');";
			echo $sql;
			return $this->db->Query($sql);
		}else{
			return false;
		}
	}
	
	/**
	* returns an array intersection of tags
	* @param string $article_id
	* @param array $tags
	*/
	
	function getTags($article_id, $tags){
		$tag_results = array();

		if($tag_list = implode("','", $tags)){
			$tag_list = "'{$tag_list}'";
		}
				
		$sql = "SELECT tag FROM article_tags WHERE article_id={$article_id} AND tag in ({$tag_list});";

		if($results = $this->db->Query($sql)){
			foreach($results as $rs){
				$tag_results[] = $rs['tag'];
			}
		}
		
		return $tag_results;
	}
	
	
	/**
	 * Marks an article posted by domain
	 * @param string $domain
	 * @param id $article_id
	 * @return int
	 */
	function markPosted($domain, $article_id){
		echo "* Mark as Posted [{$domain}, {$article_id}]<br>";
		
		$domain_hash = md5($domain);
		$sql = "INSERT INTO domain_articles (domain_hash, article_id) VALUES('{$domain_hash}',{$article_id});";
		
		list($id, $rows_affected) = $this->db->Execute($sql);
		return $rows_affected;
	}
}
?>