<?php

global $wgConf;

$wgConf->settings = [
	'wgServer' => [
		'default' => false,
		'pubwiki' => 'https://www.wikimedia.cz',
		'oldwiki' => 'https://old.wikimedia.cz',
		'innerwiki' => 'https://wiki.wikimedia.cz',
		'techwiki' => 'https://tech.wikimedia.cz',
	],
	'wmgSSO' => [
		'default' => false,
		'sul' => true,
	],
	'wgLanguageCode' => [
		'default' => 'cs',
		'techwiki' => 'en',
	],
	'groupOverrides2' => [
		'default' => [
			'sysop' => [
				'deleterevision' => true,
				'deletelogentry' => true,
			],
			// Sysadmin stuff for bureaucrats
			'bureaucrat' => [
				'hideuser' => true,
				'suppressionlog' => true,
				'suppressrevision' => true,
				'interwiki' => true,
				'siteadmin' => true,
			],
		]
	],
	'groupOverrides' => [
		'+private' => [
			'*' => [
				'read' => false,
				'edit' => false,
			],
			'user' => [
				'read' => true,
				'move' => true,
				'upload' => true,
				'autoconfirmed' => true,
				'editsemiprotected' => true,
				'reupload' => true,
				'skipcaptcha' => true,
			],
		],
		'+fishbowl' => [
			'*' => [
				'edit' => false,
			],
		],
		'+sul' => [
			'*' => [
				'createaccount' => false,
			],
			'user' => [
				'upload_by_url' => true,
				'suppressredirect' => true,
			],
			'bureaucrat' => [
				'userrights' => false, // central logging on innerwiki
			],
			// Groups not counted in auto member counts, no special rghts, to show only
			'techaccount' => [],
			'nonmember' => [],
		],
		'+innerwiki' => [
			'bureaucrat' => [
			   'userrights' => true,
			],
		],
	],
	'wgAddGroups' => [
		'innerwiki' => [
			'sysop' => [ 'nonmember' ],
		],
	],
	'wgRemoveGroups' => [
		'innerwiki' => [
			'sysop' => [ 'nonmember' ],
		],
	],
	'wgUseRCPatrol' => [
		'default' => false,
	],
	'wgUseNPPatrol' => [
		'default' => false,
	],
	'wgUseFilePatrol' => [
		'default' => false,
	],
	'wgLogo' => [
		'default' => '/static/images/wmcz_logo_135.png',
	],
	'wgSitename' => [
		'default' => 'Wikimedia ČR',
	],
	'wgHashedUploadDirectory' => [
		'default' => true,
		'pubwiki' => false,
		'oldwiki' => false,
	],
	'wgDefaultSkin' => [
		'default' => 'vector',
		'innerwiki' => 'monobook',
	],
	'wmgUseWidgets' => [
		'default' => false,
		'pubwiki' => true,
	],
	'wgNamespacesWithSubpages' => [
		'default' => [
			0 => true,
			1 => true,
		],
	],
	'wmgDebug' => [
		'default' => false,
	],
	'wgBlockDisablesLogin' => [
		'default' => true,
	],
	'wgExtraSignatureNamespaces' => [
		'default' => [ NS_PROJECT, ],
		'innerwiki' => [
			NS_MAIN,
		],
	],
	'wgImportSources' => [
		'default' => [
			'w'
		]
	],
	'wgThanksConfirmationRequired' => [
		'default' => false,
	],
	'wmgVisualEditor' => [
	'default' => false,
	'pubwiki' => true,
	'innerwiki' => true,
	],
	'wgUseFileCache' => [
		'default' => true,
		'private' => false,
	],
	'wmgUseOAuth' => [
		'default' => false,
		'innerwiki' => true,
	],
	'wgVisualEditorAvailableNamespaces' => [
		'default' => [
			'User' => true,
			'File' => true,
			'Help' => true,
			'Category' => true,
			'Project' => true,
		],
	],
	'wmgUseImgAuth' => [
		'default' => false,
		'private' => true,
	],
];
