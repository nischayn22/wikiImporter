<?php
/**
 * @author Nischay Nahata <nischayn22@gmail.com>
 * @license GPL v2 or later
 */

error_reporting( E_ALL );
ini_set( 'display_errors', 1 );


include( 'settings.php' );
$settings['cookiefile'] = "cookies.tmp";
include( 'helperfunctions.php' );

echo "Logging In to Public Wiki\n";

try {
	global $settings;
	$token = login($settings['publicwiki'],$settings['user'], $settings['pass']);
	login($settings['publicwiki'],$settings['user'], $settings['pass'], $token);
	echo ("SUCCESS\n");
} catch (Exception $e) {
	die("FAILED: " . $e->getMessage() . "\n");
}

echo "Starting to delete pages one by one in public wiki, all images will be also deleted. \n";

$url = $settings['publicwiki'] . "/api.php?action=query&list=allpages&format=xml&aplimit=10000"; // Hope this limit is enough large that we don't have the trouble to do this again and again using 'continue'

$data = httpRequest($url, $params = '');
$xml = simplexml_load_string($data);
//Check for successful login
$expr = "/api/query/allpages/p";
$result = $xml->xpath($expr);
foreach( $result as $page ) {
	deletepage( (string)$page['title'] );
}
// Bad design just copying code because File: are not returned above
$url = $settings['publicwiki'] . "/api.php?action=query&list=allpages&format=xml&apnamespace=6&aplimit=10000"; // Hope this limit is enough large that we don't have the trouble to do this again and again using 'continue'
$data = httpRequest($url, $params = '');
$xml = simplexml_load_string($data);
//Check for successful login
$expr = "/api/query/allpages/p";
$result = $xml->xpath($expr);
foreach( $result as $page ) {
	deletepage( (string)$page['title'] );
}
//all deletion done now :)

//copy pages
//now login to other wiki
echo "Logging into private wiki now\n";
try {
	global $settings;
	$token = login($settings['privatewiki'],$settings['user'], $settings['pass']);
	login($settings['privatewiki'],$settings['user'], $settings['pass'], $token);
	echo ("SUCCESS<br>");
} catch (Exception $e) {
	die("FAILED: " . $e->getMessage() . "<br>");
}

echo "Now starting to import pages, categories and file-pages from the given file\n";

//get pagenames from file
$pages = file($settings['file'], FILE_IGNORE_NEW_LINES);

//get token first
$url = $settings['publicwiki'] . "/api.php?format=xml&action=query&titles=Main_Page&prop=info|revisions&intoken=edit";
$data = httpRequest($url, $params = '');
$xml = simplexml_load_string($data);
$editToken = urlencode( (string)$xml->query->pages->page['edittoken'] );
foreach($pages as $pageName) {
	copypage( $pageName, $editToken );
}
