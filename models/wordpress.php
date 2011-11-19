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
			,'user' => $b_username
			,'password' => $db_name
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
	 * get an array of all post tags
	 * @return array
	 */
	function getPostTags(){
		$tags = array();
			
		$sql = "SELECT term.term_id as id, term.slug as name FROM {$this->db_table_prefix}terms AS term INNER JOIN wp_term_taxonomy AS tax ON term.term_id=tax.term_id WHERE tax.taxonomy='post_tag';";
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
	function postExists($gp_id, $url=null){
		$exists = false;
			
		echo "* Checking Existence [gp_id:{$gp_id}, url:{$url}]\n";
		$where = "(meta_key='gp_id' && meta_value='{$gp_id}')";
		if($url) $where .= " OR (meta_key='gp_url' && meta_value='{$url}')";
			
		$sql = "SELECT * FROM {$this->db_table_prefix}postmeta WHERE {$where} LIMIT 1;";
		if($this->db->Query($sql)){
			$exists = true;
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
	function insertPost($category_id, $author_id, $gp_id, $url, $date, $title, $content, $tags, $status){
		if($gp_id){
			$title = $this->db->escape_string($title);
			$slug = str_replace(' ', '-', strtolower($title));
			$content = $this->db->escape_string($content);

			if(!$this->postExists($gp_id, $url)){
				$date = date('Y-m-d H:i:s', strtotime($date));
				// Insert Post
				echo "* Inserting Post\n";
				$sql = "INSERT INTO {$this->db_table_prefix}posts (`post_author`, `post_date`, `post_title`, `post_name`, `post_content`, `post_status`, `comment_status`, `ping_status`, `post_type`) VALUES({$author_id}, '{$date}', '{$title}', '{$slug}', '{$content}', '{$status}', 'open', 'open', 'post');";
				list($post_id, $rows_affected) = $this->db->Execute($sql);
					
				if(!empty($post_id)){
					// Add to Uncategorized
					echo "* Inserting Category\n";
					$sql = "INSERT INTO {$this->db_table_prefix}term_relationships (`object_id`, `term_taxonomy_id`, `term_order`) VALUES({$post_id},{$category_id},0);";
					list($id, $rows_affected) = $this->db->Execute($sql);

					// Add Tags
					echo "* Inserting Tags\n";
					foreach($tags as $tag_id=>$tag_slug){
						$sql = "INSERT INTO {$this->db_table_prefix}term_relationships (`object_id`, `term_taxonomy_id`, `term_order`) VALUES({$post_id},{$tag_id},0);";
						list($id, $rows_affected) = $this->db->Execute($sql);
					}

					// Add gp_id to Meta Data
					echo "* Inserting Meta gp_id\n";
					$sql = "INSERT INTO {$this->db_table_prefix}postmeta (`post_id`, `meta_key`, `meta_value`) VALUES({$post_id},'gp_id','{$gp_id}');";
					list($id, $rows_affected) = $this->db->Execute($sql);

					// Add gp_url to Meta Data
					if($url){
						echo "* Inserting Meta url\n";
						$sql = "INSERT INTO {$this->db_table_prefix}postmeta (`post_id`, `meta_key`, `meta_value`) VALUES({$post_id},'gp_url','{$url}');";
						list($id, $rows_affected) = $this->db->Execute($sql);
					}
				}else{
					echo "* Failed To Insert Post\n";
				}
			}else{
				echo "* Google+ Post Already Found, Skipping\n";
			}
		}else{
			echo "* Google+ ID Not Found\n";
		}
	}
}

?>