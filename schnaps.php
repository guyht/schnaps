<?php
/*
 * Schnaps backup script
 *
 * Automatic snapshot management for Amazon EC2
 */

// Initial configuration
$path = dirname(__FILE__);
$config_file = 'schnaps.ini';

// Load logger
require('logger.php');
$logger = new GhtLib\Logger('schnaps.log');
$logger->init();

$logger->log('Schnaps started');

// Load configs
$config = parse_ini_file($config_file);

// Initiate config to database
if ($config['lock_mysql_db'] == 'true') {
	if (!$db = mysql_pconnect($config['db_server'], $config['db_username'], $config['db_password'])) {
		$logger->log('Error connecting to mysql: '.mysql_error($db), 'e');
		die(mysql_error($db));
	}
	$logger->log('Connected to mysql db');
}

// Flush tables
if ($config['lock_mysql_db'] == 'true') {
	if(!mysql_query('FLUSH TABLES WITH READ LOCK')) {
		$logger->log('Error locking tables: '.$mysql_error($db), 'e');
		die(mysql_error($db));
	}
	$logger->log('Obtained lock on mysql db');
}

// Freeze filesystem
if ($config['lock_xfs_filesystem'] == 'true') {
	exec('xfs_freeze -f '.$config['xfs_mountpoint']);
}
// ... Initiate snapshot

// Unfreeze filesystem
if ($config['lock_xfs_filesystem'] == 'true') {
	exec('xfs_freeze -u '.$config['xfs_mountpoint']); // xfs_freeze -u /vol
	$logger->log('Filesystem unfrozen');
}

	// Unlock tables
if ($config['lock_mysql_db'] == 'true') {
	if (!mysql_query('UNLOCK TABLES')) {
		$logger->log('Error unlocking tables: '.$mysql_error($db), 'e');
		die(mysql_error($db));
	}
	$logger->log('Unlocked mysql db');
}

$logger->log('Schnaps finished');
