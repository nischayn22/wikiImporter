<?php
/**
 * @author Nischay Nahata <nischayn22@gmail.com>
 * @license GPL v2 or later
 */

function httpRequest($url, $post="", $retry = false, $retryNumber = 0) {
	global $settings;

	try {
		$ch = curl_init();
		//Change the user agent below suitably
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.9) Gecko/20071025 Firefox/2.0.0.9');
		if( $settings['serverAuth'] ) {
			curl_setopt($ch, CURLOPT_USERPWD, $settings['AuthUsername'] . ":" . $settings['AuthPassword']);
		}
		curl_setopt($ch, CURLOPT_URL, ($url));
		curl_setopt( $ch, CURLOPT_ENCODING, "UTF-8" );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $settings['cookiefile']);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $settings['cookiefile']);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		if (!empty($post)) curl_setopt($ch,CURLOPT_POSTFIELDS,$post);
		//UNCOMMENT TO DEBUG TO output.tmp
		//curl_setopt($ch, CURLOPT_VERBOSE, true); // Display communication with server
		//$fp = fopen("output.tmp", "w");
		//curl_setopt($ch, CURLOPT_STDERR, $fp); // Display communication with server

		$xml = curl_exec($ch);

		if (!$xml) {
			throw new Exception("Error getting data from server: " . curl_error($ch));
		}

		curl_close($ch);
	} catch( Exception $e ) {
		echo 'Caught exception: ',  $e->getMessage(), "\n";
		if( !$retry && $retryNumber <3 ) {
			echo "Retrying \n";
			httpRequest($url, $post, true, $retryNumber++ );
		} else {
			echo "Could not perform action after 3 attempts. Skipping now...\n";
			return null;
		}
	}
	return $xml;
}


function login ( $site, $user, $pass, $token='') {

	$url = $site . "/api.php?action=login&format=xml";

	$params = "action=login&lgname=$user&lgpassword=$pass";
	if (!empty($token)) {
		$params .= "&lgtoken=$token";
	}

	$data = httpRequest($url, $params);

	if (empty($data)) {
		throw new Exception("No data received from server. Check that API is enabled.");
	}

	$xml = simplexml_load_string($data);
	if (!empty($token)) {
		//Check for successful login
		$expr = "/api/login[@result='Success']";
		$result = $xml->xpath($expr);

		if(!count($result)) {
			throw new Exception("Login failed");
		}
	} else {
		$expr = "/api/login[@token]";
		$result = $xml->xpath($expr);

		if(!count($result)) {
			throw new Exception("Login token not found in XML");
		}
	}

	return $result[0]->attributes()->token;
}

function deletepage( $pageid, $deleteToken ) {
	global $settings;

	$url = $settings['publicWiki'] . "/api.php?action=delete&format=xml";
	$params = "action=delete&pageid=$pageid&token=$deleteToken&reason=Outdated";
	httpRequest($url, $params);
	// Nothing to do with response currently
	// $data = httpRequest($url, $params);
	// $xml = simplexml_load_string($data);
}

function copypage( $pageName, $editToken, $recursivelyCalled = true ) {
	global $settings;

	echo "Copying over $pageName\n";
	$pageName = str_replace( ' ', '_', $pageName );
	// Get Namespace
	$parts = explode( ':', $pageName );
	$url = $settings['privateWiki'] . "/api.php?format=xml&action=query&titles=$pageName&prop=revisions&rvprop=content";
	$data = httpRequest($url, $params = '');
	$xml = simplexml_load_string($data);
	errorHandler( $xml );
	$content = (string)$xml->query->pages->page->revisions->rev;
	$timestamp = (string)$xml->query->pages->page->revisions->rev['timestamp'];

	if( count( $parts ) === 2 && $parts[0] === 'File') { // files are handled here
		$url = $settings['privateWiki'] . "/api.php?action=query&titles=$pageName&prop=imageinfo&iiprop=url&format=xml";
		$data = httpRequest($url, $params = '');
		$xml = simplexml_load_string($data);
		$expr = "/api/query/pages/page/imageinfo/ii";
		$imageInfo = $xml->xpath($expr);
		$rawFileURL = $imageInfo[0]['url'];
		$rawFileURL = (string) $imageInfo[0]['url'];
		echo "Downloading file " . $parts[1] . " \n";

		if ( !is_dir( $settings['imagesDirectory'] ) ) {
			// dir doesn't exist, make it
			echo "Creating directory " . $settings['imagesDirectory'] . "\n";
			mkdir( $settings['imagesDirectory'] );
		}
		$result = download( $rawFileURL, $settings['imagesDirectory'] . "/" . $parts[1] );

		if ( !$result ) {
			echo "Download error...Check if file exists and is usable \n";
			// write to file that copy failed
			echo "logging in failed_pages.txt \n";
			file_put_contents( 'failed_pages.txt' , $pageName . "\n", FILE_APPEND );
		} else {
			echo "File download successfully \n";
		}
		return;
	}

	// now copy normal page
	$content = urlencode( $content );
	$url = $settings['publicWiki'] . "/api.php?format=xml&action=edit&title=$pageName&text=$content";
	$data = httpRequest($url, $params = "format=xml&action=edit&title=$pageName&text=$content&token=$editToken");
	if ( $data == null ) {
		// write to file that copy failed
		echo "logging page name in failed_pages.txt\n";
		file_put_contents( 'failed_pages.txt' , $pageName . "\n", FILE_APPEND );
	}

	$xml = simplexml_load_string($data);
	errorHandler( $xml );

	// Now import images linked on the page
	echo "Finding file links in $pageName ...\n";
	$url = $settings['privateWiki'] . "/api.php?format=xml&action=query&prop=images&titles=$pageName&imlimit=1000";
	$data = httpRequest( $url );
	$xml = simplexml_load_string($data);
	errorHandler( $xml );
	//fetch image Links and copy them as well
	$expr = "/api/query/pages/page/images/im";
	$result = $xml->xpath($expr);
	if( $result ) {
		foreach( $result as $image ) {
			echo "Link found to " . (string)$image['title'] . " \n";
			copypage( (string)$image['title'], $editToken );
		}
	} else {
		echo "No file links found\n";
	}

	// Now copy category members too
	if( count( $parts ) === 2 && $parts[0] === 'Category' ) {
		if( !$settings['recurseCategories'] && $recursivelyCalled ) {
			return;
		}
		$url = $settings['privateWiki'] . "/api.php?format=xml&action=query&cmtitle=$pageName&list=categorymembers&cmlimit=10000";
		$data = httpRequest($url, $params = '');
		$xml = simplexml_load_string($data);
		errorHandler( $xml );
		//fetch category pages and call them recursively
		$expr = "/api/query/categorymembers/cm";
		$result = $xml->xpath($expr);
		foreach( $result as $page ) {
			copypage( (string)$page['title'], $editToken );
		}
	}
}

function errorHandler( $xml ){
	if( property_exists( $xml, 'error' ) ) {
		$errors = is_array( $xml->error )? $xml->error : array( $xml->error );
		foreach( $errors as $error ) {
			echo "Error code: " . $error['code'] . " " . $error['info'] . "\n";
		}
	}
}

function download($url, $file_target) {
	global $settings;
	$fp = fopen ( $file_target, 'w+');//This is the file where we save the information
	$ch = curl_init(str_replace(" ","%20",$url));//Here is the file we are downloading, replace spaces with %20
	if( $settings['serverAuth'] ) {
		curl_setopt($ch, CURLOPT_USERPWD, $settings['AuthUsername'] . ":" . $settings['AuthPassword']);
	}
	curl_setopt($ch, CURLOPT_URL, ($url));
//	curl_setopt( $ch, CURLOPT_ENCODING, "UTF-8" );
//	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $settings['cookiefile']);
	curl_setopt($ch, CURLOPT_COOKIEJAR, $settings['cookiefile']);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_TIMEOUT, 50);
	curl_setopt($ch, CURLOPT_FILE, $fp); // write curl response to file
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	$xml = curl_exec($ch); // get curl response
	curl_close($ch);
	fclose($fp);
	return $xml;
}