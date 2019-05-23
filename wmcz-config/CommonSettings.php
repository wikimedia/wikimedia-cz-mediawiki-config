<?php

# Switching stuff
if ( defined( 'MW_DB' ) ) {
    // Command-line mode and maintenance scripts (e.g. update.php) 
    $wgDBname = MW_DB;
} else {
    // Web server
    $server = $_SERVER['SERVER_NAME'];
    if ( preg_match( '/^(.*)\.wikimedia.cz$/', $server, $matches ) ) {
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

// Configure database
$wgDBuser = "wikiuser";
$wgDBadminuser = "wikiadmin";
$wgDBserver = "localhost";
$wgDBtype = "mysql";
$wgDBmysql5 = true;
$wgDBprefix = "";
$wgDBTableOptions   = "ENGINE=InnoDB, DEFAULT CHARSET=binary";

// Configure cache
$wgMainCacheType = CACHE_MEMCACHED;

// Configure email notifications
$wgEnotifWatchlist = true;
$wgEnotifUserTalk = true;


// Load skins and extensions
wfLoadSkins( [ 'Vector', 'MonoBook', 'Modern', 'CologneBlue', 'Timeless' ] );
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
wfLoadExtension( 'Gadgets' );
wfLoadExtension( 'ParserFunctions' );
wfLoadExtension( 'TemplateStyles' );
$wgPFEnableStringFunctions = true;

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
wfLoadExtension('WikiEditor');
$wgDefaultUserOptions['usebetatoolbar'] = 1;
$wgDefaultUserOptions['usebetatoolbar-cgd'] = 1;
$wgDefaultUserOptions['wikieditor-preview'] = 0;
$wgDefaultUserOptions['wikieditor-publish'] = 0;

// Configure misc common things
$wgScriptPath = '/mw/';
$wgArticlePath      = "/wiki/$1";
$wgPasswordSender = "mw@wikimedia.cz";

// Configure uploads
$wgEnableUploads       = true;
$wgUploadDirectory = "/var/www/wikis/images/$wgDBname";
$wgUploadPath = "/mw/img_auth.php"; // TODO: Only for private, probably some changes in apache will be needed too.
$wgUploadSizeWarning = 4 * 1024 * 1024;
$wgUseImageMagick = false; // unicode fails
$wgImageMagickConvertCommand = "/usr/bin/convert";
$wgSVGConverter = 'rsvg';
$wgMaxImageArea = 3e7;

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

// Real configuration
$wgConf = new SiteConfiguration;
list( $site, $lang ) = $wgConf->siteFromDB( $wgDBname );
require __DIR__ . "/InitialiseSettings.php";
$confParams = [
    'lang'    => $lang,
    'docRoot' => $_SERVER['DOCUMENT_ROOT'],
    'site'    => $site,
];
$dblists = [];
foreach (["private", "separate", "fishbowl"] as $dblist) {
    $wikis = DBLists::readDbListFile( $dblist );
    if ( in_array( $wgDBname, $wikis ) ) {
        $dblists[] = $dblist;
    }
}
$globals = $wgConf->getAll( $wgDBname, "wiki", $confParams, $dblists );
extract( $globals );

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

if($wmgUseWidgets) {
    wfLoadExtension( 'Widgets' );
}

// Shared database, if necessary
if ($wmgSSO) {
    $wgSharedDB = 'wikiusers';
    $wgSharedPrefix = '';
    $wgSharedTables[] = 'user_groups';
    $wgSharedTables[] = 'ipblocks';
    $wgSharedTables[] = "ipblocks_restrictions";
}

// Debug
if ($wmgMaintenance) {
    $wgShowExceptionDetails = true;
    $wgDebugToolbar = true;
    $wgShowSQLErrors = true;
    $wgShowDBErrorBacktrace = true;
}

// Visualeditor
if ( $wmgVisualEditor ) {
	wfLoadExtension( 'VisualEditor' );
	$wgDefaultUserOptions['visualeditor-enable'] = 1;
	if ( !isset( $_SERVER['REMOTE_ADDR'] ) OR $_SERVER['REMOTE_ADDR'] == '2a01:430:17:1::ffff:1416' OR $_SERVER['REMOTE_ADDR'] == '37.205.8.151' OR $_SERVER['REMOTE_ADDR'] == '127.0.0.1' ) {
		$wgGroupPermissions['*']['read'] = true;
		$wgGroupPermissions['*']['edit'] = true;
	}

	// Set parsoid location
	$wgVirtualRestConfig['modules']['parsoid'] = array(
	    'url' => 'http://localhost:8080',
	    'domain' => $wgDBname,
	);
}

// Test
$wgHTTPImportTimeout = 1000;
