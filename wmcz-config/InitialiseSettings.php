<?php

global $wgConf;

$wgConf->settings = [
    'wmgSSO' => [
        'default' => true,
        'separate' => false,
    ],
    'wgLanguageCode' => [
        'default' => 'cs',
        'techwiki' => 'en',
    ],
    'groupOverrides2' => [
        'default' => [
            '*' => [
                'createaccount' => false,
            ],
            'user' => [
                // If you add something normally sensitive here, revoke the privilege for separate wikis,
                // as those are publicly editable
                'upload_by_url' => true,
                'suppressredirect' => true,
            ],
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
                'userrights' => false,
            ],
            // Groups not counted in auto member counts, no special rghts, to show only
            'techaccount' => [],
            'nonmember' => [],
        ]
    ],
    'groupOverrides' => [
        'private' => [
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
        'fishbowl' => [
            '*' => [
                'edit' => false,
            ],
        ],
        'separate' => [
            '*' => [
                'createaccount' => true,
            ],
            'user' => [
                'upload_by_url' => false,
                'suppressredirect' => false,
            ],
            'bureaucrat' => [
                'userrights' => true,
            ],
        ],
        '+innerwiki' => [
            'bureaucrat' => [
               'userrights' => true,
            ],
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
    'wmgMaintenance' => [
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
];
