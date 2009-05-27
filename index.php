<?php
	require_once 'config.flickr.php';
	require_once 'hdFlickr.php';


	// UTF-8
	mb_internal_encoding("UTF-8");
	header("Content-Type: text/html; charset=utf-8");


	// Connect to memcache, if available
	$mc = NULL;
	if(class_exists("Memcache")) {
		$mc = new Memcache;
		if(!@$mc->connect("localhost", 11211))
			$mc = NULL;
	}




	// For determining whether a set_id is a machine tag or not
	function isMachineTag($set_id) {
		return preg_match("/^[a-z]/i", $set_id);
	}


	// Decode encoded machine tag in argument 
	//   1234 -> 1234
	//   ns-foo-bar -> ns:foo=bar
	function decodeMachineTagArgument($set_id) {
		if(!isMachineTag($set_id))
			return $set_id;

		$temp = explode("-", $set_id, 3);
		assert(count($temp) == 3);

		return $temp[0] .":". $temp[1] ."=". $temp[2];
	}


	// Encode eventual machine tag in argument
	//   ns:foo=bar -> ns-foo-bar
	function encodeMachineTagArgument($set_id) {
		return preg_replace(array("@:@", "@=@"), array("-", "-"), $set_id, 1);
	}


	// For creating a link to the page that display an image
	function makeImageLink($id, $set_id) {
		// If $set_id is a machine tag, encode it (replacing ':' and '=' with dashes)
		$set_id = encodeMachineTagArgument($set_id);

		return "./?id=$id&set=$set_id";
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
		$set_id = decodeMachineTagArgument($_GET["set"]);

		include("image.php");
	}
	else if(isset($_GET["set"])) {
		$set_id = decodeMachineTagArgument($_GET["set"]);

		include("photoset.php");
	}
	else if(isset($argv) && count($argv) > 1) {
		// Command line support (for debugging purposes..)
		for($argc = 1; $argc < count($argv) + 1; $argc += 2) {
			if(!strcmp($argv[$argc], "id") && $argc < count($argv) + 2) {
				$img_id = $argv[$argc + 1];
				$set_id = decodeMachineTagArgument($argv[$argc + 2]);

				include("image.php");
			}
			else if(!strcmp($argv[$argc], "set")) {
				$set_id = decodeMachineTagArgument($argv[$argc + 1]);
		
				include("photoset.php");
			}
		}
	}
	else
		include("frontpage.php");

?>
</div>
