<?php

	class hdFlickr {
		var $errorCode;
		var $errorMsg;

		// Flickr stuff
		private $apikey;
		private $apisecret;
		private $username;
		private $nsid;
		private $last_api_method;
		private $last_api_params;

		// Caching (memcache)
		private $mc;
		private $mc_prefix;
		private $flush_cache;
		private $mc_ttl;

		private $curl;

		function __construct($key, $secret, $username, &$mc = FALSE) {
			$this->apikey = $key;
			$this->apisecret = $secret;
			$this->username = $username;
			$this->last_api_method = "<none>";
			$this->last_api_params = array();

			$this->mc = $mc;
			$this->mc_prefix = "flickr";
			$this->mc_ttl = 3600 * 24;
			$this->flush_cache = FALSE;

			$this->nsid = FALSE;


			$this->curl = curl_init();
			curl_setopt($this->curl, CURLOPT_TIMEOUT, 8);
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, TRUE);
		}


		function initialize() {
			$mc_key = $this->mc_prefix . "nsid";

			if(!$this->mc || $this->flush_cache || ($this->nsid = $this->mc->get($mc_key)) === FALSE) {
				$this->logCacheMiss();
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
			else {
				$this->logCacheHit();
			}

			return TRUE;
		}


		function useCache($use = TRUE) {
			$this->flush_cache = !$use;
		}


		function logCacheHit() {
			if(!$this->mc || $this->flush_cache)
				return;


			$mc_key = $this->mc_prefix . "stats:cache_hits";
			if(@$this->mc->increment($mc_key) === FALSE)
				$this->mc->set($mc_key, 1);
		}


		function logCacheMiss() {
			if(!$this->mc || $this->flush_cache)
				return;


			$mc_key = $this->mc_prefix . "stats:cache_misses";
			if(@$this->mc->increment($mc_key) === FALSE)
				$this->mc->set($mc_key, 1);
		}


		function logAPIRequest() {
			if(($fd = @fopen("/tmp/flickr.log", "at")) !== FALSE) {
				$line = strftime("%Y-%m-%d %H:%M:%S");
				$line .= "\t". $this->last_api_method;
				$params = array();
				foreach($this->last_api_params as $k => $v)
					$params[] = "$k=$v";
				$line .= "\t". implode(";", $params);
				$line .= "\n";
				fwrite($fd, $line);
				fclose($fd);
			}

			if(!$this->mc)
				return;

			$mc_key = $this->mc_prefix . "stats:requests";
			if(@$this->mc->increment($mc_key) === FALSE)
				$this->mc->set($mc_key, 1);
		}


		function getStats() {
			if(!$this->mc)
				return FALSE;

			$ret = array();


			if(($value = $this->mc->get($this->mc_prefix ."stats:requests")) !== FALSE)
				$ret["requests"] = $value;
			else
				$ret["requests"] = -1;

			if(($value = $this->mc->get($this->mc_prefix ."stats:cache_hits")) !== FALSE)
				$ret["cache_hits"] = $value;
			else
				$ret["cache_hits"] = -1;

			if(($value = $this->mc->get($this->mc_prefix ."stats:cache_misses")) !== FALSE)
				$ret["cache_misses"] = $value;
			else
				$ret["cache_misses"] = -1;


			return $ret;
		}


		function getNsid() {
			return $this->nsid;
		}


		function apicall($method, $params) {
			$this->last_api_method = $method;
			$this->last_api_params = $params;

			$url = "http://api.flickr.com/services/rest/?method=";
			$url .= rawurlencode($method) ."&";
			foreach($params as $k => $v)
				$url .= rawurlencode($k) ."=". $v ."&";
			$url = trim($url, "&");

			curl_setopt($this->curl, CURLOPT_URL, $url);
			$data = curl_exec($this->curl);
			$this->logAPIRequest();


			$xml = simplexml_load_string($data);
			if($xml["stat"] != "ok") {
				$this->errorCode = $xml->err["code"];
				$this->errorMsg = $xml->err["msg"];
				return FALSE;
			}

			return $xml;
		}


		// Return 'photosets' node with 'photoset' children
		// http://www.flickr.com/services/api/flickr.photosets.getList.html
		function getPhotosetsXML() {
			assert($this->nsid !== FALSE);

			// Hämta lista på galleri
			$mc_key = $this->mc_prefix ."photosets";
			if(!$this->mc || $this->flush_cache || ($text = $this->mc->get($mc_key)) === FALSE) {
				$this->logCacheMiss();
				$xmlobj = $this->apicall('flickr.photosets.getList',
								array(	"api_key" => $this->apikey,
									"user_id" => $this->nsid
								)
				);

				if($xmlobj === FALSE)
					return FALSE;

				$text = $xmlobj->asXML();
				if($this->mc)
					$this->mc->set($mc_key, $text, 0, $this->mc_ttl);
			}
			else {
				$this->logCacheHit();
				$xmlobj = simplexml_load_string($text);
			}

			return $xmlobj->photosets;
		}


		/**
		 * Return 'photoset' node describing the photoset (title and description)
		 * http://www.flickr.com/services/api/flickr.photosets.getInfo.html
		 *
		 * @param $photoset_id Photoset ID
		 *
		 * @return XML-node
		 */
		function getPhotosetXML($photoset_id, $offset = 0, $limit = 0) {
			assert($this->nsid !== FALSE);

			// Hämta lista på galleri
			$mc_key = $this->mc_prefix ."photosets:$photoset_id";
			if(!$this->mc || $this->flush_cache || ($text = $this->mc->get($mc_key)) === FALSE) {
				$this->logCacheMiss();
				$xmlobj = $this->apicall('flickr.photosets.getInfo',
								array(	"api_key" => $this->apikey,
									"photoset_id" => $photoset_id
								)
				);

				if($xmlobj === FALSE)
					return FALSE;

				$text = $xmlobj->asXML();
				if($this->mc)
					$this->mc->set($mc_key, $text, 0, $this->mc_ttl);
			}
			else {
				$this->logCacheHit();
				$xmlobj = simplexml_load_string($text);
			}


			return $xmlobj->photoset;
		}


		// http://www.flickr.com/services/api/flickr.photos.search.html
		// Return 'photos' node with 'photo' children
		function searchPhotosByMachineTags($mt, $privacy_filter = 1) {
			assert($this->nsid !== FALSE);
			assert($privacy_filter >= 1 && $privacy_filter <= 5);

			// Sort tags by name and get rid of whitespace
			$tags = explode(",", $mt);
			foreach($tags as $tag)
				$tag = trim($tag);
			sort($tags);
			$mt = implode(",", $tags);


			// Gör sökning
			$mc_key = $this->mc_prefix ."machinetags:$mt";
			if(!$this->mc || $this->flush_cache || ($text = $this->mc->get($mc_key)) === FALSE) {
				$this->logCacheMiss();
				$xmlobj = $this->apicall('flickr.photos.search',
								array(  "api_key" => $this->apikey,
									"machine_tags" => $mt,
									"privacy_filter" => $privacy_filter,
									"media" => "photos"
								)
				);

				if($xmlobj === FALSE)
					return FALSE;

				$text = $xmlobj->asXML();
				if($this->mc)
					$this->mc->set($mc_key, $text, 0, $this->mc_ttl);
			}
			else {
				$this->logCacheHit();
				$xmlobj = simplexml_load_string($text);
			}


			return $xmlobj->photos;
		}



		/**
		 * Return 'photoset' node with 'photo' children
		 * http://www.flickr.com/services/api/flickr.photosets.getPhotos.html
		 *
		 * @param $photoset_id Photoset ID
		 * @param $page OPTIONAL: Page (1..N)
		 * @param $per_page OPTIONAL: Photos per page (1..500)
		 *
		 * @return XML-node
		 */
		function getPhotosXML($photoset_id, $per_page = 500, $page = 1) {
			assert($this->nsid !== FALSE);

			// Hämta lista på galleri
			$mc_key = $this->mc_prefix ."photos:$photoset_id:$per_page:$page";
			if(!$this->mc || $this->flush_cache || ($text = $this->mc->get($mc_key)) === FALSE) {
				$this->logCacheMiss();
				$xmlobj = $this->apicall('flickr.photosets.getPhotos',
								array(	"api_key"	=> $this->apikey,
									"photoset_id"	=> $photoset_id,
									"extras"	=> "date_upload,date_taken,last_update",
									"privacy_filter"=> 1, /* only public photos */
									'per_page' => $per_page,
									'page' => $page
								)
				);

				if($xmlobj === FALSE)
					return FALSE;

				$text = $xmlobj->asXML();
				if($this->mc)
					$this->mc->set($mc_key, $text, 0, $this->mc_ttl);
			}
			else {
				$this->logCacheHit();
				$xmlobj = simplexml_load_string($text);
			}


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


		// Return URL of a photo (argument is a SimpleXMLElement object)
		// Size can be '' (500), 's' for small (75), 't' for thumb (100)
		function getPhotoURL($photo, $size = "") {
			assert(is_object($photo));
			assert(isset($photo["farm"]));
			assert(isset($photo["server"]));
			assert(isset($photo["id"]));
			assert(isset($photo["secret"]));

			$url = sprintf("http://farm%d.static.flickr.com/%d/%s_%s%s.jpg",
					$photo["farm"], $photo["server"],
					$photo["id"], $photo["secret"], 
					(empty($size)? "": "_$size"));

			return $url;
		}


		function getPhotosetContext($photoset_id, $photo_id) {
			// Hämta lista på galleri
			$mc_key = $this->mc_prefix ."photoset:context:$photoset_id:$photo_id";
			if(!$this->mc || $this->flush_cache || ($text = $this->mc->get($mc_key)) === FALSE) {
				$this->logCacheMiss();
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
					$this->mc->set($mc_key, $text, 0, $this->mc_ttl);
			}
			else {
				$this->logCacheHit();
				$xmlobj = simplexml_load_string($text);
			}

			return $xmlobj;
		}


		// http://www.flickr.com/services/api/flickr.photos.getInfo.html
		// Returns 'photo' node with detailed information about the photo
		function getPhotoInfo($photo_id, $secret = FALSE) {
			$mc_key = $this->mc_prefix ."photo:info:$photo_id";
			if(!$this->mc || $this->flush_cache || ($text = $this->mc->get($mc_key)) === FALSE) {
				$this->logCacheMiss();

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
					$this->mc->set($mc_key, $text, 0, $this->mc_ttl);
			}
			else {
				$this->logCacheHit();
				$xmlobj = simplexml_load_string($text);
			}

			return $xmlobj->photo;
		}
	}
?>
