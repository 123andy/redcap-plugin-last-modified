<?php
	
/**
*	This script tries to output a list of last modified dates for records in a project
*	Requires:
*		token = API TOKEN
*	Optional:
*		record = a single record id, or an array of record ids to filter by
*		format = csv or json (default) to control the output format
*		delta = restrict to records last modified in past h:m:s
*		since = log timestamp to begin search on (yyyymmddhhmmss).  If specified, only times >= this will be used.
*	Output contains:
*		pk (primary key or record_id)
*		event (UPDATE/INSERT/DELETE)
*		ts (timestamp in format YmdHis)
*
*	Andrew Martin - Stanford University - Use at your own peril!
*
**/

error_reporting(E_ALL);

// Set Log File
$log_file = "/var/log/redcap/last_modified.log";

// Disable REDCap's authentication (will use API tokens for authentication)
define("NOAUTH", true);

// Set constant to denote that this is an API call
define("API", true);

// Include REDCap Connect
include "../../redcap_connect.php";

// logIt('lastModified Post: ' . print_r($_POST,true), "DEBUG");

// Validate inputs
$format = $_POST['format'] == 'csv' ? 'csv' : 'json';
$token = (isset($_POST['token']) ? $_POST['token'] : null);
$record = (isset($_POST['record']) ? $_POST['record'] : null);
$delta = (isset($_POST['delta']) ? $_POST['delta'] : null);
$since = (isset($_POST['since']) ? $_POST['since'] : null);

// Force record into an array if it isn't empty and isn't already array
if (!empty($record) && !is_array($record)) $record = array($record);

// Lookup project_id and validated token
$query = "SELECT project_id, username FROM redcap_user_rights WHERE api_token = '" . db_real_escape_string($token) . "';";
$q = db_query($query);
if (db_num_rows($q) == 0) stopError("Invalid API Token");
$row = db_fetch_assoc($q);
$project_id = $row['project_id'];
$username = $row['username'];
//logIt ("Hello $username with project $project_id","DEBUG");

// Validate delta if supplied
$pattern = '/^\d+:\d+:\d+$/';
if (!empty($delta) && !preg_match($pattern, $delta)) stopError("Invalid format for delta: h:m:s");
$delta_sql = empty($delta) ? '' : sprintf(" AND ts > round(SUBTIME(CURRENT_TIMESTAMP, '%s') + 0)", db_real_escape_string($delta));

// Validate since if supplied
$pattern = '/^\d{14}$/';
if (!empty($since) && !preg_match($pattern, $since)) stopError("Invalid format for since, expecting yyyymmddhhmmss");
$since_sql = empty($since) ? '' : sprintf(" AND ts >= %s", db_real_escape_string($since));

// Create where clause to limit by record if set
$record_sql = empty($record) ? '' : " AND pk in ('" . implode("','",$record) . "')";
//logIt("Parsed record sql is: $record_sql","DEBUG");

// Look up last modified timestamp
$sql = sprintf(
	"SELECT a.pk, a.event, a.ts
	FROM
		redcap_log_event a
		INNER JOIN (
			SELECT max(log_event_id) as log_event_id
			FROM redcap_log_event
			WHERE project_id = %s
			AND event IN ('UPDATE','INSERT','DELETE')
			$record_sql
			$delta_sql
			$since_sql
		GROUP BY pk) b
		ON	a.log_event_id = b.log_event_id
	ORDER BY a.ts asc;",
	db_real_escape_string($project_id)
);
logIt("SQL: $sql","DEBUG");
$q = db_query($sql);
$records = array();
while ($row = db_fetch_assoc($q)) $records[] = $row;
//logIt ("Count of results: " . count($records));

// Output Results
if ($format == 'csv') {
	$output = fopen("php://output",'w') or die("Can't open php://output");
	header("Content-Type:application/csv"); 
	header("Content-Disposition:attachment;filename=last_modified.csv"); 
	fputcsv($output, array('pk','event','timestamp'));
	foreach($records as $row) {
		fputcsv($output, $row);
	}
	fclose($output) or die("Can't close php://output");
} else {
	print json_encode($records);
}

// Log Results
$logMsg = "Exported " . count($records) . " record(s) last modified info" . (empty($delta) ? '' : " from past $delta");

//log_event($sql, $table, $event, $record, $display, $descrip="", $change_reason="", $userid_override="", $project_id_override="", $useNOW=true) 
log_event($sql, "", "OTHER", "", $logMsg, "Last Modified Plugin", "", $username, $project_id);




## HELPER FUNCTIONS
function stopError($msg) {
	global $format;
	//logIt('Foramt is ' . $format,"DEBUG");
	if ($format == 'csv') {
		//print $msg;
		die($msg);
	} else {
		$msg = json_encode(array('error'=>$msg));
		die($msg);
	}
}

// Log to file
function logIt($msg, $level = "INFO") {
	global $project_id, $log_file, $record;
	file_put_contents( $log_file,	date( 'Y-m-d H:i:s' ) . "\t" . $level . "\t" . $project_id . "\t" . $record . "\t" . $msg . "\n", FILE_APPEND );
}

?>