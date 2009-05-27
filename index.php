<?php
	require_once 'config.flickr.php';
	require_once 'hdFlickr.php';

	// UTF-8
	mb_internal_encoding("UTF-8");
	header("Content-Type: text/html; charset=utf-8");

	echo "<!-- Flickr: in index.php -->\n";


	// Memcache
	$mc = NULL;
	if(class_exists("Memcache")) {
		$mc = new Memcache;
		if(!@$mc->connect("localhost", 11211))
			$mc = NULL;
	}
?>
<div id="flickr-galleri">
<?php


	$flickr = new hdFlickr($api_key, $api_secret, $flickr_username, $mc);
	if($flickr->initialize() === FALSE) {
		die($generic_error_msg);
	}

	echo "<!-- Flickr: initialized! -->\n";


	if(isset($_GET["id"])) {
		$img_id = $_GET["id"];
		$set_id = $_GET["set"];
		include("image.php");
	}
	else if(isset($_GET["set"])) {
		$set_id = $_GET["set"];
		include("photoset.php");
	}
	else
		include("frontpage.php");

?>
</div>
