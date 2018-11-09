<?php

## ======================================================================
## Tento soubor obsahuje společnou konfiguraci..
## ======================================================================

# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
		exit;
}

## ======================================================================
## Setup paths
## ======================================================================
$wgScriptPath       = "/mw";
$wgScriptExtension  = ".php";

## ======================================================================
## Database settings
## ======================================================================
$wgDBtype           = "mysql";
$wgDBserver         = "localhost";
$wgDBmysql5 = true;

$wgDBprefix         = "";
$wgSharedDB = 'wikiusers';
$wgSharedPrefix = '';
$wgSharedTables[] = 'user_groups';
$wgSharedTables[] = 'ipblocks';

$wgDBTableOptions   = "ENGINE=InnoDB, DEFAULT CHARSET=binary";

## ======================================================================
## Email settings
## ======================================================================
$wgEnableEmail      = true;
$wgEnableUserEmail  = true;

$wgEmailAuthentication = false;

## ======================================================================
## Notification settings
## ======================================================================
$wgEnotifUserTalk = true; # UPO
$wgEnotifWatchlist = true; # UPO

$wgPasswordSender = "mw@wikimedia.cz";

## ======================================================================
## Uploads
## ======================================================================
$wgEnableUploads       = true;
$wgUploadSizeWarning = 4 * 1024 * 1024;
$wgUseImageMagick = false; // unicode fails
$wgImageMagickConvertCommand = "/usr/bin/convert";
$wgMaxImageArea = 3e7;

$wgUseInstantCommons = true; // Commons

$wgAllowCopyUploads = true;
$wgCopyUploadsFromSpecialUpload = true;
$wgGroupPermissions['user']['upload_by_url'] = true;
$wgGroupPermissions['sysop']['upload_by_url'] = true;

// Extensions
$wgFileExtensions[] = 'pdf';
$wgFileExtensions[] = 'odp';
$wgFileExtensions[] = 'doc';
$wgFileExtensions[] = 'docx';
$wgFileExtensions[] = 'ppt';
$wgFileExtensions[] = 'pptx';
$wgFileExtensions[] = 'xls';
$wgFileExtensions[] = 'xlsx';
$wgFileExtensions[] = 'odt';
$wgFileExtensions[] = 'ods';
$wgFileExtensions[] = 'mp3';
$wgFileExtensions[] = 'ogg';
$wgFileExtensions[] = 'xls';
$wgFileExtensions[] = 'eps';
$wgFileExtensions[] = 'indd';
$wgFileExtensions[] = 'svg';
$wgSVGConverter = 'rsvg';

## ======================================================================
## Permissions and nadstandard as limited wiki
## ======================================================================
// As this is limited wiki, some nadstandard for logged in users
$wgGroupPermissions['user']['suppressredirect'] = true;

// Nadstandard for sysops
$wgGroupPermissions['sysop']['createaccount'] = true;
$wgGroupPermissions['sysop']['deleterevision'] = true;
$wgGroupPermissions['sysop']['deletelogentry'] = true;


// Sysadmin stuff for bureaucrat
$wgGroupPermissions['bureaucrat']['hideuser'] = true;
$wgGroupPermissions['bureaucrat']['suppressionlog'] = true;
$wgGroupPermissions['bureaucrat']['suppressrevision'] = true;
$wgGroupPermissions['bureaucrat']['interwiki'] = true;

// Technical account group - for making automated member count easier
$wgGroupPermissions['techaccount']['read'] = true;

// Interface editor
$wgGroupPermissions['editinterface']['editinterface'] = true;
$wgGroupPermissions['editinterface']['editprotected'] = true;

// Personal JS allowed
$wgAllowUserJs = true;
$wgUseAjax = true;

## ======================================================================
## Patrol stuff
## ======================================================================
$wgUseRCPatrol = false;
$wgUseNPPatrol = false;
$wgUseFilePatrol = false;

## ======================================================================
## Extensions
## ======================================================================
wfLoadExtension( 'Cite' );
wfLoadExtension( 'Interwiki' );
wfLoadExtension( 'ImageMap' );
wfLoadExtension( 'InputBox' );
wfLoadExtension( 'TitleKey' );
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

## ======================================================================
## Styles
## ======================================================================
wfLoadSkin('MonoBook');
wfLoadSkin('Vector');

$wgLogo = "//wiki.wikimedia.cz/images/wmcz_logo_135.png";
$wgFavicon = '//wiki.wikimedia.cz/images/favicon.ico';

## ======================================================================
## Import
## ======================================================================
$wgImportSources = array(
	'w'
);

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
## Misc things
## ======================================================================
$wgBlockDisablesLogin = true; // Disable login when blocking
$wgDiff3 = "/usr/bin/diff3"; // Diff command

// Disable cache
$wgMainCacheType = CACHE_NONE;
$wgMemCachedServers = array();

$wgRCMaxAge = 3600 * 24 * 90; // Bigger max RC

$wgDefaultUserOptions['showhiddencats'] = 1; // Show hidden cat by default

$wgShellLocale = "en_US.UTF-8";

$wgLanguageCode = "cs";
$wgLocaltimezone = 'Europe/Prague';

$wgFixDoubleRedirects = true; // No double redirects

## ======================================================================
## Upgrade stuff
## ======================================================================
#$wgShowExceptionDetails = true;
#$wgDebugToolbar = true;
#$wgShowSQLErrors = true;
#$wgShowDBErrorBacktrace = true;

#$wgReadOnly = 'This wiki is currently being upgraded to a newer software version. If you need anything, please contact the sysadmin at martin.urbanec@wikimedia.cz.';
