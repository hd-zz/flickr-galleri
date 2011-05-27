<?php
	$title = "";
	$desc = "";
	if(isMachineTag($set_id)) {
		$photos = $flickr->searchPhotosByMachineTags($set_id);
		$title = "Taggade bilder";
	}
	else {
		$photos = $flickr->getPhotosXML($set_id);
		$photoset = $flickr->getPhotosetXML($set_id);
		$title = $photoset->title;
		$desc = $photoset->description;
	}


	// How we're displaying the title
	if(!isset($photoset_mode))
		$photoset_mode = "default";

	switch($photoset_mode) {
		case "default":
			echo "<h1>Galleri</h1>\n";
			echo "<h2>". htmlspecialchars($title, ENT_NOQUOTES) ."</h2>\n";
			if(!empty($desc))
				echo "<p>". htmlspecialchars($desc, ENT_NOQUOTES) ."</p>\n";
			break;

		case "more":
			echo "<h2>Fler bilder i albumet</h2>\n";
			break;
	}

	if($photos !== FALSE) {


	// List all photos available
	$i = 0;
	$num_photos = count($photos->photo);
	foreach($photos->photo as $p) {

		// Fetch thumb URL and details for this photo
		$thumb_url = $flickr->getPhotoURL($p, "t");
		$details = $flickr->getPhotoInfo($p["id"], $p["secret"]);
		if($thumb_url === FALSE || $details === FALSE) {
			echo "<!-- ERROR: Failed to get URL or details for photo with id '". htmlspecialchars($p["id"]) ."' -->\n";

			$num_photos--;
			continue;
		}


		// Link to URL displaying that image
		$link_url = makeImageLink($p["id"], $set_id);


		// Make up a date to present below the picture
		$ts = (string)$details->dates["posted"]; // Unix timestamp
		if(isset($details->dates["taken"]) && !empty($details->dates["taken"]))
			$ts = strtotime((string)$details->dates["taken"]); // YYYY-mm-dd HH:MM:SS

		$datestr  = strftime("%d/%m kl %H:%M", $ts);
		


		$p_class = "";
		if($i % 4 == 0) {
			// Börja nytt div-block, och sätt $p_class till 'newgrp'
			$p_class = ' class="newgrp"';
?>
<div class="list-img-small">
<?php
		}


?>
	<p<?= $p_class ?>>
		<a class="thumb name-galleri" href="<?= htmlspecialchars($link_url) ?>">
			<img src="<?= $thumb_url ?>" alt="<?= htmlspecialchars($p["title"]) ?>" />
		</a>
		<span><?= $datestr ?></span>
	</p>
<?php

		$i++;


		// Avsluta diven om vi visat fyra bilder, eller om alla bilderna är slut
		if($i % 4 == 0 || $i == $num_photos) {
?>
</div>
<?php
		}	// if last photo in row, or final photo


	} // foreach

	}
	else {
?>
	<p>Hittade inte albumet. Det kan ha tagits bort.</p>
<?php
	}
?>
