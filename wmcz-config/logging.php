<?php

$handlers = [
	'blackhole' => [
		'class' => \Monolog\Handler\NullHandler::class,
	]
];

$loggers = [
	'@default' => [
		'processors' => [ 'wiki', 'psr' ],
		'handlers' => (array)$wmgDefaultMonologHandler,
	]
];

foreach ( $wmgMonologChannels as $channel => $level ) {
	if (!isset($handlers["stream-$channel-$level"])) {
		$handlers["stream-$channel-$level"] = [
			'class' => '\\Monolog\\Handler\\StreamHandler',
			'args' => [ "/var/log/mediawiki/${channel}.log", $level ],
			'formatter' => 'line'
		];
	}

	if ($level !== false) {
		$loggers[$channel] = [
			'processors' => [ 'wiki', 'psr' ],
			'handlers' => [ "stream-$channel-$level" ]
		];
	}
}

$wgMWLoggerDefaultSpi = [
	'class' => '\\MediaWiki\\Logger\\MonologSpi',
	'args' => [ [
		'loggers' => $loggers,
		'processors' => [
            'wiki' => [ 'class' => '\\MediaWiki\\Logger\\Monolog\\WikiProcessor' ],
            'psr' => [ 'class' => '\\Monolog\\Processor\\PsrLogMessageProcessor' ],
        ],
        'handlers' => $handlers,
        'formatters' => [
            'line' => [ 'class' => '\\Monolog\\Formatter\\LineFormatter' ],
        ]
	] ]
];
