<h1>Galleri</h1>
<p>Välkommen till hd.se fotogalleri. Välj ett galleri nedan.</p>

<div class="results">
<h2 class="sep">Tillgängliga gallerier</h2>
<?php

	$photosets = $flickr->getPhotosetsXML();
        foreach($photosets->photoset as $ps) {
		$i++;


                $set_id = $ps["id"];
		$div_class = "story";
		if($i == 1)
			$div_class .= " first";


		$thumb_url = "#";
		$num_images_text = "Inga bilder";

		$photoset_photos = $flickr->getPhotosXML($ps["id"]);
		if($photoset_photos !== FALSE && count($photoset_photos->photo) > 0) {
			$thumb_url = $flickr->getPhotoURL($photoset_photos->photo[0], "t");

			$num_images_text = count($photoset_photos->photo);
			if($num_images_text != 1)
				$num_images_text .= " bilder";
			else
				$num_images_text .= " bild";
		}

?>
	<div class="<?= $div_class ?>">
		<p>
		<a href="./?set=<?= $ps["id"] ?>"><img src="<?= $thumb_url ?>" alt="" /></a>
		</p>
		<h3><a href="./?set=<?= $ps["id"] ?>"><?= htmlspecialchars($ps->title, ENT_NOQUOTES, "UTF-8") ?></a></h3>
		<ul class="meta">
			<li><strong><?= $num_images_text ?></strong></li>
			<li><?= htmlspecialchars($ps->description, ENT_NOQUOTES, "UTF-8") ?></li>
		</ul>
	</div>
<?php	
        }
?>
</div> <!-- / results -->
