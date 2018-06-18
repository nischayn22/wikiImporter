<?php
/**
 * @author Nischay Nahata <nischayn22@gmail.com>
 * @license GPL v2 or later
 */

# Imports the Google Cloud client library
use Google\Cloud\Translate\TranslateClient;

function copypage( $pageName, $recursivelyCalled = true ) {
	global $settings, $publicApi, $privateApi;

	// Check if entire namespace is specified
	if ( is_numeric( $pageName ) ) {
		$result = $privateApi->listPageInNamespace( $pageName );
		foreach( $result as $page ) {
			copypage( (string)$page['title'] );
		}
		return;
	}

	echo "Copying over $pageName\n";

	// Get Namespace
	$parts = explode( ':', $pageName );
	$content = $privateApi->readPage($pageName);

	if ( $settings['enableTranslate'] ) {
		$content = translateWikiText( $content );
	}

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
	if ($settings['create']) {
		$data = $publicApi->createPage($pageName, $content);
	} else {
		$data = $publicApi->editPage($pageName, $content);
	}

	if ( $data == null ) {
		// write to file that copy failed
		echo "logging page name in failed_pages.txt\n";
		file_put_contents( 'failed_pages.txt' , $pageName . "\n", FILE_APPEND );
	}

	if ($settings['copy_images']) {
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
	} else {
		echo "Skipping files\n";
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

// This needs to handle the link as well as the text displayed
function translateInternalLink( $link_str ) {
	$link_parts = explode( '|', $link_str );
	$translated_link = $link_parts[0];

	if ( count( $link_parts ) == 2 ) {
		return $translated_link . '|' . translateText( $link_parts[1] );
	}
	return $translated_link;
}

function translateTemplateContents( $templateContent ) {
	$pos = strpos( $templateContent, '|' );
	$templateName = substr( $templateContent, 0, $pos );
	$templateParametersContent = substr( $templateContent, $pos + 1, strlen( $templateContent ) - ( $pos + 1 ) );

	$translatedTemplateContent = $templateName . '|' . translateWikiText( $templateParametersContent, true );
	return $translatedTemplateContent;
}

function translateText( $text ) {
	global $settings;

	if ( empty( trim( $text ) ) ) {
		return $text;
	}

	$cache_dir = __DIR__ . '/.cache';
	if ( !is_dir( $cache_dir ) ) {
		mkdir( $cache_dir );
		echo "Successfully created cache dir\n";
	}

	// trim text and then join the parts back as Google trims them
	$ltrimmed = ltrim( $text );

	$ltrim = '';
	if ( strlen( $text ) > strlen( $ltrimmed ) ) {
		$ltrim = substr( $text, 0, strlen( $text ) - strlen( $ltrimmed ) );
	}

	$rtrim = '';

	$rtrimmed = trim( $ltrimmed );
	if ( strlen( $ltrimmed ) > strlen( $rtrimmed ) ) {
		$rtrim = substr( $ltrimmed, strlen( $rtrimmed ), strlen( $ltrimmed ) - strlen( $rtrimmed ) );
	}

	$md5 = md5( $rtrimmed );
	$cache_file = $cache_dir . '/' . $md5;

	$ts_now = ( new DateTime('NOW'))->getTimestamp();

	$translated_string = '';
	if ( file_exists( $cache_file ) && $ts_now - filemtime( $cache_file ) < 30 * 86400 ) {
		$translated_string = file_get_contents( $cache_file );
	} else {
		# Your Google Cloud Platform project ID
		$projectId = $settings['GOOGLE_TRANSLATE_PROJECT_ID'];

		$translate = new TranslateClient([
			'projectId' => $projectId
		]);

		# The target language
		$target = $settings['lang_to'];

		# Translates some text into Russian
		$translation = $translate->translate($rtrimmed, [
			'target' => $target,
			'format' => 'text'
		]);

		$translated_string = $translation['text'];
		file_put_contents( $cache_file, $translated_string );
	}
	return $ltrim . $translated_string . $rtrim;
}

// TODO: DISPLAYTITLE, <includeonly>, etc

// $templateContent: true if $content provided is content inside a template and parameter names should not be translated

function translateWikiText( $content, $templateContent = false ) {
	$translated_content = '';

	$len = strlen( $content );
	$curr_str = '';
	$state_deep = 0;
	$state_arr = array( 'CONTENT' );

	for ( $i = 0; $i < $len; $i++ ){

		if ( $content[$i] == "<" && $content[$i+1] == "!" && $state_arr[$state_deep] == 'CONTENT' ) {
			if ( $content[$i+2] == "-" && $content[$i+3] == "-" ) {
				$translated_content .= translateText( $curr_str );
				$curr_str = '';
				$state_arr[] = 'COMMENTBEGIN';
				$state_deep++;
				$i = $i + 3;
				continue;
			}
		}

		if ( $content[$i] == "-" && $content[$i+1] == "-" && $state_arr[$state_deep] == 'COMMENTBEGIN' ) {
			if ( $content[$i+2] == ">" ) {
				$translated_content .=  "<!--" . $curr_str . "-->";
				$curr_str = '';

				array_pop( $state_arr );
				$state_deep--;
				$i = $i + 2;
				continue;
			}
		}

		if ( $content[$i] == "'" && $content[$i+1] == "'" && $state_arr[$state_deep] == 'CONTENT' ) {
			$translated_content .= translateText( $curr_str );
			$curr_str = '';
			if ( $content[$i+2] == "'" && $content[$i+3] == "'" && $content[$i+4] == "'" ) {
				$state_arr[] = 'BOLDITALICBEGIN';
				$state_deep++;
				$i = $i + 4;
				continue;
			} else if ( $content[$i+2] == "'" ) {
				$state_arr[] = 'BOLDBEGIN';
				$state_deep++;
				$i = $i + 2;
				continue;
			} else {
				$state_arr[] = 'ITALICBEGIN';
				$state_deep++;
				$i = $i + 1;
				continue;
			}
		}

		if ( $content[$i] == "'" && $content[$i+1] == "'" && $state_arr[$state_deep] == 'BOLDITALICBEGIN' ) {
			$translated_content .=  "'''''" . translateWikiText( $curr_str ) . "'''''";
			$curr_str = '';

			array_pop( $state_arr );
			$state_deep--;
			$i = $i + 4;
			continue;
		}
		if ( $content[$i] == "'" && $content[$i+1] == "'" && $state_arr[$state_deep] == 'BOLDBEGIN' ) {
			$translated_content .=  "'''" . translateWikiText( $curr_str ) . "'''";
			$curr_str = '';

			array_pop( $state_arr );
			$state_deep--;
			$i = $i + 2;
			continue;
		}
		if ( $content[$i] == "'" && $content[$i+1] == "'" && $state_arr[$state_deep] == 'ITALICBEGIN' ) {
			$translated_content .=  "''" . translateWikiText( $curr_str ) . "''";
			$curr_str = '';

			array_pop( $state_arr );
			$state_deep--;
			$i = $i + 1;
			continue;
		}

		if ( $content[$i] == "=" && $content[$i+1] == "=" && $state_arr[$state_deep] == 'CONTENT' ) {
			$translated_content .= translateText( $curr_str );
			$curr_str = '';

			if ( $content[$i+2] == "=" && $content[$i+3] == "=" && $content[$i+4] == "=" ) {
				$state_arr[] = 'SEC5BEGIN';
				$state_deep++;
				$i = $i + 4;
				continue;
			} else if ( $content[$i+2] == "=" && $content[$i+3] == "=" ) {
				$state_arr[] = 'SEC4BEGIN';
				$state_deep++;
				$i = $i + 3;
				continue;
			} else if ( $content[$i+2] == "=" ) {
				$state_arr[] = 'SEC3BEGIN';
				$state_deep++;
				$i = $i + 2;
				continue;
			} else {
				$state_arr[] = 'SEC2BEGIN';
				$state_deep++;
				$i = $i + 1;
				continue;
			}
		}

		if ( $content[$i] == "=" && $content[$i+1] == "=" && $state_arr[$state_deep] == 'SEC5BEGIN' ) {
			$translated_content .=  "=====" . ucfirst( trim( translateWikiText( $curr_str ) ) ) . "=====";
			$curr_str = '';

			array_pop( $state_arr );
			$state_deep--;
			$i = $i + 4;
			continue;
		}

		if ( $content[$i] == "=" && $content[$i+1] == "=" && $state_arr[$state_deep] == 'SEC4BEGIN' ) {
			$translated_content .=  "====" . ucfirst( trim( translateWikiText( $curr_str ) ) ) . "====";
			$curr_str = '';

			array_pop( $state_arr );
			$state_deep--;
			$i = $i + 3;
			continue;
		}

		if ( $content[$i] == "=" && $content[$i+1] == "=" && $state_arr[$state_deep] == 'SEC3BEGIN' ) {
			$translated_content .=  "===" . ucfirst( trim( translateWikiText( $curr_str ) ) ) . "===";
			$curr_str = '';

			array_pop( $state_arr );
			$state_deep--;
			$i = $i + 2;
			continue;
		}

		if ( $content[$i] == "=" && $content[$i+1] == "=" && $state_arr[$state_deep] == 'SEC2BEGIN' ) {
			$translated_content .=  "==" . ucfirst( trim( translateWikiText( $curr_str ) ) ) . "==";
			$curr_str = '';

			array_pop( $state_arr );
			$state_deep--;
			$i = $i + 1;
			continue;
		}

		if ( $content[$i] == '[' && $state_arr[$state_deep] == 'CONTENT' ) {

			// Translate content accumulated so far
			$translated_content .= translateText( $curr_str );
			$curr_str = '';

			$state_arr[] = 'LINKBEGIN';
			$state_deep++;
			continue;
		}

		// Internal Link Begin
		if ( $content[$i] == '[' && $state_arr[$state_deep] == 'LINKBEGIN' ) {
			array_pop( $state_arr );
			$state_arr[] = 'INTERNALLINKBEGIN';
			continue;
		}

		// External Link End
		// No need to translate
		if ( $content[$i] == ']' && $state_arr[$state_deep] == 'LINKBEGIN' ) {
			array_pop( $state_arr );
			$state_deep--;
			$translated_content .= "[" . $curr_str . "]";
			$curr_str = '';
			continue;
		}

		// Internal Link End
		if ( $content[$i] == ']' && $state_arr[$state_deep] == 'INTERNALLINKBEGIN' ) {
			array_pop( $state_arr );
			$state_arr[] = 'INTERNALLINKEND';
			continue;
		}

		if ( $content[$i] == ']' && $state_arr[$state_deep] == 'INTERNALLINKEND' ) {
			array_pop( $state_arr );
			$state_deep--;
			$translated_content .= "[[" . translateInternalLink( $curr_str ) . "]]";
			$curr_str = '';
			continue;
		}

		if ( $content[$i] == '{' && $state_arr[$state_deep] == 'CONTENT' ) {

			// Translate content accumulated so far
			$translated_content .= translateText( $curr_str );
			$curr_str = '';

			$state_arr[] = 'CURLYBEGIN';
			$state_deep++;
			continue;
		}

		if ( $content[$i] == '{'&& $content[$i+1] == '#' && $state_arr[$state_deep] == 'CURLYBEGIN' ) {
			array_pop( $state_arr );
			$state_arr[] = 'PARSERFUNCBEGIN';
			continue;
		}

		if ( $content[$i] == '{' && $state_arr[$state_deep] == 'CURLYBEGIN' ) {
			array_pop( $state_arr );
			$state_arr[] = 'TEMPLATEBEGIN';
			continue;
		}

		// Handle nested templates
		if ( $content[$i] == '{' && in_array( $state_arr[$state_deep], array( 'PARSERFUNCBEGIN', 'TEMPLATEBEGIN' ) ) ) {
			$state_arr[] = 'NESTEDTEMPLATEBEGIN';
			$state_deep++;
			$curr_str .= $content[$i];
			continue;
		}
		if ( $content[$i] == '{' && $state_arr[$state_deep] == 'NESTEDTEMPLATEBEGIN' ) {
			array_pop( $state_arr );
			$state_arr[] = 'NESTEDTEMPLATE';
			$curr_str .= $content[$i];
			continue;
		}
		if ( $content[$i] == '}' && $state_arr[$state_deep] == 'NESTEDTEMPLATE' ) {
			array_pop( $state_arr );
			$state_arr[] = 'NESTEDTEMPLATEEND';
			$curr_str .= $content[$i];
			continue;
		}
		if ( $content[$i] == '}' && $state_arr[$state_deep] == 'NESTEDTEMPLATEEND' ) {
			array_pop( $state_arr );
			$state_deep--;
			$curr_str .= $content[$i];
			continue;
		}

		if ( $content[$i] == '}' && $state_arr[$state_deep] == 'PARSERFUNCBEGIN' ) {
			array_pop( $state_arr );
			$state_arr[] = 'PARSERFUNCEND';
			continue;
		}

		if ( $content[$i] == '}' && $state_arr[$state_deep] == 'PARSERFUNCEND' ) {
			array_pop( $state_arr );
			$state_deep--;
			$translated_content .= "{{#" . $curr_str . "}}";
			$curr_str = '';
			continue;
		}

		if ( $content[$i] == '}' && $state_arr[$state_deep] == 'TEMPLATEBEGIN' ) {
			array_pop( $state_arr );
			$state_arr[] = 'TEMPLATEEND';
			continue;
		}

		if ( $content[$i] == '}' && $state_arr[$state_deep] == 'TEMPLATEEND' ) {
			array_pop( $state_arr );
			$state_deep--;

			if ( strpos( $curr_str, '|' ) !== false ) {
				$translated_content .= "{{" . translateTemplateContents( $curr_str ) . "}}";
			} else {
				$translated_content .= "{{" . $curr_str . "}}";
			}

			$curr_str = '';
			continue;
		}

		if ( $content[$i] == '_' && $state_arr[$state_deep] == 'CONTENT' ) {
			$state_arr[] = 'UNDERSCBEGIN';
			$state_deep++;
			continue;
		}
		if ( $content[$i] != '_' && $state_arr[$state_deep] == 'UNDERSCBEGIN' ) {
			array_pop( $state_arr );
			$state_deep--;

			// We didn't add this before so add now
			$curr_str .= '_';
		}

		if ( $content[$i] == '_' && $state_arr[$state_deep] == 'UNDERSCBEGIN' ) {
			// Translate content accumulated so far
			$translated_content .= translateText( $curr_str );
			$curr_str = '';

			array_pop( $state_arr );
			$state_arr[] = 'MAGICBEGIN';
			continue;
		}
		if ( $content[$i] == '_' && $state_arr[$state_deep] == 'MAGICBEGIN' ) {
			array_pop( $state_arr );
			$state_arr[] = 'MAGICEND';
		}
		if ( $content[$i] == '_' && $state_arr[$state_deep] == 'MAGICEND' ) {
			array_pop( $state_arr );
			$state_deep--;
			$translated_content .= "__" . $curr_str . "__";
			$curr_str = '';
			continue;
		}

		if ( $templateContent && $state_arr[$state_deep] == 'CONTENT' && in_array( $content[$i], array( '|', '=' ) ) ) {
			if ( $content[$i] == '=' ) { //Its a parameter name of a template
				$translated_content .= $curr_str . '=';
				$curr_str = '';
			} else if ( $content[$i] == '|' ) {
				$translated_content .= translateText( $curr_str ) . '|';
				$curr_str = '';
			}
			continue;
		}

		// Reached here means add it to curr_str
		$curr_str .= $content[$i];
	}
	$translated_content .= translateText( $curr_str );

	return $translated_content;
}
