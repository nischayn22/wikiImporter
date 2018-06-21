# Description
This is a utility script that selectively updates a wiki using another wiki's content,
i.e. if you have a public and a private wiki where the public wiki has *only* partial contents of the private wiki,
this script can selectively fetch pages/images from your private wiki and import them to your public wiki.
The script can also copy over entire namespaces.

Apart from this the script can translate wikitext from one language to another as well before importing.

# Installation -

	Download this repo
	Install Composer
	Install dependencies using:
    composer update


# Usage -
Just run this script after setting the settings (below).

## Please set the settings in settings.php to point to your wikis
Example settings -

$settings['publicWiki'] = "http://myPublic.com/w"; // Location of Api.php
$settings['publicWikiUser'] = "Nischay Nahata"; // Username of account with read,write permissions
$settings['publicWikiPassword'] = "password"; // Password

$settings['privateWiki'] = "http://myPrivate.com/w"; // Location of Api.php
$settings['privateWikiUser'] = "Nischay Nahata"; // Username of account with read permissions
$settings['privateWikiPassword'] = "password"; // Password

Please provide all kinds of permissions to the user accounts above including apihighlimits (http://www.mediawiki.org/wiki/API:Query_-_Lists#Limits) for proper functioning.


## Settings for Google Translate
$settings['enableTranslate'] = true;
$settings['GOOGLE_TRANSLATE_PROJECT_ID'] = 'YOUR-PROJECTID-XXXXXX';
$settings['lang_to'] = 'en';



## If you need to authenticate against a server use following settings
// Settings for Basic HTTP Auth
$global['serverAuth'] = true;
$settings['AuthUsername'] = 'nischay';
$settings['AuthPassword'] = 'password';

## To not delete files set
$settings['deleteFiles'] = false;

## Specify file containing list of pages to be copied
$settings['copyPages'] = "clientPages.txt";

# Example -
file named clientPages.txt has the following text:

Hello World
Category:Id
File:Passportpics.jpg

## To copy entire namespace specify namespace number as per [Namespaces](https://www.mediawiki.org/wiki/Manual:Namespace)
0
--

# Usage
Run the script using the following command "php wikiImporter.php"

# Authors
wikiImporter was originally written by Nischay Nahata, as http://www.WikiWorks.com consultant for Aruba Networks
Updated for MediaWiki 1.27 by Christoph Zimmermann from the [Public Domain Project](https://publicdomainproject.org)

# License
GPL v2 or later
