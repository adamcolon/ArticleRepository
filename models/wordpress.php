<?php
require_once('data_source.php');

class Wordpress{
	var $db_table_prefix = 'wp_';
	var $db = null;
	var $options = array();

	function __construct($db_host, $db_username, $db_password, $db_name, $db_table_prefix){
		$db_settings = array(
			'host' => $db_host
			,'db_name' => $db_name
			,'user' => $db_username
			,'password' => $db_password
		);
		$this->db = new DataSource($db_settings);

		$this->db_table_prefix = $db_table_prefix;
	}

	/**
	 *
	 * Send an email to the author
	 * @param int $author_id
	 * @param string $subject
	 * @param string $message
	 */
	function mailAuthor($author_id, $subject, $message){
		$sql = "SELECT user_email AS email FROM {$this->db_table_prefix}users as User WHERE ID={$author_id};";
		if($results = $this->db->Query($sql)){
			$rs = array_shift($results);
			$email_to = $rs['email'];
			mail($email_to, $subject, $message);
			echo "* Email Sent to [{$email_to}]\n";
		}
	}

	/**
	 * 
	 * Return Specific Option
	 * @param string $name
	 * @return string
	 */
	function getOptions($name){
		if(empty($this->options)){
			$sql = "SELECT * FROM {$this->db_table_prefix}options as Options";
			if($results = $this->db->Query($sql)){
				foreach($results as $rs){
					$this->options[$rs['option_name']] = $rs['option_value'];
				}
			}
		}
		return (empty($this->options[$name]))?null:$this->options[$name];
	}

	/**
	* get an array of all Categories
	* @return array
	*/
	function getCategories(){
		$categories = array();
	
		$sql = "SELECT term.term_id as id, term.slug as name FROM {$this->db_table_prefix}terms AS term INNER JOIN {$this->db_table_prefix}term_taxonomy AS tax ON term.term_id=tax.term_id WHERE tax.taxonomy='category';";
		if($result = $this->db->Query($sql)){
			foreach($result as $rs){
				$categories[$rs['id']] = $rs['name'];
			}
		}
		return $categories;
	}
	
	/**
	 * get an array of all post tags
	 * @return array
	 */
	function getPostTags(){
		$tags = array();
			
		$sql = "SELECT term.term_id as id, term.slug as name FROM {$this->db_table_prefix}terms AS term INNER JOIN {$this->db_table_prefix}term_taxonomy AS tax ON term.term_id=tax.term_id WHERE tax.taxonomy='post_tag';";
		if($result = $this->db->Query($sql)){
			foreach($result as $rs){
				$tags[$rs['id']] = $rs['name'];
			}
		}
		return $tags;
	}

	/**
	 * 
	 * return if the post exists based on gp_id or url
	 * @param id $gp_id
	 * @param string $url
	 * @return boolean
	 */
	function postExists($existence_criteria){
		$exists = false;
		
		foreach($existence_criteria as $key=>$value){
			echo "* Checking Existence [key:{$key}, value:{$value}]<br>";
			$where = "(meta_key='{$key}' && meta_value='{$value}')";
				
			$sql = "SELECT * FROM {$this->db_table_prefix}postmeta WHERE {$where} LIMIT 1;";
			if($this->db->Query($sql)){
				$exists = true;
				break;
			}
		}
			
		return $exists;
	}

	/**
	 * 
	 * Insert Post
	 * @param int $category_id
	 * @param int $author_id
	 * @param int $gp_id
	 * @param string $url
	 * @param string $date
	 * @param string $title
	 * @param string $content
	 * @param array $tags
	 * @param string $status
	 */
	function insertPost($author_id, $existence_criteria, $date, $title, $content, $tags, $status){
		if($existence_criteria){
			$title = $this->db->escape_string($title);
			$slug = str_replace(' ', '-', strtolower($title));
			$content = $this->db->escape_string($content);

			if(!$this->postExists($existence_criteria)){
				$date = date('Y-m-d H:i:s', strtotime($date));
				// Insert Post
				echo "* Inserting Post<br>";
				$sql = "INSERT INTO {$this->db_table_prefix}posts (`post_author`, `post_date`, `post_title`, `post_name`, `post_content`, `post_status`, `comment_status`, `ping_status`, `post_type`) VALUES({$author_id}, '{$date}', '{$title}', '{$slug}', '{$content}', '{$status}', 'open', 'open', 'post');";
				$results = $this->db->Execute($sql);
				
				$post_id = $results['id'];
				if(!empty($post_id)){
					// Add Tags
					echo "* Inserting Tags<br>";
					foreach($tags as $tag_id=>$tag_slug){
						echo "* - Inserting [{$tag_slug}]<br>";
						$sql = "INSERT INTO {$this->db_table_prefix}term_relationships (`object_id`, `term_taxonomy_id`, `term_order`) VALUES({$post_id},{$tag_id},0);";
						$results = $this->db->Execute($sql);
					}

					// Add Meta Data
					echo "* Inserting Meta Existence Criteria<br>";
					$this->insertExists($existence_criteria, $post_id);
				}else{
					echo "* Failed To Insert Post<br>";
				}
			}else{
				echo "* Existence Criteria Found, Skipping<br>";
			}
		}else{
			echo "* Existence Criteria Not Included<br>";
		}
	}

	/**
	*
	* insert meta data for existence criteria
	* @param array $existence_criteria
	* @param int $post_id
	*/
	function insertExists($existence_criteria, $post_id){
		foreach($existence_criteria as $key=>$value){
			echo "* Inserting Existence Criteria [key:{$key}, value:{$value}]<br>";
			$sql = "INSERT INTO {$this->db_table_prefix}postmeta (`post_id`, `meta_key`, `meta_value`) VALUES({$post_id},'{$key}','{$value}');";
			$results = $this->db->Execute($sql);
		}
	}
}

?>