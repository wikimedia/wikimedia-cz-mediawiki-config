<?php

## ======================================================================
## Tento soubor obsahuje logiku pro přepínání konfigurace.
## ======================================================================

# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
		exit;
}

if ( defined( 'MW_DB' ) ) {
  $wikiId = MW_DB;
}
elseif (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] == 'wiki.wikimedia.cz') {
  $wikiId = 'inner_wiki';
}
elseif (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] == 'www.wikimedia.cz') {
  $wikiId = 'pub_wiki';
}
elseif (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] == 'tech.wikimedia.cz') {
  $wikiId = 'tech_wiki';
} else {
    // Fail gracefully if no value was set to the $wikiId variable, i.e. if no wiki was determined
    die( 'It was not possible to determine the wiki ID.' );
}

require_once("/var/www/mediawiki-config/CommonSettings.php");
require_once("/var/www/mediawiki-config/${wikiId}_private.php");
require_once("/var/www/mediawiki-config/$wikiId.php");
