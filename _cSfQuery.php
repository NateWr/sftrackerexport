<?php

/*
 * Class to handle Sourceforge API queries and results
 */
class sfQuery {

	// Base URL to query
	private $base_url = 'http://sourceforge.net/rest/p/yourproject/';
	
	// API target and results limit
	public $endpoint = null;
	
	// Raw response from the server
	public $result = null;
	
	// Raw response turned into an array with json_decode()
	public $data = null;
	
	// Known users. Stores found usernames to prevent duplicate SQL queries
	public $known_users = array();
	
	// Backup issue numbers. If for some reason we fail to retrieve a
	// Sourceforge bug number, it will issue a number starting with this
	// and incrementing each time.
	public $safe_issue_no = 10000;
	
	// Log errors in the queries
	public $errors = array();
	
	// Log errors in adding users
	public $errors_users = array();
	
	/*
	 * Override defaults if desired
	 * @defaults = (assoc array) name/value pairs to override default settings
	 */
	function __construct($defaults = null) {
		if (isset($defaults)) {
			if (isset($defaults['base_url'])) $this->base_url = $defaults['base_url'];
		}
	}
	
	/*
	 * Retrieve API URL result
	 * @endpoint = (string) API target (ex - 'bugs')
	 */
	public function queryAPI($endpoint = null) {
		if (!$this->endpoint) $this->endpoint = $endpoint;
		if (!$this->endpoint) {
			$this->logAPIError("No endpoint specified");
			return false;
		}
		$this->result = file_get_contents($this->base_url . $this->endpoint);
		$this->data = json_decode($this->result);
		if (json_last_error() == JSON_ERROR_NONE) {
			$this->data = json_decode($this->result);
			return true;
		} else {
			$this->logAPIError();
			return false;
		}
	}

	/*
	 * Log API error
	 * @message = (string) optional error message
	 */
	public function logAPIError($message = null) {
		global $api_error_log;
		global $ticket;
		array_push($api_error_log, 
			array(
				"id" => $ticket->ticket_num,
				"endpoint" => $this->endpoint,
				"message" => $message
				)
			);
	}
	
	private function getTable($table) {
		global $db_prefix;
		return $db_prefix . $table;
	}
	
	/*
	 * Strip trailing backslashes (\) from a string. A trailing 
	 * backslash can break the CSV file
	 * @str = (string) String to strip
	 * @NOTE: it only strips one \, no recursive checks yet
	 */
	public function stripTrailingBackslash($str) {
		if (substr($str, -1) == '\\') {
			return substr($str, 0, -1);
		}
		return $str;
	}
	
	/*
	 * Return the summary. Relies on a default in case there is
	 * no valid summary info.
	 * @title = (string) value returned by sourceforge api
	 */
	public function formatBGSummary($title) {
		if (!trim($title)) {
			return $bg_default_title;
		} else {
			return $this->stripTrailingBackslash($title);
		}
	}
	
	/*
	 * Add comments to a description entry
	 * @ticket = (stdObject) ticket data from sourceforge
	 * @ticket_type = (string) type of ticket (bugs, feature-requests, patches)
	 */
	public function formatBGDescription($ticket, $ticket_type) {
	
		// Note that it has been imported and link to SF bug
		$ticket->ticket->description = "[http://sourceforge.net/p/ufoai/" . $ticket_type . "/" . $ticket->ticket_num . " Item " . $ticket->ticket_num . "] imported from sourceforge.net tracker on " . date("Y-m-d H:i:s") . "\r\n\r\n" . $ticket->ticket->description;
		
		// Add comments
		$comments = array();
		$comment_dates = array();
		$i = 0;
		foreach ($ticket->ticket->discussion_thread->posts as $post) {
		
			// In some cases, the API will always fail to
			// return a comment. If the API call failed, then
			// $ticket->ticket->discussion_thread->posts->api_call will
			// be false. Otherwise it will be true. If we're missing
			// comment data, then we'll enter a dummy comment that explains
			// this.
			if ($post->api_call === false) {
				$comments[$i] = "\n====== Missing Comment Alert ======\n";
				$comments[$i] .= "\nThe importer failed to retrieve a comment in this thread. Please view the old ticket link above for full discussion details.";
				$dt = new DateTime();
				$comments_dates[$dt->getTimestamp()] = $i;
			} else {
				$comments[$i] = "\n====== " . $post->post->author . " (" . $post->post->timestamp . ") ======\n";
				$comments[$i] .= "\n" . $post->post->text;
				$dt = new DateTime($post->post->timestamp);
				$comments_dates[$dt->getTimestamp()] = $i;
			}
			$i++;
		}
		if ($i) {
			ksort($comments_dates, SORT_NUMERIC);
			$ordered_comments = array();
			foreach ($comments_dates as $date => $c_key) {
				$ordered_comments[] = $comments[$c_key];
			}
			$ticket->ticket->description .= "\n===== Comments Ported from Sourceforge =====\n";
			$ticket->ticket->description .= join(" ", $ordered_comments);
		}
				
		return $this->stripTrailingBackslash($ticket->ticket->description);
	}
	
	/*
	 * Return the bug genie value for sourceforge field
	 * @sf_val = (string) value returned by sourceforge api
	 * @valid_val = (array) associative array of known values and
	 *					the matching value for bug genie
	 */
	public function formatBGField($sf_val, $valid_val) {
		if (!trim($sf_val)) {
			return $valid_val['_default'];
		} else if (isset($valid_val[$sf_val])) {
			return $valid_val[$sf_val];
		} else {
			global $bad_fields_log;
			array_push($bad_fields_log, array("sf_val" => $sf_val, "valid_val" => $valid_val));
			return $valid_val['_default'];
		}
	}
	
	/*
	 * Returns timestamp for a sourceforge date
	 * @date = (string) date returned by sourceforge api
	 */
	public function formatBGDateField($date) {
		if (!$date = new DateTime($date)) {
			global $bad_fields_log;
			array_push($bad_fields_log, array("date" => $date));
			$date = new DateTime();
		}
		return $date->getTimestamp();
	}
	
	/*
	 * Returns issue no (former sourceforge but ticket id)
	 * @no = (string) date returned by sourceforge api
	 */
	public function formatBGIssueNoField($no) {
		if (!trim($no) || (string) (int) $no === $no) {
			$safe_issue_no++;
			return $safe_issue_no;
		} else {
			return (int) $no;
		}
	}
	
	/*
	 * Returns user ID for a user and creates a user if one doesn't exist.
	 * @user = (string) username returned by sourceforge api
	 */
	public function formatBGUserField($user) {
	
		// Log users pass
		global $log_users;
		$log_users[] = $user;
		
		// Return the ID if we've already saved it
		if (isset($this->known_users[$user]))
			return $this->known_users[$user];
			
		// Return the default ID if we're meant to skip this user or if the user
		// info is empty (could be a failed API call)
		global $user_skip;
		if (isset($user_skip[$user]) || !trim($user)) {
			global $user_default;
			return $user_default;
		}
			
		// Get the ID of a user if we can
		$q = "SELECT id FROM " . $this->getTable('users') . " WHERE username='" . mysql_real_escape_string($user) . "'";
		$r = mysql_query($q);
		if ($r && mysql_num_rows($r) > 0) {
			$row = mysql_fetch_assoc($r);
			
			// Save this user before returning
			array_push($this->known_users, array($user => $row['id']));
			
			return $row['id'];
		}
		
		// Otherwise add the user
		global $user_group;
		global $user_scope;
		global $user_settings;
		global $user_dashboards;
		
		// Assempble query
		// @note: enabled and deleted are important to make your users appear
		// 			in the admin panel.
		$q_add = "INSERT INTO " . $this->getTable('users') . " SET 
					username='" . mysql_real_escape_string($user) . "',
					realname='" . mysql_real_escape_string($user) . "',
					buddyname='" . mysql_real_escape_string($user) . "',
					private_email=1,
					customstate=0,
					language='',
					avatar='',
					use_gravatar=0,
					lastseen=0,
					enabled=1,
					openid_locked=0,
					activated=0,
					deleted=0";
		$r_add = mysql_query($q_add);
		if (!$r_add) {
			$this->errors_users[] = $q_add;
		} else {
		
			// Get User ID
			$q_id = 'SELECT max(id) as id FROM ' . $this->getTable('users');
			$r_id = mysql_query($q_id);
			if (!$r_id) {
				$this->errors_users[] = $q_id;
			} else {
				$id_row = mysql_fetch_array($r_id);
				$id = $id_row['id'];
				
				// Add User scope
				$q_scope = 'INSERT INTO ' . $this->getTable('userscopes') . ' SET user_id=' . $id ;
				$q_scope .= ', confirmed=1';
				$q_scope .= ', group_id=' . $user_group;
				$q_scope .= ', scope=' . $user_scope;
				$r_assign = mysql_query($q_scope);
				if (!$r_assign) {
					$this->errors_users[] = $q_scope;
				} else {
					
					// Add user settings
					foreach ($user_settings as $setting) {
						$q_settings = 'INSERT INTO ' . $this->getTable('settings') . ' SET uid=' . $id . ', scope=' . $user_scope;
						$q_settings .= ', name="' . $setting['name'] . '"';
						$q_settings .= ', module="' . $setting['module'] . '"';
						$q_settings .= ', value="' . $setting['value'] . '"';
						$r_settings = mysql_query($q_settings);
						if (!$r_settings) {
							$this->errors_users[] = $q_settings;
						}
					}
					
					// Add user dashboards
					foreach ($user_dashboards as $dashboard) {
						$q_dashboards = 'INSERT INTO ' . $this->getTable('dashboard_views') . ' SET tid=' . $id . ', scope=' . $user_scope;
						$q_dashboards .= ', name="' . $dashboard['name'] . '"';
						$q_dashboards .= ', view="' . $dashboard['view'] . '"';
						$q_dashboards .= ', pid="' . $dashboard['pid'] . '"';
						$q_dashboards .= ', target_type="' . $dashboard['target_type'] . '"';
						$r_dashboards = mysql_query($q_dashboards);
						if (!$r_dashboards) {
							$this->errors_users[] = $q_dashboards;
						}
					}
				}
			}
		}
		
		// If we still have no ID, return the default
		if (!isset($id)) {
			global $user_default;
			return $user_default;
		} else {
			return $id;
		}
	}
}

?>