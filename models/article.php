<?php
require_once('data_source.php');
require_once('imgur.php');

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
			$result = $this->validatePost($data);
			if($result['valid']){
				$this->hash = md5($data['url']);
				$this->title = $this->db->escape_string($data['title']);
				$this->url = $data['url'];
				$this->summary = $this->processSummary($data['summary']);
				$this->image_url = $this->reHostImage($data['image_url']);
					
				$tags = str_replace(array('#', ' ', ', '), array('', ',', ','), $data['tags']);
				$tags = str_replace(',,', ',', $tags);
				$this->tags = explode(',', $this->db->escape_string($tags));
					
				$this->dataloaded = true;
			}else{
				echo "Failed Validation<br>".implode("\n", $result['messages']);
			}
		}
	}

	/**
	 * return true if both title and url are part of the data
	 * @param array $data
	 */
	function validatePost($data){
		$valid = false;
		$messages = array();
		
		if(!empty($data['title']) &&  !empty($data['url']) &&  !empty($data['tags'])){
			$valid = true;
			if(DEBUG) echo __METHOD__." Post Validated.<br/>";
		}else{
			if(empty($data['title'])) $messages[] = "<div>Missing Title.</div>";
			if(empty($data['url'])) $messages[] = "<div>Missing URL.</div>";
			if(empty($data['tags'])) $messages[] = "<div>Missing Tags.</div>";
		}
		
		return array('valid'=>$valid, 'message'=>$messages);
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
		$messages = array();
					
		if($this->dataloaded){
			if(!$this->exists()){
				$sql = "INSERT INTO articles (title, hash, url, image_url, summary) VALUES('{$this->title}', '{$this->hash}', '{$this->url}', '{$this->image_url}', '{$this->summary}');";
				$result = $this->db->Execute($sql);
				if($result['rows_affected'] > 0) $success = true;
					
				$this->addTags($result['id'], $this->tags);
			}else{
				$messages[] = "<div>Article Already Exists</div>";
			}
		}
			
		return array('success'=>$success, 'messages'=>$messages);
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
		$sql = "INSERT INTO domain_articles (domain_hash, domain_name, article_id) VALUES('{$domain_hash}','{$domain}', {$article_id});";
		
		list($id, $rows_affected) = $this->db->Execute($sql);
		return $rows_affected;
	}
	
	/**
	 * Massage Summary by removing non-ascii characters and add meta tags where appropriate
	 * @param string $summary
	 * @return string
	 */
	function processSummary($summary){
		$summary = preg_replace('/[^(\x20-\x7F)]*/','', $summary); // Strip out non-ascii characters
		$summary = "<description>{$summary}</description>";	// Facebook uses this tag for sharing
		$summary = $this->db->escape_string($summary);	// Prevent SQL Injection
		return $summary;
	}
	
	function reHostImage($image_url){
		$imgur = new Imgur();
		$new_image_url = $imgur->reHostImage($image_url);
		
		if(empty($new_image_url)){
			$new_image_url = $image_url;
		}
		
		return $new_image_url;
	}
}
?>