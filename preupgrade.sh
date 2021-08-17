#!/bin/bash

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

echo "<INFO> Creating temporary folders for upgrading"
mkdir /tmp/$ARGV1\_upgrade
mkdir /tmp/$ARGV1\_upgrade/config
mkdir /tmp/$ARGV1\_upgrade/log
mkdir /tmp/$ARGV1\_upgrade/cron
mkdir /tmp/$ARGV1\_upgrade/cron/cron.01min
mkdir /tmp/$ARGV1\_upgrade/cron/cron.03min
mkdir /tmp/$ARGV1\_upgrade/cron/cron.05min
mkdir /tmp/$ARGV1\_upgrade/cron/cron.10min
mkdir /tmp/$ARGV1\_upgrade/cron/cron.15min
mkdir /tmp/$ARGV1\_upgrade/cron/cron.30min
mkdir /tmp/$ARGV1\_upgrade/cron/cron.hourly

echo "<INFO> Backing up existing config files"
cp -v -r $ARGV5/config/plugins/$ARGV3/ /tmp/$ARGV1\_upgrade/config

echo "<INFO> Backing up existing log files"
cp -v -r $ARGV5/log/plugins/$ARGV3/ /tmp/$ARGV1\_upgrade/log

echo "<INFO> Backing up existing cron jobs"
[ -f $ARGV5/system/cron/cron.01min/Vitoconnect ] && cp -v -r $ARGV5/system/cron/cron.01min/Vitoconnect /tmp/$ARGV1\_upgrade/cron/cron.01min/Vitoconnect
[ -f $ARGV5/system/cron/cron.03min/Vitoconnect ] && cp -v -r $ARGV5/system/cron/cron.03min/Vitoconnect /tmp/$ARGV1\_upgrade/cron/cron.03min/Vitoconnect
[ -f $ARGV5/system/cron/cron.05min/Vitoconnect ] && cp -v -r $ARGV5/system/cron/cron.05min/Vitoconnect /tmp/$ARGV1\_upgrade/cron/cron.05min/Vitoconnect
[ -f $ARGV5/system/cron/cron.10min/Vitoconnect ] && cp -v -r $ARGV5/system/cron/cron.10min/Vitoconnect /tmp/$ARGV1\_upgrade/cron/cron.10min/Vitoconnect
[ -f $ARGV5/system/cron/cron.15min/Vitoconnect ] && cp -v -r $ARGV5/system/cron/cron.15min/Vitoconnect /tmp/$ARGV1\_upgrade/cron/cron.15min/Vitoconnect
[ -f $ARGV5/system/cron/cron.30min/Vitoconnect ] && cp -v -r $ARGV5/system/cron/cron.30min/Vitoconnect /tmp/$ARGV1\_upgrade/cron/cron.30min/Vitoconnect
[ -f $ARGV5/system/cron/cron.hourly/Vitoconnect ] && cp -v -r $ARGV5/system/cron/cron.hourly/Vitoconnect /tmp/$ARGV1\_upgrade/cron/cron.hourly/Vitoconnect
# Exit with Status 0
exit 0
