<?php

## ======================================================================
## Tento soubor obsahuje konkrétní konfiguraci pro inner_wiki
## ======================================================================

# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
		exit;
}

## ======================================================================
## Identity
## ======================================================================
$wgSitename         = "Wikimedia ČR";
$wgArticlePath      = "/wiki/$1";

## ======================================================================
## Uploads
## ======================================================================
$wgUploadPath = "/mw/img_auth.php";

## ======================================================================
## Skins
## ======================================================================
$wgDefaultSkin = 'monobook';

## ======================================================================
## Permissions
## ======================================================================
// Disallow anonymous access
$wgGroupPermissions['*']['createaccount']   = false; // nothing for anons
$wgGroupPermissions['*']['read']            = false;
$wgGroupPermissions['*']['edit']            = false;
$wgGroupPermissions['*']['createpage']      = false;
$wgGroupPermissions['*']['createtalk']      = false;

// Prevent normal user from user account creating
$wgGroupPermissions['user']['createaccount'] = false;

// Allow bot access
$wgGroupPermissions['bot']['read']            = true;
$wgGroupPermissions['bot']['edit']            = true;
$wgGroupPermissions['bot']['createpage']      = true;
$wgGroupPermissions['bot']['createtalk']      = true;
$wgGroupPermissions['bot']['suppressredirect'] = true;

// Allow admins to grant templateeditor
$wgAddGroups['sysop'][] = 'templateeditor';

// Whitelist necessary information
$wgWhitelistRead = array("Special:UserLogin", "Special:UserLogout", "Special:PasswordReset", "MediaWiki:Common.css", "Speciální:OAuth" );

## ======================================================================
## Namespaces
## ======================================================================
$wgExtraNamespaces = array(
	100 => "Ven",
	101 => "Ven_diskuse",
	#102 => "Rada",
	#103 => "Rada_diskuse",
	104 => "Archiv",
	105 => "Archiv_diskuse"
);

## ======================================================================
## Extensions
## ======================================================================
wfLoadExtension( 'SandboxLink' );
wfLoadExtension( 'OAuth' );
// OAuth settings
$wgGroupPermissions['bureaucrat']['mwoauthproposeconsumer'] = true;
$wgGroupPermissions['bureaucrat']['mwoauthupdateownconsumer'] = true;
$wgGroupPermissions['bureaucrat']['mwoauthmanageconsumer'] = true;
$wgGroupPermissions['bureaucrat']['mwoauthsuppress'] = true;
$wgGroupPermissions['bureaucrat']['mwoauthviewsuppressed'] = true;
$wgGroupPermissions['bureaucrat']['mwoauthviewprivate'] = true;
$wgGroupPermissions['bureaucrat']['mwoauthmanagemygrants'] = true;

## ======================================================================
## Misc stuff
## ======================================================================
$wgExtraSignatureNamespaces = array( NS_MAIN ); // Main is signable
