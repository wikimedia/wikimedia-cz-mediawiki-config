<?php

# Set realm
global $wmgRealm;
$wmgRealm = trim( file_get_contents( '/etc/wikimedia-cluster' ) ?: 'production' );

// $wgReadOnly = 'Prave probiha udrzba serveru, wiki je v rezimu pouze pro cteni. Dekujeme za pochopeni.';

# Switching stuff
if ( defined( 'MW_DB' ) ) {
	// Command-line mode and maintenance scripts (e.g. update.php) 
	$wgDBname = MW_DB;
} else {
	// Web server
	$server = $_SERVER['SERVER_NAME'];
	if ( $wmgRealm === 'production' ) {
		$rootDomain = 'wikimedia.cz';
	} elseif ( $wmgRealm === 'dev' ) {
		$rootDomain = 'wmcz.wikifarm';
	}
	if ( preg_match( "/^(.*)\.$rootDomain$/" , $server, $matches ) ) {
		$wikiname = $matches[1];
	} else {
		die( "Invalid host name, can't determine wiki name" );
		// Optional: Redirect to a "No such wiki" page.
	}
	if ( $wikiname === "www" ) {
		// Optional: Override database name of your "main" wiki (otherwise "wwwwiki")
		$wikiname = "pub";
	} else if ( $wikiname === "wiki" ) {
		$wikiname = "inner";
	}
	$wgDBname = $wikiname . "wiki";
}

// Load external stuff
require_once __DIR__ . "/PrivateSettings.php";
require_once __DIR__ . "/../DBLists.php";

// Real configuration
$wgConf = new SiteConfiguration;
$wgConf->wikis = DBLists::readDbListFile( 'all' );
$wgLocalDatabases = $wgConf->getLocalDatabases();

list( $site, $lang ) = $wgConf->siteFromDB( $wgDBname );
require __DIR__ . "/InitialiseSettings.php";
$confParams = [
	'lang'    => $lang,
	'docRoot' => $_SERVER['DOCUMENT_ROOT'],
	'site'    => $site,
];
$dblists = [];
foreach (["private", "sul", "fishbowl", "lockeddown"] as $dblist) {
	$wikis = DBLists::readDbListFile( $dblist );
	if ( in_array( $wgDBname, $wikis ) ) {
		$dblists[] = $dblist;
	}
}
$globals = $wgConf->getAll( $wgDBname, "wiki", $confParams, $dblists );
extract( $globals );

// Configure database
$wgDBuser = "wikiuser";
$wgDBadminuser = "wikiadmin";
$wgDBserver = "localhost";
$wgDBtype = "mysql";
$wgDBmysql5 = true;
$wgDBprefix = "";
$wgDBTableOptions   = "ENGINE=InnoDB, DEFAULT CHARSET=binary";

// Configure cache
if ( $wmgRealm === 'production' ) {
	$wgMainCacheType = CACHE_MEMCACHED;
	$wgMemCachedServers = [ '127.0.0.1:11211' ];
}

// Configure email notifications
$wgEnotifWatchlist = true;
$wgEnotifUserTalk = true;


// Load skins and extensions
wfLoadSkins( [ 'Vector', 'MonoBook', 'Modern', 'CologneBlue', 'Timeless' ] );
wfLoadExtension( 'AbuseFilter' );
wfLoadExtension( 'Cite' );
wfLoadExtension( 'Interwiki' );
wfLoadExtension( 'ImageMap' );
wfLoadExtension( 'InputBox' );
//wfLoadExtension( 'TitleKey' );
wfLoadExtension( 'Poem' );
wfLoadExtension( 'CategoryTree' );
wfLoadExtension( 'LabeledSectionTransclusion' );
wfLoadExtension( 'Echo' );
wfLoadExtension( 'Thanks' );
wfLoadExtension( 'CharInsert' );
wfLoadExtension( 'Renameuser' );
wfLoadExtension( 'Nuke' );
wfLoadExtension( 'Linter' );
//wfLoadExtension( 'OATHAuth' );
$wgOATHAuthDatabase = 'wikiusers';
//wfLoadExtension( 'Gadgets' );
wfLoadExtension( 'ParserFunctions' );
wfLoadExtension( 'TemplateStyles' );
$wgPFEnableStringFunctions = true;
wfLoadExtension( 'SyntaxHighlight_GeSHi' );

wfLoadExtension( 'timeline' );
// Timeline settings
$wgTimelinePloticusCommand = "/usr/bin/ploticus";
$wgTimelinePerlCommand = "/usr/bin/perl";

wfLoadExtension( 'UserMerge' );
// UserMerge settings
$wgGroupPermissions['bureaucrat']['usermerge'] = true;

wfLoadExtension( 'MultimediaViewer' );
$wgMediaViewerIsInBeta = false;

// WikiEditor settings
wfLoadExtension( 'CodeMirror' );
wfLoadExtension('WikiEditor');
$wgDefaultUserOptions['usebetatoolbar'] = 1;
$wgDefaultUserOptions['usebetatoolbar-cgd'] = 1;
$wgDefaultUserOptions['wikieditor-preview'] = 0;
$wgDefaultUserOptions['wikieditor-publish'] = 0;

// Configure misc common things
$wgScriptPath = '/mw';
$wgArticlePath      = "/wiki/$1";
$wgPasswordSender = "mw@wikimedia.cz";
$wgEmergencyContact = "mw@wikimedia.cz";
$wgFragmentMode = [ 'html5', 'legacy' ];

// Configure uploads
$wgEnableUploads       = true;
$wgUploadDirectory = "/var/www/wikis/images/$wgDBname";
$wgUploadPath = "/uploads/$wgDBname";
$wgThumbnailScriptPath = "{$wgScriptPath}/thumb.php";

$wgUseImageMagick = true;
$wgImageMagickConvertCommand = "/usr/bin/convert";
$wgSVGConverter = 'rsvg';
$wgMaxImageArea = 3e7;

// PDF handler
wfLoadExtension( 'PdfHandler' );
$wgPdfProcessor = '/usr/bin/gs';
$wgPdfPostProcessor = $wgImageMagickConvertCommand;
$wgPdfInfo = '/usr/bin/pdfinfo';
$wgPdftoText = '/usr/bin/pdftotext';

// Extra extensions (TODO: Move to IS.php)
$wgFileExtensions[] = 'docx';
$wgFileExtensions[] = 'xls';
$wgFileExtensions[] = 'pdf';
$wgFileExtensions[] = 'mpp';
$wgFileExtensions[] = 'odt';
$wgFileExtensions[] = 'ods';

$wgUseInstantCommons = true; // Commons

$wgAllowCopyUploads = true;
$wgCopyUploadsFromSpecialUpload = true;

$wgEnableRestAPI = true;

// Process permissions
foreach ( $groupOverrides2 as $group => $permissions ) {
	if ( !array_key_exists( $group, $wgGroupPermissions ) ) {
		$wgGroupPermissions[$group] = [];
	}
	$wgGroupPermissions[$group] = $permissions + $wgGroupPermissions[$group];
}

foreach ( $groupOverrides as $group => $permissions ) {
	if ( !array_key_exists( $group, $wgGroupPermissions ) ) {
		$wgGroupPermissions[$group] = [];
	}
	$wgGroupPermissions[$group] = $permissions + $wgGroupPermissions[$group];
}

// Temporary bypass of T245149
if ( isset( $_SERVER['REMOTE_ADDR'] ) && in_array( $_SERVER['REMOTE_ADDR'], [ '2a01:430:17:1::ffff:1416', '37.205.8.151' ] ) ) {
	$wgGroupPermissions['*']['read'] = true;
}

// Logging
require_once __DIR__ . '/logging.php';

if($wmgUseWidgets) {
	wfLoadExtension( 'Widgets' );
}

// Shared database, if necessary
if ($wmgSSO) {
	$wgSharedDB = 'wikiusers';
	$wgSharedPrefix = '';
	$wgSharedTables = [];
	$wgSharedTables[] = 'user_properties';
	$wgSharedTables[] = 'user';
	$wgSharedTables[] = 'actor';
	$wgSharedTables[] = 'user_groups';
	$wgSharedTables[] = 'ipblocks';
	$wgSharedTables[] = "ipblocks_restrictions";
	$wgSharedTables[] = 'interwiki';
}

// Debug
if ($wmgDebug) {
	$wgShowExceptionDetails = true;
	$wgDebugToolbar = true;
	$wgShowSQLErrors = true;
	$wgShowDBErrorBacktrace = true;
}

// Visualeditor
if ( $wmgVisualEditor ) {
	$PARSOID_INSTALL_DIR = '/var/www/wikis/parsoid';
	wfLoadExtension( 'Parsoid', "$PARSOID_INSTALL_DIR/extension.json");
	wfLoadExtension( 'VisualEditor' );
	$wgVisualEditorParsoidAutoConfig = false;
	$wgParsoidSettings = [
		'useSelser' => true,
		'rtTestMode' => false,
		'linting' => false,
	];
	$wgVirtualRestConfig['modules']['parsoid'] = [];
	$wgDefaultUserOptions['visualeditor-enable'] = 1;
	$wgVirtualRestConfig['modules']['parsoid']['forwardCookies'] = true;

	if ( !isset( $_SERVER['REMOTE_ADDR'] ) OR $_SERVER['REMOTE_ADDR'] == '37.205.8.151' /** Current public IP **/ ) {
		$wgGroupPermissions['*']['read'] = true;
		$wgGroupPermissions['*']['edit'] = true;
	}

	// Set parsoid location
	/*$wgVirtualRestConfig['modules']['parsoid'] = array(
		'url' => 'http://localhost:8000',
		'domain' => $wgDBname,
	);*/
}

// OAuth
if ( $wmgUseOAuth  ) {
	wfLoadExtension( 'OAuth' );
	$wgGroupPermissions['user']['mwoauthproposeconsumer'] = true;
	$wgGroupPermissions['user']['mwoauthupdateownconsumer'] = true;
	$wgGroupPermissions['bureaucrat']['mwoauthmanageconsumer'] = true;
	$wgWhitelistRead[] = 'Special:OAuth';
	$wgWhitelistRead[] = 'Speciální:OAuth';
}

// ReplaceText
if ( $wmgUseReplaceText ) {
	wfLoadExtension( 'ReplaceText' );
}

// DiscussionTools
if ( $wmgUseDiscussionTools ) {
	wfLoadExtension( 'DiscussionTools' );
}

if ( $wmgUseSandboxLink ) {
	wfLoadExtension( 'SandboxLink' );
}

if ( $wmgUseCheckUser ) {
	wfLoadExtension( 'CheckUser' );
	$wgCUDMaxAge = 365 * 2 * 24 * 3600; // 2 years
}

if ( $wmgUseSecurePoll ) {
	wfLoadExtension( 'SecurePoll' );
}

if ( $wmgUseCodeEditor ) {
	wfLoadExtension( 'CodeEditor' );
	$wgDefaultUserOptions['usebetatoolbar'] = 1;
}

if ( $wmgUseSyntaxHighlight ) {
	wfLoadExtension( 'SyntaxHighlight_GeSHi' );
}

if ( $wmgUseScribunto ) {
	wfLoadExtension( 'Scribunto' );
	$wgScribuntoDefaultEngine = 'luastandalone';
}

// For private wikis, use img_auth.php
if ( $wmgUseImgAuth ) {
	$wgUploadPath = "/mw/img_auth.php";
}

// Security mitigation - https://www.mediawiki.org/wiki/2021-12_security_release/FAQ
$wgActions['mcrundo'] = false;
$wgActions['mcrrestore'] = false;

// Test
$wgHTTPImportTimeout = 1000;
