<?php

/*
 * Configuration file for SFTrackerExport
 * ------
 * Read the README.txt for more information on these settings.
 */


/*
 * Basic settings
 */

// Database details for The Bug Genie
$host		= 'localhost';
$user		= '';
$pass		= '';
$db_name	= 'thebuggenie';
$db_prefix	= 'tbg3_';

// Path to output csv files
$export_path = 'export/';

// Path to log errors
$log_path = 'log/';

// Bug Genie Project ID to add issues to
$bg_proj_id = '1';

// Backup tracker item title if none is provided
$bg_default_title = 'Empty';

// An array of Sourceforge tracker types (key) along with their
// corresponding issue type in Bug Genie. Each type (except the
// default) will be retrieved from Sourceforge, so remove any
// entries you don't want to export.
$bg_issue_type = array(
	"_default"			=> 1,	// do not remove
	"bugs"				=> 1,
	"feature-requests"	=> 2,
	"patches"			=> 3
	);

// How many tracker items to put into a single CSV file.
// Sourceforge's REST API is slow and can frequently fail. By
// outputing fewer items in a single file, there are fewer
// batches that need to be run again. If you need to run a
// specific batch again, set batch_page to the batch you need,
// otherwise it should be null.
// The first batch_page is 0.
$batch_size = 100;
$batch_page = null;

// Specify the appropriate bug genie state for each sourceforge ticket status
$bg_state = array(
	"_default"	=> 0,	// do not remove
	"closed"	=> 1,
	"open"		=> 0,
	"pending"	=> 0
	);

/*
 * Issue Detail Settings
 */

// Specify the appropriate bug genie status for each sourceforge status
$bg_status = array(
	"_default"	=> 23,	// do not remove
	"closed"	=> 31,
	"open"		=> 23,
	"pending"	=> 28
	);

// Specify the appropriate bug genie category for each sourceforge label
$bg_category = array(
	"_default"				=> 1,	// do not remove
	"General"				=> 1,
	"Security"				=> 2,
	"User Interface"		=> 3
	);

// Specify the appropriate bug genie milestone for each sourceforge milestone
$bg_milestone = array(
	"_default"			=> 1,	// do not remove
	"Sample Milestone"	=> 1
	);

// Specify the appropriate bug genie priority for each sourceforge priority
$bg_priority = array(
	"_default"	=> 8,	// do not remove
	"1"			=> 7,
	"2"			=> 7,
	"3"			=> 7,
	"4"			=> 7,
	"5"			=> 8,
	"6"			=> 6,
	"7"			=> 5,
	"8"			=> 4,
	"9"			=> 4,
	"10"		=> 4
	);

/*
 * User Import Settings
 */

// A default user ID in Bug Genie to use if there are any problems
// Typically you would use Bug Genie's automatically created Guest
$user_default = 2;

// The user group and scope in Bug Genie to add the user to. You may
// want to set up a special group to import users into, since they
// will not have any authentication details ported over.
$user_group = 2;
$user_scope = 1;

// Users to skip (ticket will be assigned to $user_default)
$user_skip = array(
	"*anonymous" => true,
	"nobody" => true
	);

// User settings to add for each user
$user_settings = array(
	array(
		"name" => "notify_issue_assigned_updated",
		"module" => "mailing",
		"value" => "1"
		),
	array(
		"name" => "notify_issue_once",
		"module" => "mailing",
		"value" => "1"
		),
	array(
		"name" => "notify_issue_posted_updated",
		"module" => "mailing",
		"value" => "1"
		),
	array(
		"name" => "notify_issue_project_vip",
		"module" => "mailing",
		"value" => "1"
		),
	array(
		"name" => "notify_issue_related_project_teamassigned",
		"module" => "mailing",
		"value" => "1"
		),
	array(
		"name" => "notify_issue_teamassigned_updated",
		"module" => "mailing",
		"value" => "1"
		),
	array(
		"name" => "notify_issue_commented_on",
		"module" => "mailing",
		"value" => "1"
		),
	array(
		"name" => "timezone",
		"module" => "core",
		"value" => "sys"
		),
	array(
		"name" => "language",
		"module" => "core",
		"value" => "sys"
		),
	);

// User dashboard views to add for each user
$user_dashboards = array(
	array(
		"name" => 1,
		"view" => 5,
		"pid" => 0,
		"target_type" => 1
		),
	array(
		"name" => 1,
		"view" => 3,
		"pid" => 0,
		"target_type" => 1
		),
	array(
		"name" => 1,
		"view" => 4,
		"pid" => 0,
		"target_type" => 1
		),
	array(
		"name" => 3,
		"view" => 0,
		"pid" => 0,
		"target_type" => 1
		),
	array(
		"name" => 1,
		"view" => 11,
		"pid" => 0,
		"target_type" => 1
		)
	);

/*
 * Load the classes needed
 */
require_once '_cSfQuery.php';

/*
 * Connect to the database.
 */
$dbconnect = mysql_connect($host, $user, $pass);
if (!@$dbconnect) {
	echo ('Unable to connect to the database server at ' . $host);
	exit();
}
mysql_select_db($db_name, $dbconnect);
if (!@mysql_select_db($db_name)) {
	die('Unable to locate database ' . $db_name);
}

?>