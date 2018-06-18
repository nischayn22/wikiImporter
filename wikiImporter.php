<?php
/**
 * @author Nischay Nahata <nischayn22@gmail.com>
 * @license GPL v2 or later
 */

error_reporting( E_STRICT );
//UNCOMMENT THIS TO SHOW ALL PHP ERRORS
//error_reporting ( E_ALL );

# Includes the autoloader for libraries installed with composer
require __DIR__ . '/vendor/autoload.php';

include( 'settings.php' );
$settings['cookiefile'] = "cookies.tmp";

include 'MediaWiki_Api/MediaWiki_Api_functions.php';
include( 'helperfunctions.php' );

$publicApi = new MediaWikiApi($settings['publicWiki']);
echo "Logging in to public wiki\n";
$publicApi->logout();
$publicApi->login($settings['publicWikiUser'], $settings['publicWikiPassword']);



if( $settings['delete'] ) {

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
} else {
	echo "Not deleting images in the public wiki... \n";
}

//copy pages

$privateApi = new MediaWikiApi($settings['privateWiki']);

echo "Logging into private wiki now\n";
$privateApi->logout();
$privateApi->login($settings['privateWikiUser'], $settings['privateWikiPassword']);

echo "Starting to import pages, categories and files...\n";

//get pagenames from file
$pages = file($settings['copyPages'], FILE_IGNORE_NEW_LINES);

foreach($pages as $pageName) {
	copypage( $pageName, false );
}

$publicApi->logout();
$privateApi->logout();

echo "All done.\n";
