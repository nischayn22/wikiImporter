<?php
/**
 * @author Nischay Nahata <nischayn22@gmail.com>
 * @license GPL v2 or later
 */

function httpRequest($url, $post="") {
	global $settings;

	$ch = curl_init();
	//Change the user agent below suitably
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.9) Gecko/20071025 Firefox/2.0.0.9');
	curl_setopt($ch, CURLOPT_URL, ($url));
	curl_setopt( $ch, CURLOPT_ENCODING, "UTF-8" );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt ($ch, CURLOPT_COOKIEFILE, $settings['cookiefile']);
	curl_setopt ($ch, CURLOPT_COOKIEJAR, $settings['cookiefile']);
	if (!empty($post)) curl_setopt($ch,CURLOPT_POSTFIELDS,$post);
	//UNCOMMENT TO DEBUG TO output.tmp
	//curl_setopt($ch, CURLOPT_VERBOSE, true); // Display communication with server
	//$fp = fopen("output.tmp", "w");
	//curl_setopt($ch, CURLOPT_STDERR, $fp); // Display communication with server
	
	$xml = curl_exec($ch);
	
	if (!$xml) {
			throw new Exception("Error getting data from server ($url): " . curl_error($ch));
	}

	curl_close($ch);
	
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

function deletepage( $pageName ) {
        global $settings;

		//get delete token
        $url = $settings['publicwiki'] . "/api.php?action=query&prop=info&intoken=delete&titles=$pageName&format=xml";
        $data = httpRequest($url, $params = '');
        $xml = simplexml_load_string($data);
		$expr = "/api/query/pages/page";
		$result = $xml->xpath($expr);
		if( isset( $result[0] ) ) { // should skip Main Page
			$deleteToken = urlencode((string)($result[0]['deletetoken']));
			//now delete stuff
			echo "deleting page $pageName \n";
			$url = $settings['publicwiki'] . "/api.php?action=delete&format=xml";
			$params = "action=delete&title=$pageName&token=$deleteToken";
			$data = httpRequest($url, $params);
			$xml = simplexml_load_string($data);
		}else{
			echo "Unable to delete $pageName\n";
			//TODO show reasons here
		}
}

function copypage( $pageName, $editToken ) {
	global $settings;

	// Get Namespace
	$parts = explode( ':', $pageName );
	echo "Copying over $pageName\n";
	$url = $settings['privatewiki'] . "/api.php?format=xml&action=query&titles=$pageName&prop=revisions&rvprop=content";
	$data = httpRequest($url, $params = '');
	$xml = simplexml_load_string($data);
	$content = (string)$xml->query->pages->page->revisions->rev;

	//Now copy this to other wiki
	$timestamp = (string)$xml->query->pages->page->revisions->rev['timestamp'];

	if( count( $parts ) === 2 && $parts[0] === 'File') { // files are handled here
		$url = $settings['privatewiki'] . "/api.php?action=query&titles=$pageName&prop=imageinfo&iiprop=url&format=xml";
		$data = httpRequest($url, $params = '');
		$xml = simplexml_load_string($data);
		//fetch category pages and call them recursively
		$expr = "/api/query/pages/page/imageinfo/ii";
		$fileUrl = urlencode( (string)$xml->xpath($expr)[0]['url']);
		$url = $settings['publicwiki'] . "/api.php?action=upload&filename=$parts[1]&text=$content&url=$fileUrl&format=xml&ignorewarnings=1";
		$data = httpRequest($url, $params = "&token=$editToken");
		return;
	}
	// now copy normal page
	$url = $settings['publicwiki'] . "/api.php?format=xml&action=edit&title=$pageName&text=$content";
	$data = httpRequest($url, $params = "format=xml&action=edit&title=$pageName&text=$content&token=$editToken");
	$xml = simplexml_load_string($data);
	// TODO: get status to display

	// Now copy category members too
	if( count( $parts ) === 2 && $parts[0] === 'Category') {
		$url = $settings['privatewiki'] . "/api.php?format=xml&action=query&cmtitle=$pageName&list=categorymembers&cmlimit=10000";
		$data = httpRequest($url, $params = '');
		$xml = simplexml_load_string($data);
		//fetch category pages and call them recursively
		$expr = "/api/query/categorymembers/cm";
		$result = $xml->xpath($expr);
		foreach( $result as $page ) {
			copypage( (string)$page['title'] );
		}
	}

}