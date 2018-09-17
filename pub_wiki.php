<?php

## ======================================================================
## Tento soubor obsahuje konkrétní konfiguraci pro pub_wiki
## ======================================================================

# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
		exit;
}


## ======================================================================
## Identity
## ======================================================================
$wgSitename         = "Wikimedia ČR";
$wgArticlePath      = "/web/$1";

## ======================================================================
## Uploads
## ======================================================================
$wgHashedUploadDirectory = false;

## ======================================================================
## Skins
## ======================================================================
$wgDefaultSkin = 'vector';

## ======================================================================
## Permissions
## ======================================================================
// No write privs for anonymous
$wgGroupPermissions['*']['createaccount']   = false;
$wgGroupPermissions['*']['read']            = true;
$wgGroupPermissions['*']['edit']            = false;
$wgGroupPermissions['*']['createpage']      = false;
$wgGroupPermissions['*']['createtalk']      = false;

// No centralized operations here
$wgGroupPermissions['bureaucrat']['userrights'] = false;
$wgGroupPermissions['sysop']['block'] = false;
$wgGroupPermissions['sysop']['createaccount'] = false;
$wgGroupPermissions['user']['createaccount'] = false;

## ======================================================================
## Extensions
## ======================================================================
wfLoadExtension( 'EditSubpages' );
require_once "$IP/extensions/Widgets/Widgets.php";
require_once "$IP/extensions/LinkTarget/LinkTarget.php";

// G Analytics
require_once "$IP/extensions/HeadScript/HeadScript.php";
$wgHeadScriptCode = '<script async src="https://www.googletagmanager.com/gtag/js?id=UA-116294927-1"></script><script src="https://www.wikimedia.cz/analytics.js"></script>';

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

## ======================================================================
## Upgrade stuff
## ======================================================================
#$wgReadOnly = 'This wiki is currently being upgraded to a newer software version. If you need anything, please contact the sysadmin at martin.urbanec@wikimedia.cz.';
