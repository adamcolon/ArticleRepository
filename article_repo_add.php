<?php
	require('models/article.php');
	
	echo '<html style="height:25px;"><body>';

	$article = new Article($_POST);
	$result = $article->add();
	if($result['success']){
		echo '<div style="white-space:nowrap;">Successfully Added Article.</div>';
	}else{
		echo '<div style="white-space:nowrap;">Failed to Add Article.</div>'.implode("\n", $result['messages']);
	}

	echo '</body></html>';
?>