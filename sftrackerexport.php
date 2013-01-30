<?php

/*
 * Retrieve SourceForge tracker items and convert to CSV
 */


require_once 'config.php';

// The date/time this file was run. Used to write to one log file.
$dt_log = date("Ymdhis");

// Set a global error array to log failed api requests
$api_error_log = array();

// Log unexpected field values
$bad_fields_log = array();

// Log usernames encountered
$log_users = array();

// Open log and write the first line
file_put_contents($log_path . $dt_log, date("YmdHis") . ':LOG STARTED' . "\n");

// Loop over each tracker type you want to port
foreach ($bg_issue_type as $ticket_type => $val) {

	// Skip the fallback value declared in the array
	if ($ticket_type == "_default")
		continue;
		
	// Continue executing batches until we run out of tickets
	$type_finished = false;
	$page = 0;
	while (!$type_finished) {
	
		// If a specific page is specified in the config
		// options, let's run that page and quit
		if ($batch_page) {
			$page = $batch_page;
			$type_finished = true;
		}
	
		// Construct the call to the REST API
		$api_query = $ticket_type . '/?limit=' . $batch_size;
		if ($page) {
			$api_query .= '&page=' . $page;
		}
		
		// Retrieve and loop over the tickets
		$sfBugs = new sfQuery();
		$sfBugs->queryAPI($api_query);
		
		// End the loop if we have no more tickets to get
		if (!count($sfBugs->data->tickets)) {
			$type_finished = true;
			continue;
		}
		$t = 0;
		foreach ($sfBugs->data->tickets as $ticket) {

			// Prevent timing out for each ticket
			set_time_limit(600);

			// Retrieve the ticket details
			$sfBugDetail = new sfQuery();
			if ($sfBugDetail->queryAPI($ticket_type . '/' . $ticket->ticket_num)) {
			
				// Fetch details for each post to the ticket
				if (is_array($sfBugDetail->data->ticket->discussion_thread->posts)) {
					$p = 0;
					foreach ($sfBugDetail->data->ticket->discussion_thread->posts as $post) {
						$sfBugDetailPost = new sfQuery();
						if ($sfBugDetailPost->queryAPI($ticket_type . '/_discuss/thread/' . $sfBugDetail->data->ticket->discussion_thread->_id . '/' . $post->slug)) {
							$sfBugDetail->data->ticket->discussion_thread->posts[$p]->api_call = true;
							$sfBugDetail->data->ticket->discussion_thread->posts[$p]->post = $sfBugDetailPost->data->post;
							
						// If our API call fails, we need to put a dummy comment that
						// makes it clear the user needs to check the old link.
						} else {
							$sfBugDetail->data->ticket->discussion_thread->posts[$p]->api_call = false;
							$sfBugDetailPost->logAPIError("Missing Comment");
						}
						$p++;
					}
				}
				
				// Put the bug details into the bug object
				$sfBugs->data->tickets[$t]->ticket = $sfBugDetail->data->ticket;
			}
			
			$t++;
		}

		// Begin writing to CSV file
		$csv = array();
		$fp = fopen($export_path . $ticket_type . '_page-' . $page . '.csv', 'w');
		$header = array(
			"title",
			"project",
			"descr",
			"state",
			"status",
			"posted_by",
			"assigned",
			"assigned_type",
			"issue_type",
			"priority",
			"category",
			"milestone",
//			"posted" // @EXTEND Not supported by default in The Bug Genie 3.2.4, see README.txt
			);
			
		// Add an issue number for bugs
		// @EXTEND Not supported by default in The Bug Genie 3.2.4, see README.txt
//		if ($ticket_type == 'bugs') {
//			$header[] = "issue_no";
//		}
		fputcsv($fp,$header);
		$i = 0;
		$cats_test = array();
		foreach ($sfBugs->data->tickets as $ticket) {
		
			// Don't add a line if this ticket is missing (API call failed)
			if (!isset($ticket->ticket)) 
				continue;

			// Handle labels because [0] offset can throw a notice if
			// the array doesn't exist
			if (count($ticket->ticket->labels)) {
				$label = $ticket->ticket->labels[0];
			} else {
				$label = '';
			}
			
			// Handle milestone because it can throw a notice if
			// key doesn't exist
			if (isset($ticket->ticket->custom_fields->_milestone)) {
				$milestone = $ticket->ticket->custom_fields->_milestone;
			} else {
				$milestone = '';
			}

			// Build ticket array
			$csv_item = array(
				$sfBugs->formatBGSummary($ticket->ticket->summary),
				$bg_proj_id,
				$sfBugs->formatBGDescription($ticket, $ticket_type),
				$sfBugs->formatBGField($ticket->ticket->status, $bg_state),
				$sfBugs->formatBGField($ticket->ticket->status, $bg_status),
				$sfBugs->formatBGUserField($ticket->ticket->reported_by),
				$sfBugs->formatBGUserField($ticket->ticket->assigned_to),
				1,
				$sfBugs->formatBGField($ticket_type, $bg_issue_type),
				$sfBugs->formatBGField($ticket->ticket->custom_fields->_priority, $bg_priority),
				$sfBugs->formatBGField($label, $bg_category),
				$sfBugs->formatBGField($milestone, $bg_milestone),
//				$sfBugs->formatBGDateField($ticket->ticket->created_date) // @EXTEND Not supported by default in The Bug Genie 3.2.4, see README.txt
			);
			
			// Add an issue number only for bugs
			// @EXTEND Not supported by default in The Bug Genie 3.2.4, see README.txt
//			if ($ticket_type == 'bugs') {
//				$csv_item[] = $sfBugs->formatBGIssueNoField($ticket->ticket_num);
//			}
			fputcsv($fp,$csv_item);
		}
		fclose($fp);
		
		// Write errors to log
		if (count($api_error_log) || count($bad_fields_log)) {
			foreach ($api_error_log as $api_error) {
				file_put_contents($log_path . $dt_log,
					date("YmdHis") . ':' . 
					"API_ERROR:" . $ticket_type . ':' . $page .
					":" . $api_error['id'] . 
					":" . $api_error['message'] . 
					":" . $api_error['endpoint'] . 
					":" . $api_error['result'] . "\n",
					FILE_APPEND
					);
			}
			
			foreach ($bad_fields_log as $bad_field) {
				$field_details = array();
				foreach ($bad_field as $key => $val) {
					$field_details[] = $key . '[[' . $val . ']]'; 
				}
				file_put_contents($log_path . $dt_log,
					date("YmdHis") . ':' . 
					"FIELD_ERROR:" . $ticket_type . ':' . $page .
					":" . join(":", $field_details) . "\n",
					FILE_APPEND
					);
			}
			
			// Clear the error arrays
			$api_error_log = array();
			$bad_fields_log = array();
		}
		
		// Increase the page count for the next api call
		$page++;
	}
}

file_put_contents($log_path . $dt_log, "FINISHED", FILE_APPEND);
echo "\n\nfinished\n";

?>