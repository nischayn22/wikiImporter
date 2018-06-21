<?php


// Settings for public wiki

$settings['publicWiki'] = "http://localhost/test"; // Location of Api.php
$settings['publicWikiUser'] = "Nischayn22"; // Username of account with read,write permissions
$settings['publicWikiPassword'] = "RajatNischay12"; // Password


// Settings for private wiki

$settings['privateWiki'] = "http://localhost/testnew"; // Location of Api.php
$settings['privateWikiUser'] = "Nischayn22"; // Username of account with read permissions
$settings['privateWikiPassword'] = "RajatNischay12"; // Password

// Settings for Google Translate
$settings['enableTranslate'] = false;
$settings['GOOGLE_TRANSLATE_PROJECT_ID'] = 'steel-paratext-205412';
$settings['lang_to'] = 'en';



// Settings for Basic HTTP Auth
$settings['serverAuth'] = false;
$settings['AuthUsername'] = 'nischay';
$settings['AuthPassword'] = 'password';


// File containing list of pages to be copied over, separated by newlines
$settings['copyPages'] = "clientPages.txt";

// Whether to delete files or not
$settings['deleteFiles'] = false;

// folder to store files downloaded from private wiki; just specify the name here and the folder will be automatically created.
$settings['imagesDirectory'] = 'images';

// File containing list of pages to be never deleted, separated by newlines
$settings['doNotDeletePages'] = "doNotDelete.txt";

// Whether to handle categories recursively
$settings['recurseCategories'] = false;

// Whether to delete anything
$settings['delete'] = false;

// Whether to only create new pages, otherwise we will edit pages and override stuff
$settings['create'] = false;

// Whether to copy over images found in wikitext
$settings['copy_images'] = false;
