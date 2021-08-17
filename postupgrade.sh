#!/bin/sh

# To use important variables from command line use the following code:
ARGV0=$0 # Zero argument is shell command
# echo "<INFO> Command is: $ARGV0"

ARGV1=$1 # First argument is temp folder during install
# echo "<INFO> Temporary folder is: $ARGV1"

ARGV2=$2 # Second argument is Plugin-Name for scipts etc.
# echo "<INFO> (Short) Name is: $ARGV2"

ARGV3=$3 # Third argument is Plugin installation folder
# echo "<INFO> Installation folder is: $ARGV3"

ARGV4=$4 # Forth argument is Plugin version
# echo "<INFO> Installation folder is: $ARGV4"

ARGV5=$5 # Fifth argument is Base folder of LoxBerry
# echo "<INFO> Base folder is: $ARGV5"

echo "<INFO> Copy back existing config files"
cp -v -r /tmp/$ARGV1\_upgrade/config/$ARGV3/* $ARGV5/config/plugins/$ARGV3/ 

echo "<INFO> Copy back existing log files"
cp -v -r /tmp/$ARGV1\_upgrade/log/$ARGV3/* $ARGV5/log/plugins/$ARGV3/ 

echo "<INFO> Copy back existing cron jobs"
[ -f /tmp/$ARGV1\_upgrade/cron/cron.01min/Vitoconnect ] && cp -v -r /tmp/$ARGV1\_upgrade/cron/cron.01min/Vitoconnect $ARGV5/system/cron/cron.01min/Vitoconnect
[ -f /tmp/$ARGV1\_upgrade/cron/cron.03min/Vitoconnect ] && cp -v -r /tmp/$ARGV1\_upgrade/cron/cron.03min/Vitoconnect $ARGV5/system/cron/cron.03min/Vitoconnect
[ -f /tmp/$ARGV1\_upgrade/cron/cron.05min/Vitoconnect ] && cp -v -r /tmp/$ARGV1\_upgrade/cron/cron.05min/Vitoconnect $ARGV5/system/cron/cron.05min/Vitoconnect
[ -f /tmp/$ARGV1\_upgrade/cron/cron.10min/Vitoconnect ] && cp -v -r /tmp/$ARGV1\_upgrade/cron/cron.10min/Vitoconnect $ARGV5/system/cron/cron.10min/Vitoconnect
[ -f /tmp/$ARGV1\_upgrade/cron/cron.15min/Vitoconnect ] && cp -v -r /tmp/$ARGV1\_upgrade/cron/cron.15min/Vitoconnect $ARGV5/system/cron/cron.15min/Vitoconnect
[ -f /tmp/$ARGV1\_upgrade/cron/cron.30min/Vitoconnect ] && cp -v -r /tmp/$ARGV1\_upgrade/cron/cron.30min/Vitoconnect $ARGV5/system/cron/cron.30min/Vitoconnect
[ -f /tmp/$ARGV1\_upgrade/cron/cron.hourly/Vitoconnect ] && cp -v -r /tmp/$ARGV1\_upgrade/cron/cron.hourly/Vitoconnect $ARGV5/system/cron/cron.hourly/Vitoconnect

echo "<INFO> Remove temporary folders"
rm -r /tmp/$ARGV1\_upgrade

# Exit with Status 0
exit 0
