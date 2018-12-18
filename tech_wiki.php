<?php

## ======================================================================
## Tento soubor obsahuje konkrétní konfiguraci pro tech_wiki
## ======================================================================

# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
                exit;
}

## ======================================================================
## Identity
## ======================================================================
$wgSitename         = "Tech Wiki - WMCZ";
$wgArticlePath      = "/wiki/$1";
$wgLanguageCode = 'en';

## ======================================================================
## Skins
## ======================================================================
$wgDefaultSkin = 'vector';

## ======================================================================
## Namespaces
## ======================================================================
$wgNamespacesWithSubpages = array(
	-1=>false,
	 0=>true,  1=>true,
	 2=>true,  3=>true,
	 4=>true,  5=>true,
	 6=>false, 7=>true,
	 8=>true,  9=>true,
	10=>true,  11=>true,
	12=>true,  13=>true,
	14=>false, 15=>true
);

## ======================================
## Misc
## ======================================
$wgSharedTables = []; // No shared tables

## ======================================================================
## Upgrade stuff
## ======================================================================
#$wgReadOnly = 'This wiki is currently being upgraded to a newer software version. If you need anything, please contact the sysadmin at martin.urbanec@wikimedia.cz.';
