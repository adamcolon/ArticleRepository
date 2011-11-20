<?php
/* Google+ To Wordpress
 * Usage: This can be called as a web page or in a cron
* For each Google+ entry in the user's public stream, it uses the Wordpress postmeta data to store G+ ID data preventing duplicate posts.
* It compares wordpress tags with #hashtags in the post annotation to filter out only the posts a particular blog wants.
* If a post title is too long or missing, it will insert the post with a status of 'pending'.
*/
require('config/wp_config.php');
require('config/settings.php');
require('models/article.php');
require('models/wordpress.php');
main($wordpress_settings);

function main($wordpress_settings){
	echo "<h1>Article Repository Wordpress Publish</h1><div>".date('Y-m-d H:i:s')."</div>";

	// Instantiate main objects
	$Wordpress = new Wordpress($wordpress_settings['db_host'], $wordpress_settings['db_username'], $wordpress_settings['db_password'], $wordpress_settings['db_name'], $wordpress_settings['db_table_prefix']);
	
	echo "<style>th {text-align:right;background-color:#ddd;}</style><table>";
	echo "	<tr><th>Wordpress Host:</th><td>{$wordpress_settings['db_host']}</td></tr>";
	echo "	<tr><th>Wordpress Default Status:</th><td>{$wordpress_settings['default_status']}</td></tr>";
	echo "	<tr><th>Wordpress Author Id:</th><td>{$wordpress_settings['author_id']}</td></tr>";
	echo "	<tr><th>Wordpress Category Id:</th><td>{$wordpress_settings['category_id']}</td></tr>";
	echo "	<tr><th>Wordpress Live Publish:</th><td>{$wordpress_settings['live']}</td></tr>";

	// Grab list of Wordpress categories and tags to filter the G+ stream against
	$wp_tags = $Wordpress->getPostTags();
	$wp_categories = $Wordpress->getCategories();
	
	echo "	<tr><th>Wordpress Category:</th><td>".implode(',', $wp_categories)."</td></tr>";
	echo "	<tr><th>Wordpress Tags:</th><td>".implode(',', $wp_tags)."</td></tr>";
	echo "</table><hr>";

	if($wp_domain = $Wordpress->getOptions('siteurl')){
		$Article = new Article();
		if($articles = $Article->getPostData($wp_domain, $wp_categories)){
			foreach($articles as $article){
				$tag_lists = $wp_categories + $wp_tags;
				$article_tags = $Article->getTags($article['id'], $tag_lists);

				foreach($tag_lists as $tag_id=>$tag_name){
					if(in_array($tag_name, $article_tags)){
						$tags["{$tag_id}"] = $tag_name;
					}
				}

				echo "<pre>".print_r($article, true)."</pre>";
				$Wordpress->insertPost($wordpress_settings['author_id'], array('url_hash'=>$article['hash']), $article['created'], $article['title'], $article['summary'], $tags, 'publish');
				$Article->markPosted($wp_domain, $article['id']);
			}
		}else{
			echo "No Articles Found<br>";
		}
	}else{
		echo "Site URL Not Found<br>";
	}

}

?>