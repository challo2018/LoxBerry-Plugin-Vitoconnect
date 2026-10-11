#!/bin/bash
# postinstall.sh - Vitoconnect Plugin

# Arguments from LoxBerry
COMMAND=$0
PTEMPDIR=$1
PSHNAME=$2
PDIR=$3
PVERSION=$4
PTEMPPATH=$6

# Combine with /etc/environment
if [ -f /etc/environment ]; then
    . /etc/environment
fi

PDATA=${LBPDATA}/$PDIR
PBIN=${LBPBIN}/$PDIR
PLOG=${LBPLOG}/$PDIR
PDAEMON=${LBPDAEMON}/$PDIR

echo "<INFO> Vitoconnect postinstall: Target dir is $PDIR (Version $PVERSION)"

# Create plugin data and log directories
mkdir -p "$PDATA" "$PLOG"

# Setup Python virtual environment in $PDATA/venv
VENV_DIR="$PDATA/venv"
echo "<INFO> Überprüfe Python Virtual Environment in $VENV_DIR..."

if [ ! -d "$VENV_DIR" ]; then
    echo "<INFO> Erstelle isoliertes Python venv..."
    python3 -m venv "$VENV_DIR"
    if [ $? -ne 0 ]; then
        echo "<WARNING> Konnte venv nicht erstellen. Bitte sicherstellen, dass python3-venv installiert ist."
    fi
fi

if [ -f "$VENV_DIR/bin/pip" ]; then
    echo "<INFO> Installiere/Aktualisiere PyViCare und paho-mqtt im venv..."
    "$VENV_DIR/bin/pip" install --upgrade pip
    "$VENV_DIR/bin/pip" install PyViCare paho-mqtt
    if [ $? -eq 0 ]; then
        echo "<OK> PyViCare und Abhängigkeiten erfolgreich installiert!"
    else
        echo "<WARNING> Installation von PyViCare schlug fehl. Kann über das Webfrontend nachgeholt werden."
    fi
fi

# Ensure permissions on bin and daemon scripts
if [ -d "$PBIN" ]; then
    chmod +x "$PBIN"/* 2>/dev/null
fi
if [ -d "$PDAEMON" ]; then
    chmod +x "$PDAEMON"/* 2>/dev/null
fi

echo "<OK> Vitoconnect Installation abgeschlossen."
exit 0
