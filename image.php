<?php

	echo "<!-- Flickr: in image.php -->\n";

	$photoset_title = "";
	if(isMachineTag($set_id)) {
		$photoset_title = "Taggade bilder";
		$photos = $flickr->searchPhotosByMachineTags($set_id);

	}
	else {
		$photoset = $flickr->getPhotosetXML($set_id);
		$photos = $flickr->getPhotosXML($set_id);
		$photoset_title = $photoset->title;
		$context = $flickr->getPhotosetContext($set_id, $img_id);
	}


	// Find this photo among returned photos and set its index ($photo_number)
	$photo_number = 1;
	$prev_photo = $next_photo = $this_photo = FALSE;
	foreach($photos->photo as $p) {
		if($this_photo !== FALSE && $next_photo == FALSE) {
			$next_photo = $p;
			break;
		}
		else if(!strcmp($img_id, (string)$p["id"])) {
			$this_photo = $p;
			continue;
		}

		$photo_number++;
		$prev_photo = $p;
	}
	$p = $this_photo;



	// Emulate getPhotosetContext() when $set_id is a machine tag
	if(isMachineTag($set_id)) {
		$xml = '<?xml version="1.0" encoding="utf-8" ?>';
		$xml .= '<context>';

		if(!$prev_photo || ($temp_details = $flickr->getPhotoInfo($prev_photo["id"], $prev_photo["secret"])) === FALSE)
			$xml .= '<prevphoto id="0" title="" />';
		else
			$xml .= '<prevphoto id="'. htmlspecialchars((string)$prev_photo["id"]) .'" title="'. htmlspecialchars($temp_details->title) .' " />';

		if(!$next_photo || ($temp_details = $flickr->getPhotoInfo($next_photo["id"], $next_photo["secret"])) === FALSE)
			$xml .= '<nextphoto id="0" title="" />';
		else
			$xml .= '<nextphoto id="'. htmlspecialchars((string)$next_photo["id"]) .'" title="'. htmlspecialchars($temp_details->title) .' " />';

		$xml .= '</context>';

		echo "$xml\n";
		$context = simplexml_load_string($xml);
	}


	if($p === FALSE)
		die($generic_error_msg);

	// Get URL and details for this pgoto
	$img_url = $flickr->getPhotoURL($p);
	$details = $flickr->getPhotoInfo($p["id"], $p["secret"]);
	

	if($context === FALSE || $photos === FALSE || $details === FALSE || $img_url === FALSE)
		die($generic_error_msg);




	// Generate navigation HTML
	ob_start();
?>
	<ul class="nav-offset-alt">
		<li class="current">
		Bild <strong><?= $photo_number ?></strong> av <strong><?= count($photos->photo) ?></strong>
		</li>
<?php
	if((string)$context->prevphoto["id"] != "0") {
		$params = array(
			'id='. urlencode((string)$context->prevphoto["id"]),
			'set='. encodeMachineTagArgument($set_id)
		);
		if($page > 1)
			$params[] = 'p='. urlencode($page);

		$link = './?'. implode('&', $params);
?>
		<li class="prev">
			<a href="<?php echo htmlspecialchars($link) ?>" title="<?= htmlspecialchars($context->prevphoto["title"]) ?>">Föregående</a>
		</li>
<?php
	}
	if((string)$context->nextphoto["id"] != "0") {
		$params = array(
			'id='. urlencode((string)$context->nextphoto["id"]),
			'set='. encodeMachineTagArgument($set_id)
		);
		if($page > 1)
			$params[] = 'p='. urlencode($page);

		$link = './?'. implode('&', $params);
?>
		<li class="next">
			<a href="<?php echo htmlspecialchars($link) ?>" title="<?= htmlspecialchars($context->nextphoto["title"]) ?>">Nästa</a>
		</li>
<?php
	}
	else {
		$params = array(
			'set='. encodeMachineTagArgument($set_id)
		);
		if($page > 1)
			$params[] = 'p='. urlencode($page);

		$link = './?'. implode('&', $params);
?>
		<li class="next">
			<a href="<?php echo htmlspecialchars($link) ?>" title="<?= htmlspecialchars("Återgå till: ". $photoset_title) ?>">Återgå till galleriet</a>
		</li>
<?php
	}
?>
	</ul>
<?php
	$nav_html = ob_get_contents();
	ob_end_clean();
	// Done generating navigation



?>
<h1><?= htmlspecialchars($photoset_title) ?></h1>
<?= $nav_html ?>
<div style="width: 486px; text-align: center">
	<!--
	<h2 style="text-align: center"><?= htmlspecialchars($details->title, ENT_NOQUOTES) ?></h2>
	-->
	<img src="<?= htmlspecialchars($img_url) ?>" alt="<?= htmlspecialchars($details->title) ?>" />
	<p style="padding-left: 5px; text-align: left"><?= preg_replace("@\r\n|\r|\n@", "<br />\n", $details->description) ?>
	<span style="float: right; clear: left; padding: 2px">Psst! Bilden finns också på <a href="http://flickr.com/photos/<?= htmlspecialchars($flickr->getNsid() ."/". (string)$p["id"]) ?>" onclick="return HD.se.externalize(this)">Flickr</a></span>
	</p>
</div>
<?php


	// Display navigation once again
	echo $nav_html;


	// Display thumbnails of photos in the gallery
	$photoset_mode = "more";
	include("photoset.php");

?>
