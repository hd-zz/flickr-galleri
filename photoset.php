<?php

	echo "<!-- Flickr: in photoset.php -->\n";



	$title = "";
	$desc = "";

	$num_photos_total = 0;
	$num_pages = 0;
	if(isMachineTag($set_id)) {
		$photos = $flickr->searchPhotosByMachineTags($set_id);
		$title = "Taggade bilder";
	}
	else {
		// Räkna ut antal sidor baserat på antalet bilder
		$photoset = $flickr->getPhotosetXML($set_id);
		$num_photos_total = (string)$photoset['photos'];
		$num_pages = ceil($num_photos_total / $photos_per_page);

		$photos = $flickr->getPhotosXML($set_id, $photos_per_page, $page);
		$title = $photoset->title;
		$desc = $photoset->description;
	}


	
	// HTML för sidnavigeringen
	$navigering_html = '';
	if($num_pages > 1) {
		// Wrapper
		$navigering_html .= '<ul class="nav-offset" style="list-style-type: none">';
		// List
		$navigering_html .= '<li class="current">';

		// Many result pages?
		if($results_page > 5)
			$navigering_html .= ' .. ';
			
		// Links next pages
		for($i = 1; $i <= $num_pages; $i++) {
			if($i > ($page - 5) && $i < ($page + 5)) {
				if($i == $page)
					$navigering_html .= ' <strong>' + $i + '</strong> ';
				else {
					$navigering_html .= ' <a href="?p='. $i .'&amp;set='. encodeMachineTagArgument($set_id);
					$navigering_html .= '">'. $i .'</a> ';
				}
			}
		}
		
		// Are we far from the end?
		if($page < ($num_pages - 4))
			$navigering_html .= ' .. ';
		
		// End the list
		$navigering_html .= '</li>';
		
		// Previous page link
		if($page > 1) {
			$navigering_html .= '<li class="prev"><a href="?p='. ($page - 1) .'&amp;set='. encodeMachineTagArgument($set_id);
			$navigering_html .= '">Föregående sida</a></li>';
		}

		// Next page link
		if($page < $num_pages) {
			$navigering_html .= '<li class="next"><a href="?p='. ($page + 1) .'&amp;set='. encodeMachineTagArgument($set_id);
			$navigering_html .= '">Nästa sida</a></li>';
		}

		$navigering_html .= '</ul>';
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

	default:
		break;
	}


	if($photos !== FALSE) {

	// Navigation
	echo $navigering_html;

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
		$link_url = makeImageLink($p["id"], $set_id, $page);


		// Make up a date to present below the picture
		$ts = (string)$details->dates["posted"]; // Unix timestamp
		if(0 && isset($details->dates["taken"]) && !empty($details->dates["taken"]))
			$ts = strtotime((string)$details->dates["taken"]); // YYYY-mm-dd HH:MM:SS

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
<!-- Flickr: ending div-block, current photo is <?= $i ." of ". $num_photos ?> -->
<?php
		}	// if last photo in row, or final photo


	} // foreach

	// Navigation
	echo $navigering_html;
	}
	else {
?>
	<p>Hittade inte albumet. Det kan ha tagits bort.</p>
<?php
	}
?>
