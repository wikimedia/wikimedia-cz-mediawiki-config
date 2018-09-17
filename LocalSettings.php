<?php

## ======================================================================
## Tento soubor by neměl obsahovat žádné detaily,
## pouze nebzytné informace nutné k provozu MediaWiki.
##
## Veškeré další informace by měly být v samostatných
## souborech kodwiki.php
## ======================================================================

# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
		exit;
}

# =======================================================================
# Load externí konfigurace
# =======================================================================

require_once("/var/www/mediawiki-config/SwitchSettings.php");
