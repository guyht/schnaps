<?php
/*
 * Schnapps backup script
 *
 * Automatic snapshot management for Amazon EC2
 */

// Initial configuration
$path = dirname(__FILE__);
$config_file = 'schnapps.ini';

// Load logger
require('logger.php');
$logger = new GhtLib/Logger('schnapps.log');
$logger->init();

$logger->log('Schnapps started');

// Load configs
$config = parse_ini_file($config_file);

// Initiate config to database
if ($config['lock_mysql_db'] == 'true') {
	if (!$db = mysql_pconnect($config['db_server'], $config['db_username'], $config['db_password'])) {
		$logger->log('Error connecting to mysql: '.mysql_error($db), 'e');
		die(mysql_error($db);
	}
	$logger->log('Connected to mysql db');
}

	// Flush tables
if ($config['lock_mysql_db'] == 'true') {
	if(!mysql_query('FLUSH TABLES WITH READ LOCK')) {
		$logger->log('Error locking tables: '.$mysql_error($db), 'e');
		die(mysql_error($db);
	}
	$logger->log('Obtained lock on mysql db');
}

// Freeze filesystem
if ($config['lock_xfs_filesystem'] == 'true') {
	exec('xfs_freeze -f '.$config['xfs_mountpoint'];
	$logger->log('Filesystem frozen');
}
// ... Initiate snapshot
$logger->log('This is where the snapshot would be done');

// Unfreeze filesystem
if ($config['lock_xfs_filesystem'] == 'true') {
	exec('xfs_freeze -u '.$config['xfs_mountpoint']); // xfs_freeze -u /vol
	$logger->log('Filesystem unfrozen');
}

	// Unlock tables
if ($config['lock_mysql_db'] == 'true') {
	if (!mysql_query('UNLOCK TABLES')) {
		$logger->log('Error unlocking tables: '.$mysql_error($db), 'e');
		die(mysql_error($db);
	}
	$logger->log('Unlocked mysql db');
}

$logger->log('Schnapps finished');
