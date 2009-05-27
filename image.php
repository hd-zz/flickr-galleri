<?php

	echo "<!-- Flickr: in image.php -->\n";

	$context = $flickr->getPhotosetContext($set_id, $img_id);
	$photoset = $flickr->getPhotosetXML($set_id, $img_id);
	$photos = $flickr->getPhotosXML($set_id, $img_id);
	$details = $flickr->getPhotoInfo($img_id, $photo["secret"]);
	$img_url = $flickr->getPhotoURL($set_id, $img_id);


	if($context === FALSE || $photoset === FALSE || $photos === FALSE || $details === FALSE || $img_url === FALSE)
		die($generic_error_msg);



	echo "<!-- Flickr: photoset claims ". (string)$photoset["photos"] ." number of photos. -->\n";
	echo "<!-- Flickr: photos claims ". count($photos->photo) ." number of photos. -->\n";


	$photo_number = 1;
	foreach($photos->photo as $p) {
		if(!strcmp($details["id"], $p["id"]))
			break;
		$photo_number++;
	}


	// Generate navigation HTML
	ob_start();
?>
	<ul class="nav-offset-alt">
		<li class="current">
		Bild <strong><?= $photo_number ?></strong> av <strong><?= count($photos->photo) ?></strong>
		</li>
<?php
	if((string)$context->prevphoto["id"] != "0") {
?>
		<li class="prev">
			<a href="./?id=<?= $context->prevphoto["id"] ?>&amp;set=<?= $set_id ?>" title="<?= htmlspecialchars($context->prevphoto["title"]) ?>">Föregående</a>
		</li>
<?php
	}
	if((string)$context->nextphoto["id"] != "0") {
?>
		<li class="next">
			<a href="./?id=<?= $context->nextphoto["id"] ?>&amp;set=<?= $set_id ?>" title="<?= htmlspecialchars($context->nextphoto["title"]) ?>">Nästa</a>
		</li>
<?php
	}
	else {
?>
		<li class="next">
			<a href="./?set=<?= $set_id ?>" title="<?= htmlspecialchars("Återgå till: ". $photoset->title) ?>">Återgå till galleriet</a>
		</li>
<?php
	}
?>
	</ul>
<?php
	$nav_html = ob_get_contents();
	ob_end_clean();
?>
<h1><?= htmlspecialchars($photoset->title) ?></h1>
<?= $nav_html ?>
<div style="width: 486px; text-align: center">
	<!--
	<h2 style="text-align: center"><?= htmlspecialchars($details->title, ENT_NOQUOTES) ?></h2>
	-->
	<img src="<?= htmlspecialchars($img_url) ?>" alt="<?= htmlspecialchars($details->title) ?>" />
	<p style="padding-left: 5px; text-align: left"><?= preg_replace("@\r\n|\r|\n@", "<br />\n", $details->description) ?></p>
</div>
<?= $nav_html ?>
<h2>Fler bilder i galleriet</h2>
<?php
	/*
	 * Detta är _exakt_ samma som photoset.php, minus några rader i toppen!
	 *
	 * Jag har läst in innehållet med ':r photoset.php' i vi och sedan raderat
	 * alla rader fram till och med <h2>-elementet.
	 *
	 */

	$i = 0;
	$num_photos = count($photos->photo);
	foreach($photos->photo as $p) {

		$thumb_url = $flickr->getPhotoURL($set_id, $p["id"], "t");
		if($thumb_url === FALSE)
			die("Failed to get photoURL for set:". $set_id ." and img:". $p["id"] ."\n");


		$ts = $p["dateupload"]; // Unix timestamp
		if(isset($p["datetaken"]) && !empty($p["datetaken"]))
			$ts = strtotime($p["datetaken"]); // YYYY-mm-dd HH:MM:SS

		$datestr  = strftime("%d/%m kl %H:%M", $ts);
		

		$p_class = "";
		if($i % 4 == 0) {
			// Börja nytt div-block, och sätt $p_class till 'newgrp'
			$p_class = ' class="newgrp"';
?>
<!-- Flickr: starting new block, on photo <?= ($i+1) ." of ". $num_photos ?> -->
<div class="list-img-small">
<?php
		}
?>
	<p<?= $p_class ?>>
		<a class="thumb name-galleri" href="./?id=<?= $p["id"] ?>&amp;set=<?= $set_id ?>">
			<img src="<?= $thumb_url ?>" alt="<?= htmlspecialchars($p["title"]) ?>" />
		</a>
		<span>
			<?= $datestr ?>
		</span>
	</p>
<?php
		$i++;
		// Avsluta diven om vi visat fyra bilder, eller om alla bilderna är slut
		if($i % 4 == 0 || $i == $num_photos) {
?>
</div>
<!-- Flickr: ending div-block, current photo is <?= $i ." of ". $num_photos ?> -->
<?php
		}


	} // foreach
?>
