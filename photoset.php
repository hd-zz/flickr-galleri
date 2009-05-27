<?php

	echo "<!-- Flickr: in photoset.php -->\n";
	$photoset = $flickr->getPhotosetXML($set_id);
	$photos = $flickr->getPhotosXML($set_id);


	echo "<!-- Flickr: photoset claims ". (string)$photoset["photos"] ." number of photos. -->\n";
	echo "<!-- Flickr: photos claims ". count($photos->photo) ." number of photos. -->\n";


?>
<h1>Galleri</h1>

<h2><?= htmlspecialchars($photoset->title, ENT_NOQUOTES) ?></h2>
<?php
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
