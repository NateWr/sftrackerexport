<?php

/*
 * Small utility to retrieve ticket parameters from sourceforge
 * ------
 * This tool will print out arrays containing all the unique
 * statuses, categories, priorities, etc that it finds in
 * your tracker. This is useful for setting the conversion
 * arrays in config.php and for setting up appropriate items
 * in bug genie. Just uncomment the section (bugs/fr/patches)
 * that you want to check. You'll also need to modify the
 * limit and page parameters in the query to make sure you
 * get them all.
 */

require_once '_cSfQuery.php';

// CSV file to write to
$csv_file = 'sftrackerexport.csv';

// Issue type
$issue_type = 'bugs';

// Limit and page for the query
$limit = 1000;
$page = 0; 

// Set a global error log
$api_error_log = array();

// Allow the program to run for a while;
set_time_limit(1200);

/*
 * Fetch the issues
 */
$status = array();
$category = array();
$priority = array();
$milestone = array();
$labels = array();
$reported_by = array();
$assigned_to = array();
$multiple_labels = array();
$custom_fields = array();

// Retrieve and loop over the tickets
$sfBugs = new sfQuery();
$sfBugs->queryAPI($issue_type . '/?limit=' . $limit . '&page=' . $page);
$t = 0;
foreach ($sfBugs->data->tickets as $ticket) {

	// Retrieve the ticket details
	$sfBugDetail = new sfQuery();
	$sfBugDetail->queryAPI($isue_type . '/' . $ticket->ticket_num);
	
	$status[$sfBugDetail->data->ticket->status] = 1;
	if (isset($sfBugDetail->data->ticket->custom_fields->_priority)) {
		$priority[$sfBugDetail->data->ticket->custom_fields->_priority] = 1;
	}	
	if (isset($sfBugDetail->data->ticket->custom_fields->_milestone)) {
		$milestone[$sfBugDetail->data->ticket->custom_fields->_milestone] = 1;
	}	
	if (isset($sfBugDetail->data->ticket->labels)) {
		$l = 0;
		foreach ($sfBugDetail->data->ticket->labels as $label) {
			$labels[$label] = 1;
			if ($l > 0) {
				array_push($multiple_labels,$sfBugDetail->data->ticket);
			}
			$l++;
		}
	}
	$reported_by[$sfBugDetail->data->ticket->reported_by] = 1;
	$assigned_to[$sfBugDetail->data->ticket->assigned_to] = 1;
	array_push($custom_fields, $sfBugDetail->data->ticket->custom_fields);
	
	// Put the bug details into the bug object
	$sfBugs->data->tickets[$t]->ticket = $sfBugDetail->data->ticket;
	
	$t++;
}

echo '<pre>';
print_r($status);
print_r($priority);
print_r($milestone);
print_r($reported_by);
print_r($assigned_to);
print_r($labels);
print_r($multiple_labels);
print_r($custom_fields);

if (count($api_error_log)) {
	print_r($api_error_log);
	exit();
}

?>
