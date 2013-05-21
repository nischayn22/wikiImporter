<?php
/**
 * @author Nischay Nahata <nischayn22@gmail.com>
 * @license GPL v2 or later
 */

error_reporting( E_STRICT );


include( 'settings.php' );
$settings['cookiefile'] = "cookies.tmp";
include( 'helperfunctions.php' );

echo "Logging in to public wiki\n";

try {
	global $settings;
	$token = login($settings['publicWiki'],$settings['publicWikiUser'], $settings['publicWikiPassword']);
	login($settings['publicWiki'],$settings['publicWikiUser'], $settings['publicWikiPassword'], $token);
	echo ("SUCCESS\n");
} catch (Exception $e) {
	die("FAILED: " . $e->getMessage() . "\n");
}
//get token first
$url = $settings['publicWiki'] . "/api.php?format=xml&action=query&titles=Main_Page&prop=info|revisions&intoken=edit";
$data = httpRequest($url, $params = '');
$xml = simplexml_load_string($data);
$editToken = urlencode( (string)$xml->query->pages->page['edittoken'] );

echo "Starting to delete pages one by one in public wiki... \n";
//get pagenames that shouldn't be deleted
$doNotDeletePages = file( $settings['doNotDeletePages'], FILE_IGNORE_NEW_LINES );

for( $i=0; $i<15; $i++ ) {

	// Skip files or not
	if( $i == 6 && !$settings['deleteFiles'] ) {
		continue;
	}

	$url = $settings['publicWiki'] . "/api.php?action=query&list=allpages&format=xml&apnamespace=$i&aplimit=10000"; // Hope this limit is enough large that we don't have the trouble to do this again and again using 'continue'
	$data = httpRequest($url, $params = '');
	$xml = simplexml_load_string($data);
	$expr = "/api/query/allpages/p";
	$result = $xml->xpath($expr);
	foreach( $result as $page ) {
		if( !in_array( (string)$page['title'], $doNotDeletePages ) ) {
			echo "Deleting page ". (string)$page['title'] . "\n";
			deletepage( (string)$page['pageid'], $editToken );
		} else {
			echo "Skipping page ". (string)$page['title'] . "\n";
		}
	}
}
//all deletion done now :)

//copy pages
//now login to other wiki
echo "Logging into private wiki now\n";
try {
	global $settings;
	$token = login($settings['privateWiki'],$settings['privateWikiUser'], $settings['privateWikiPassword']);
	login($settings['privateWiki'],$settings['privateWikiUser'], $settings['privateWikiPassword'], $token);
	echo ("SUCCESS\n");
} catch (Exception $e) {
	die("FAILED: " . $e->getMessage() . "\n");
}

echo "Starting to import pages, categories and files...\n";

//get pagenames from file
$pages = file($settings['file'], FILE_IGNORE_NEW_LINES);

foreach($pages as $pageName) {
	copypage( $pageName, $editToken );
}

echo "All done. Now you can import the images into the public wiki using importImages.php\n";