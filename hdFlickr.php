<?php

	class hdFlickr {
		var $errorCode;
		var $errorMsg;

		// Flickr stuff
		private $apikey;
		private $apisecret;
		private $username;
		private $nsid;
	
		// Caching (memcache)
		private $mc;
		private $mc_prefix;
		private $flush_cache;

		private $curl;

		function __construct($key, $secret, $username, &$mc = FALSE) {
			$this->apikey = $key;
			$this->apisecret = $secret;
			$this->username = $username;

			$this->mc = $mc;
			$this->mc_prefix = "flickr";
			$this->flush_cache = FALSE;

			$this->nsid = FALSE;


			$this->curl = curl_init();
			curl_setopt($this->curl, CURLOPT_TIMEOUT, 8);
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, TRUE);
		}


		function apicall($method, $params) {
			$url = "http://api.flickr.com/services/rest/?method=";
			$url .= rawurlencode($method) ."&";
			foreach($params as $k => $v)
				$url .= rawurlencode($k) ."=". $v ."&";
			$url = trim($url, "&");

			curl_setopt($this->curl, CURLOPT_URL, $url);
			$data = curl_exec($this->curl);
			$xml = simplexml_load_string($data);



			if($xml["stat"] != "ok") {
				$this->errorCode = $xml->err["code"];
				$this->errorMsg = $xml->err["msg"];
				return FALSE;
			}

			return $xml;
		}


		function initialize() {
			$mc_key = $this->mc_prefix . "nsid";

			if(!$this->mc || $this->flush_cache || ($this->nsid = $this->mc->get($mc_key)) === FALSE) {
				$xml = $this->apicall('flickr.people.findByUsername',
								array(	"api_key" => $this->apikey,
									"username" => $this->username,
								)
				);

				if($xml === FALSE)
					return FALSE;

				$this->nsid = (string)$xml->user["nsid"];
				if($this->mc)
					$this->mc->set($mc_key, $this->nsid);
			}

			return TRUE;
		}



		function useCache($use = TRUE) {
			$this->flush_cache = !$use;
		}


		// Return 'photosets' node with 'photoset' children
		function getPhotosetsXML() {
			assert($this->nsid !== FALSE);

			// Hämta lista på galleri
			$mc_key = $mc_prefix ."photosets";
			if(!$this->mc || $this->flush_cache || ($text = $this->mc->get($mc_key)) === FALSE) {
				$xmlobj = $this->apicall('flickr.photosets.getList',
								array(	"api_key" => $this->apikey,
									"user_id" => $this->nsid
								)
				);

				if($xmlobj === FALSE)
					return FALSE;

				$text = $xmlobj->asXML();
				if($this->mc)
					$this->mc->set($mc_key, $text, 0, 300);
			}
			else
				$xmlobj = simplexml_load_string($text);

			return $xmlobj->photosets;
		}


		// http://www.flickr.com/services/api/flickr.photosets.getInfo.html
		// Return 'photoset' node describing the photoset (title and description)
		function getPhotosetXML($photoset_id) {
			assert($this->nsid !== FALSE);

			// Hämta lista på galleri
			$mc_key = $mc_prefix ."photosets:$photoset_id";
			if(!$this->mc || $this->flush_cache || ($text = $this->mc->get($mc_key)) === FALSE) {
				$xmlobj = $this->apicall('flickr.photosets.getInfo',
								array(	"api_key" => $this->apikey,
									"photoset_id" => $photoset_id
								)
				);

				if($xmlobj === FALSE)
					return FALSE;

				$text = $xmlobj->asXML();
				if($this->mc)
					$this->mc->set($mc_key, $text, 0, 300);
			}
			else
				$xmlobj = simplexml_load_string($text);


			return $xmlobj->photoset;
		}


		// http://www.flickr.com/services/api/flickr.photosets.getPhotos.html
		// Return 'photoset' node with 'photo' children
		function getPhotosXML($photoset_id) {
			assert($this->nsid !== FALSE);

			// Hämta lista på galleri
			$mc_key = $mc_prefix ."photos:$photoset_id";
			if(!$this->mc || $this->flush_cache || ($text = $this->mc->get($mc_key)) === FALSE) {
				$xmlobj = $this->apicall('flickr.photosets.getPhotos',
								array(	"api_key"	=> $this->apikey,
									"photoset_id"	=> $photoset_id,
									"extras"	=> "date_upload,date_taken,last_update",
									"privacy_filter"=> 1 /* only public photos */
								)
				);

				if($xmlobj === FALSE)
					return FALSE;

				$text = $xmlobj->asXML();
				if($this->mc)
					$this->mc->set($mc_key, $text, 0, 300);
			}
			else
				$xmlobj = simplexml_load_string($text);


			return $xmlobj->photoset;
		}


		// Return 'photo' node; if photo_id is NULL, return primary photo
		function getPhotoXML($photoset_id, $photo_id = FALSE) {
			if(($photoset = $this->getPhotosXML($photoset_id)) === FALSE)
				return FALSE;

			foreach($photoset->photo as $p) {
				if(intval((string)$p["isprimary"]) == 1 && !$photo_id)
					return $p;
				else if(strcmp($p["id"], $photo_id) == 0)
					return $p;
			}

			return FALSE;
		}


		// Return URL of a photo; if photo_id is NULL, return primary photo
		// Size can be '' (500), 's' for small (75), 't' for thumb (100)
		function getPhotoURL($photoset_id, $photo_id = FALSE, $size = "") {
			$mc_key = $mc_prefix ."photo:url:$photoset_id:";
			if($photo_id === FALSE)
				$mc_key .= "primary:$size";
			else
				$mc_key .= "$photo_id:$size";

			if(!$this->mc || $this->flush_cache || ($url = $this->mc->get($mc_key)) === FALSE) {
				if(($photo = $this->getPhotoXML($photoset_id, $photo_id)) === FALSE) {
					return FALSE;
				}


				$url = sprintf("http://farm%d.static.flickr.com/%d/%s_%s%s.jpg",
						$photo["farm"], $photo["server"],
						$photo["id"], $photo["secret"], 
						(empty($size)? "": "_$size"));

				if($this->mc)
					$this->mc->set($mc_key, $url, 0, 300);
			}

			return $url;
		}


		function getPhotosetContext($photoset_id, $photo_id) {
			// Hämta lista på galleri
			$mc_key = $mc_prefix ."photoset:context:$photoset_id:$photo_id";
			if(!$this->mc || $this->flush_cache || ($text = $this->mc->get($mc_key)) === FALSE) {
				$xmlobj = $this->apicall('flickr.photosets.getContext',
								array(	"api_key"	=> $this->apikey,
									"photoset_id"	=> $photoset_id,
									"photo_id"	=> $photo_id
								)
				);

				if($xmlobj === FALSE)
					return FALSE;

				$text = $xmlobj->asXML();
				if($this->mc)
					$this->mc->set($mc_key, $text, 0, 300);
			}
			else
				$xmlobj = simplexml_load_string($text);

			return $xmlobj;
		}


		// http://www.flickr.com/services/api/flickr.photos.getInfo.html
		// Returns node with 'prevphoto' and 'nextphoto' children
		function getPhotoInfo($photo_id, $secret = FALSE) {
			$mc_key = $mc_prefix ."photo:info:$photo_id";
			if(!$this->mc || $this->flush_cache || ($text = $this->mc->get($mc_key)) === FALSE) {

				$params = array(
						"api_key"	=> $this->apikey,
						"photo_id"	=> $photo_id
				);
				if($secret !== FALSE)
					$params["secret"] = $secret;

				$xmlobj = $this->apicall('flickr.photos.getInfo', $params);
				if($xmlobj === FALSE)
					return FALSE;

				$text = $xmlobj->asXML();
				if($this->mc)
					$this->mc->set($mc_key, $text, 0, 300);
			}
			else
				$xmlobj = simplexml_load_string($text);

			return $xmlobj->photo;
		}
	}
?>
