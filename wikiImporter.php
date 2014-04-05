<?php
/**
 * @author Nischay Nahata <nischayn22@gmail.com>
 * @license GPL v2 or later
 */

error_reporting( E_STRICT );


include( 'settings.php' );
$settings['cookiefile'] = "cookies.tmp";

include 'MediaWiki_Api\MediaWiki_Api_functions.php';
include( 'helperfunctions.php' );

$publicApi = new MediaWikiApi($settings['publicWiki']);
echo "Logging in to public wiki\n";
$publicApi->login($settings['publicWikiUser'], $settings['publicWikiPassword']);

echo "Starting to delete pages one by one in public wiki... \n";

//get pagenames that shouldn't be deleted
$doNotDeletePages = file( $settings['doNotDeletePages'], FILE_IGNORE_NEW_LINES );

for( $i=0; $i<15; $i++ ) {

	// Skip files or not
	if( $i == 6 && !$settings['deleteFiles'] ) {
		continue;
	}

	$result = $publicApi->listPageInNamespace($i);
	foreach( $result as $page ) {
		if( !in_array( (string)$page['title'], $doNotDeletePages ) ) {
			echo "Deleting page ". (string)$page['title'] . "\n";
			$publicApi->deleteById((string)$page['pageid']);
		} else {
			echo "Skipping page ". (string)$page['title'] . "\n";
		}
	}
}

//all deletion done now :)

//copy pages

$privateApi = new MediaWikiApi($settings['privateWiki']);

echo "Logging into private wiki now\n";
$privateApi->login($settings['privateWikiUser'], $settings['privateWikiPassword']);

echo "Starting to import pages, categories and files...\n";

//get pagenames from file
$pages = file($settings['file'], FILE_IGNORE_NEW_LINES);

foreach($pages as $pageName) {
	copypage( $pageName, false );
}

echo "All done. Now you can import the images into the public wiki using importImages.php\n";