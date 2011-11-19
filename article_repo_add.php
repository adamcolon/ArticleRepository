<?php
	require('models/article.php');
	
	print_r($_POST));
	echo '<html style="height:25px;"><body>';

	$article = new Article($_POST);
	if($article->add()){
		echo '<div style="white-space:nowrap;">Successfully Added Article.</div>';
	}else{
		echo '<div style="white-space:nowrap;">Failed to Add Article.</div>';
	}

	echo '</body></html>';
?>