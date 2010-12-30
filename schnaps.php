<?php
/*
 * Schnaps backup script
 *
 * Automatic snapshot management for Amazon EC2
 */

// Initial configuration
$path = dirname(__FILE__);
$config_file = 'schnaps.ini';

// Determine year, month, day and hour
$year       = date('Y');
$month      = date('m');
$day        = date('d');
$hour       = date('H');

// Load logger
require('logger.php');
$logger = new GhtLib\Logger('schnaps.log');
$logger->init();

// Load AWS SDK
require_once dirname(__FILE__).'/aws-sdk-for-php/sdk.class.php';

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
	$logger->log('Filesystem frozen', 'i', false);
}
// ... Initiate snapshot
$logger->log('Initiating snapshot', 'i', false);

// Build snapshot name
$snap_name = 'schnaps-backup-'.($day == $config['month_day'] ? 'perm-' : '').$config['vol_id'].'-'.$year.'-'.$month.'-'.$day.'-'.$hour;

// Initiate ec2 object and make request to create snapshot
$ec2 = new AmazonEC2($config['aws_key'], $config['aws_secret_key']);
$resp = $ec2->create_snapshot($config['vol_id'], $snap_name);
if ($resp->isOK()) {
	$logger->log('Created snapshot with description: '.$snap_name, 'i', false);
} else {
	$logger->log('Failed to create snapshot with description: '.$snap_name, 'e', false);
}

// Retrieve snapshot id
$snap_id = $resp->body->snapshotId;
$logger->log('Snapshot id is: '.$snap_id, 'i', false);

// Add tag
$logger->log('Tagging snapshot', 'i', false);
$ec2->create_tags($snap_id, array(
								array('Key' => 'schnaps_backup', 'Value' => 'true'),
								array('Key' => 'protected', 'Value' => ($day == $config['month_day'] ? 'true' : 'false')),
							));

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

// The first part of the script is complete, now its time to delete snapshots older than x days
$logger->log('Describing snapshots');
$resp = $ec2->describe_snapshots(array('Owner' => 'self', 'Filter' => array(
									array('Name' => 'tag:schnaps_backup', 'Value' => 'true'),
									array('Name' => 'tag:protected', 'Value' => 'false'))));
$resp_perm = $ec2->describe_snapshots(array('Owner' => 'self', 'Filter' => array(
									array('Name' => 'tag:schnaps_backup', 'Value' => 'true'),
									array('Name' => 'tag:protected', 'Value' => 'true'))));
$items = $resp->body->snapshotSet->item;
$items_perm = $resp_perm->body->snapshotSet->item;
$delete_before = time() - ($config['delete_older_than']*24*60*60);
$logger->log('Will attempt do delete snapshots older than '.date('Y-m-d', $delete_before));

foreach ($items as $snapshot) {
	$logger->log('Found snapshot '.$snapshot->description);
	$regex = "/schnaps-backup-(?:perm-)?vol-([0-9a-zA-Z]+)-([0-9]{4})-([0-9]{2})-([0-9]{2})-([0-9]{2})/";
	$match = array();
	preg_match_all($regex, $snapshot->description, $match);
	$sy = $match[2][0];
	$sm = $match[3][0];
	$sd = $match[4][0];
	$stamp = mktime(0, 0, 0, $sm, $sd, $sy);
	$logger->log('Snapshot was created at '.date('Y-m-d', $stamp));
	if ($stamp < $delete_before) {
		$logger->log('Attempting to delete snapshot');
		$logger->log('Checking newer permanent snapshot exists');
		$found = false;
		foreach ($items_perm as $snapshot_perm) {
			$match = array();
			preg_match_all($regex, $snapshot_perm->description, $match);
			$sy = $match[2][0];
			$sm = $match[3][0];
			$sd = $match[4][0];
			$stamp_perm = mktime(0, 0, 0, $sm, $sd, $sy);
			if ($stamp_perm >= $stamp) {
				$found = true;
			}
		}
		if ($found) {
			$logger->log('Found newer permanent snapshot, deleting snapshot '.$snapshot->description);
			$ec2->delete_snapshot($snapshot->snapshotId);
		} else {
			$logger->log('Could not delete snapshot as no newer permanent snapshot exists.  This is most likely caused by an error.', 'e');
		}
	}
}


$logger->log('Schnaps finished');
