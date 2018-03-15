<?php


// Settings for public wiki

$settings['publicWiki'] = "http://myPublic.com/w"; // Location of Api.php
$settings['publicWikiUser'] = "Nischay Nahata"; // Username of account with read,write permissions
$settings['publicWikiPassword'] = "password"; // Password


// Settings for private wiki

$settings['privateWiki'] = "http://myPrivate.com/w"; // Location of Api.php
$settings['privateWikiUser'] = "Nischay Nahata"; // Username of account with read permissions
$settings['privateWikiPassword'] = "password"; // Password

// Settings for Basic HTTP Auth
$settings['serverAuth'] = true;
$settings['AuthUsername'] = 'nischay';
$settings['AuthPassword'] = 'password';


// File containing list of pages to be copied over, separated by newlines
$settings['file'] = "clientPages.txt";

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
$settings['copy_images'] = true;
