<?php
/*
 * Refresh memcache cache for changed photosets
 * If given an argument, force refresh on all photosets and photos within them
 *
 *
 */


	require_once 'config.flickr.php';
	require_once 'hdFlickr.php';


	// UTF-8
	mb_internal_encoding("UTF-8");


	$force_refresh = FALSE;
	if($argc > 1 && (!strcasecmp($argv[1], "-h") || !strcasecmp($argv[1], "--help"))) {
		echo "This utility script refreshes the internal memcached-based cache.\n";
		echo "To refresh changed (i.e, diffrent number of images) photosets:\n";
		echo "  \$ php refresh.php\n";
		echo "To refresh all available photosets:\n";
		echo "  \$ php refresh.php all\n";
		echo "\n";
	}
	else if($argc > 1) {
		echo "* Forcing refresh of cached data for all photosets and their photos\n";
		$force_refresh = TRUE;
	}


	// Connect to memcache, if available
	$mc = NULL;
	if(class_exists("Memcache")) {
		$mc = new Memcache;
		if(!@$mc->connect("localhost", 11211))
			$mc = NULL;
	}



	// Initialize API access
	$flickr = new hdFlickr($api_key, $api_secret, $flickr_username, $mc);
	if($flickr->initialize() === FALSE) {
		die($generic_error_msg);
	}



	// Print current statistics
	$stats = array();
	if(($details = $flickr->getStats()) !== FALSE) {
		foreach($details as $k => $v)
			$stats[] = "$k=$v";

		if($details["cache_hits"] > 0 && $details["cache_misses"] > 0)
			$stats[] = sprintf("hit-ratio=%.2f", 100.0 * $details["cache_hits"] / ($details["cache_hits"] + $details["cache_misses"]));
	}
	else
		$stats[] = "N/A";
	echo "* Currents stats: ". implode(", ", $stats) ."\n";
		
	

	// Fetch a (possibly) cached list
	$flickr->useCache(TRUE);
	$old = $flickr->getPhotosetsXML();

	// ..and a known fresh list
	$flickr->useCache(FALSE);
	$fresh = $flickr->getPhotosetsXML();

	echo "* Refreshed list of photosets (photosets.getList)\n";
	echo "  - Number of sets in cached copy: ". count($old->photoset) ."\n";
	echo "  - Number of sets in fresh copy: ".  count($fresh->photoset) ."\n";



	// Compare the fresh list of photosets with the old one 
	// and build a list of photosets that need to be refreshed
	$photosets_to_refresh = array();
	foreach($fresh->photoset as $fp) {

		if($force_refresh) {
			$photosets_to_refresh[] = (string)$fp["id"];
			continue;
		}


		$found = FALSE;
		foreach($old->photoset as $op) {
			if(strcmp((string)$fp["id"], (string)$op["id"]))
				continue;

			$found = TRUE;
			break;
		}

		if(!$found) {
			echo "* Photoset with id '". (string)$fp["id"] ."' was previously not present in cache (NEW)!\n";
			$photosets_to_refresh[] = (string)$fp["id"];
			continue;
		}

		$diffs = array();
		if((string)$fp["photos"] != (string)$op["photos"])
			$diffs[] = "Number of photos differs, old=". (string)$op["photos"] .", new=". (string)$fp["photos"];
		
		if((string)$fp["primary"] != (string)$op["primary"])
			$diffs[] = "Primary ID differs, old=". (string)$op["primary"] .", new=". (string)$fp["primary"];
		
		if(strcmp($fp->title, $op->title))
			$diffs[] = "Title differs, new='". $fp->title ."'";

		if(strcmp($fp->description, $op->description))
			$diffs[] = "Description differs, new='". ereg_replace("\r\n|\r|\n", " ", $fp->desciption) ."'";

		if(count($diffs) == 0)
			continue;


		echo "* Photoset with id '". (string)$fp["id"] ."' differs from the cached copy\n";
		foreach($diffs as $str)
			echo "  - $str\n";


		$photosets_to_refresh[] = (string)$fp["id"];
	}



	echo "* Refreshing ". count($photosets_to_refresh) ." photosets";
	if(count($photosets_to_refresh))
		echo " with id '". implode("', '", $photosets_to_refresh) ."'\n";
	else
		echo "\n";

	$t0 = time();
	foreach($photosets_to_refresh as $set_id) {
		echo "  $set_id: Refreshing meta data.. ";
		if(($photoset = $flickr->getPhotosetXML($set_id)) === FALSE)
			echo "FAILED!\n";
		else
			echo "OK!\n";


		echo "  $set_id: Refreshing list of photos.. ";
		if(($photos = $flickr->getPhotosXML($set_id)) === FALSE)
			echo "FAILED!\n";
		else
			echo "OK, set has ". count($photos->photo) ." photos\n";



		// Simluate browsing photos (image.php) to refresh all required data
		echo "  $set_id: Refreshing photo details (phots.getInfo) and context (photos.getContext)\n";
		$t1 = time();
		$i = 1;
		$num_photos = count($photos->photo);
		foreach($photos->photo as $p) {
			$photo_id = (string)$p["id"];

			echo sprintf("  $set_id: (photo %02d/%02d) Refreshing details (photos.getInfo) for $photo_id.. ", $i, $num_photos);
			if(($context = $flickr->getPhotoInfo((string)$p["id"], (string)$p["secret"])) === FALSE)
				echo "FAILED\n";
			else
				echo "OK\n";

			echo sprintf("  $set_id: (photo %02d/%02d) Refreshing photoset context for $photo_id.. ", $i, $num_photos);
			if(($context = $flickr->getPhotosetContext($set_id, $p["id"])) === FALSE)
				echo "FAILED\n";
			else
				echo "OK\n";

			$i++;
		}

		echo sprintf("  $set_id: Done refreshing set, %d photos refreshed in %d seconds (%.2f/sec)\n", $num_photos, time() - $t1, $num_photos / (time() - $t1));
	}


	echo "* Done refreshing cache, ". (time() - $t0) ." seconds elapsed\n";


	$stats = array();
	if(($details = $flickr->getStats()) !== FALSE) {
		foreach($details as $k => $v)
			$stats[] = "$k=$v";

		if($details["cache_hits"] > 0 && $details["cache_misses"] > 0) {
			$stats[] = sprintf("hit-ratio=%.2f", 100.0 * $details["cache_hits"] / ($details["cache_hits"] + $details["cache_misses"]));
		}
	}
	else
		$stats[] = "N/A";
	echo "* New stats: ". implode(", ", $stats) ."\n";
		
?>
