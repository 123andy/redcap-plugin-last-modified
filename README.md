# redcap-plugin-last-modified
An API-like plugin that returns record modification timestamps and details.  

This script tries to output a list of last modified dates for records in a project.
This is used by a cron task to keep data in a large redcap project in sync with an outside system.  Rather than re-pushing the entire dataset every interval, only those records that have been modified are pushed to the external system.

#####Requires:
*	token = API TOKEN

#####Optional:
*	record = a single record id, or an array of record ids to filter by
*	format = csv or json (default) to control the output format
*	delta = restrict to records last modified in past h:m:s
*	since = log timestamp to begin search on (yyyymmddhhmmss).  If specified, only times >= this will be used.

#####Output contains:
*	pk (primary key or record_id)
*	event (UPDATE/INSERT/DELETE)
*	ts (timestamp in format YmdHis)

Andrew Martin - Stanford University - Use at your own peril!

## Example Call
######token: ABC123123CBA
######delta: 10:0:0
######format: json

## Example Output
(csv format)
```
pk,event,timestamp
R04-0002,UPDATE,20160208131413
R04-0038,UPDATE,20160208131439
R04-0023,UPDATE,20160208165741
R04-0057,UPDATE,20160208180931
R02-0016,UPDATE,20160210102744
R02-0030,UPDATE,20160210103921
```

(json format)
```
[
  {"pk":"R06-0003","event":"UPDATE","ts":"20160218052431"},
  {"pk":"R06-0010","event":"UPDATE","ts":"20160218052459"},
  {"pk":"R06-0007","event":"UPDATE","ts":"20160218054824"}
]
```