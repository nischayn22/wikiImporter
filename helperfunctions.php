<?php
/**
 * @author Nischay Nahata <nischayn22@gmail.com>
 * @license GPL v2 or later
 */

function copypage( $pageName, $recursivelyCalled = true ) {
	global $settings, $publicApi, $privateApi;

	echo "Copying over $pageName\n";
	// Get Namespace
	$parts = explode( ':', $pageName );
	$content = $publicApi->readPage($pageName);

        if ( empty($content) ) {
	    // write to file that reading failed
	    echo "Page read error...Check if page exists and is accessible \n";
            echo "logging page name in failed_pages.txt\n";
            file_put_contents( 'failed_pages.txt' , $pageName . "\n", FILE_APPEND );
	    return;
        }

	if( count( $parts ) === 2 && $parts[0] === 'File') { // files are handled here
	    $rawFileUrl = $privateApi->getFileUrl($pageName);
		echo "Downloading file " . $parts[1] . " \n";

		if ( !is_dir( $settings['imagesDirectory'] ) ) {
			// dir doesn't exist, make it
			echo "Creating directory " . $settings['imagesDirectory'] . "\n";
			mkdir( $settings['imagesDirectory'] );
		}
		$result = download( $rawFileUrl, $settings['imagesDirectory'] . "/" . $parts[1] );

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
	if ($settings['create'])
	{
		$data = $publicApi->createPage($pageName, $content);
	} else {
		$data = $publicApi->editPage($pageName, $content);
	}
	if ( $data == null ) {
		// write to file that copy failed
		echo "logging page name in failed_pages.txt\n";
		file_put_contents( 'failed_pages.txt' , $pageName . "\n", FILE_APPEND );
	}

	// Now import images linked on the page
	echo "Finding file links in $pageName ...\n";
	$result = $privateApi->listImagesOnPage($pageName);
	if( $result ) {
		foreach( $result as $image ) {
			echo "Link found to " . (string)$image['title'] . " \n";
			copypage( (string)$image['title'] );
		}
	} else {
		echo "No file links found\n";
	}

	// Now copy category members too
	if( count( $parts ) === 2 && $parts[0] === 'Category' ) {
		if( !$settings['recurseCategories'] && $recursivelyCalled ) {
			return;
		}
		$result = $privateApi->listPageInCategory($pageName);
		foreach( $result as $page ) {
			copypage( (string)$page['title'] );
		}
	}
}

