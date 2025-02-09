<?php

global $wgConf;

$wgConf->settings = [
	'wgServer' => [
		'default' => false,
		'oldwiki' => 'https://old.wikimedia.cz',
		'innerwiki' => 'https://wiki.wikimedia.cz',
		'techwiki' => 'https://tech.wikimedia.cz',
		'docswiki' => 'https://docs.wikimedia.cz',
	],
	'wmgSSO' => [
		'default' => false,
		'sul' => true,
	],
	'wmgDefaultMonologHandler' => [
		'default' => 'blackhole',
	],
	'wmgMonologChannels' => [
		'default' => [
			'exception' => 'debug',
			'error' => 'debug',
			'thumb' => 'info',
			'ratelimit' => 'debug',
			'dberror' => 'debug',
			'VisualEditor' => 'debug',
		]
	],
	'wgLanguageCode' => [
		'default' => 'cs',
		'techwiki' => 'en',
	],
	'wgLocaltimezone' => [
		'default' => 'Europe/Prague',
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
			'electionadmin' => [
				'securepoll-create-poll' => true,
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
				'createaccount' => false,
			],
		],
		'+lockeddown' => [
			'*' => [
				'edit' => false,
			],
			'user' => [
				'edit' => false,
			],
			'bureaucrat' => [
				'edit' => true,
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
			'transwiki' => [
				'import' => true,
			],
		],
		'+innerwiki' => [
			'bureaucrat' => [
			   'userrights' => true,
			],
		],
	],
	'wgAddGroups' => [
		'innerwiki' => [
			'sysop' => [ 'nonmember', 'techaccount' ],
		],
	],
	'wgRemoveGroups' => [
		'innerwiki' => [
			'sysop' => [ 'nonmember', 'techaccount' ],
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
		'oldwiki' => false,
	],
	'wgDefaultSkin' => [
		'default' => 'vector',
		'innerwiki' => 'monobook',
	],
	'wmgUseWidgets' => [
		'default' => false,
		'oldwiki' => true,
		'innerwiki' => true,
	],
	'wmgDebug' => [
		'default' => false,
	],
	'wgBlockDisablesLogin' => [
		'default' => true,
	],
	'wgExtraNamespaces' => [
		'innerwiki' => [
			3000 => 'Zastaralé',
			3001 => 'Diskuse_k_zastaralému',
		],
	],
	'wgExtraSignatureNamespaces' => [
		'default' => [ NS_PROJECT, ],
		'innerwiki' => [
			NS_MAIN,
		],
	],
	'wgImportSources' => [
		'default' => [
			'w',
			'cs',
			'en',
			'web',
		]
	],
	'wgThanksConfirmationRequired' => [
		'default' => false,
	],
	'wmgVisualEditor' => [
		'default' => false,
		'innerwiki' => true,
		'docswiki' => true,
	],
	'wgUseFileCache' => [
		'default' => true,
		'private' => false,
	],
	'wmgUseOAuth' => [
		'default' => false,
		'innerwiki' => true,
	],
	'wmgUseReplaceText' => [
		'default' => false,
		'innerwiki' => true,
	],
	'wmgUseDiscussionTools' => [
		'default' => false,
		'innerwiki' => true,
	],
	'wmgUseSandboxLink' => [
		'default' => false,
		'innerwiki' => true,
	],
	'wmgUseCheckUser' => [
		'default' => true,
	],
	'wmgUseSecurePoll' => [
		'default' => false,
		'innerwiki' => true,
	],
	'wmgUseCodeEditor' => [
		'default' => false,
		'innerwiki' => true,
	],
	'wmgUseSyntaxHighlight' => [
		'default' => false,
		'innerwiki' => true,
	],
	'wmgUseScribunto' => [
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
	'wgDefaultRobotPolicy' => [
		'default' => 'index,follow',
		'oldwiki' => 'noindex,nofollow',
		'private' => 'noindex,nofollow',
	],
	'wgNamespacesWithSubpages' => [
		'default' => [
			NS_MAIN => true,
			NS_TALK /* 1 */ => true,
			NS_USER /* 2 */ => true,
			NS_USER_TALK /* 3 */ => true,
			NS_PROJECT /* 4 */ => true,
			NS_PROJECT_TALK /* 5 */ => true,
			NS_FILE_TALK /* 7 */ => true,
			NS_MEDIAWIKI /* 8 */ => true,
			NS_MEDIAWIKI_TALK /* 9 */ => true,
			NS_TEMPLATE /* 10 */ => true,
			NS_TEMPLATE_TALK /* 11 */ => true,
			NS_HELP /* 12 */ => true,
			NS_HELP_TALK /* 13 */ => true,
			NS_CATEGORY_TALK /* 15 */ => true,
		],
		'+innerwiki' => [
			3000 => true,
			3001 => true,
		],
	],
	'wgFixDoubleRedirects' => [
		'default' => false,
		'private' => true,
		'fishbowl' => true
	],
];
