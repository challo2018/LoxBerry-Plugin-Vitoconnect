#!/bin/bash
# LoxBerry Daemon for Vitoconnect Plugin
# Starts and monitors vitoconnect-daemon.py inside plugin venv

PLUGIN_NAME="Vitoconnect"

# Load LoxBerry environment
if [ -f /etc/environment ]; then
    . /etc/environment
fi

LBHOMEDIR=${LBHOMEDIR:-"/opt/loxberry"}
LBPDATA=${LBPDATA:-"$LBHOMEDIR/data/plugins"}
LBPBIN=${LBPBIN:-"$LBHOMEDIR/bin/plugins"}
LBPLOG=${LBPLOG:-"$LBHOMEDIR/log/plugins"}

VENV_PYTHON="$LBPDATA/$PLUGIN_NAME/venv/bin/python3"
DAEMON_SCRIPT="$LBPBIN/$PLUGIN_NAME/vitoconnect-daemon.py"
LOG_FILE="$LBPLOG/$PLUGIN_NAME/daemon.log"
PID_FILE="/run/shm/${PLUGIN_NAME}.pid"

# Fallback PID file
if [ ! -d "/run/shm" ]; then
    PID_FILE="/tmp/${PLUGIN_NAME}.pid"
fi

start() {
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if kill -0 "$PID" 2>/dev/null; then
            echo "$PLUGIN_NAME daemon is already running with PID $PID."
            return 0
        fi
        rm -f "$PID_FILE"
    fi

    if [ ! -f "$VENV_PYTHON" ]; then
        echo "<ERROR> Python venv not found at $VENV_PYTHON. Run postinstall.sh or pyvicare-manager.py --setup-venv first."
        return 1
    fi

    if [ ! -f "$DAEMON_SCRIPT" ]; then
        echo "<ERROR> Daemon script not found at $DAEMON_SCRIPT."
        return 1
    fi

    mkdir -p "$(dirname "$LOG_FILE")"
    chown -R loxberry:loxberry "$(dirname "$LOG_FILE")" 2>/dev/null

    echo "Starting $PLUGIN_NAME daemon..."
    # Start as user loxberry
    su - loxberry -c "nohup $VENV_PYTHON $DAEMON_SCRIPT >> $LOG_FILE 2>&1 &"
    sleep 1

    if [ -f "$PID_FILE" ]; then
        echo "$PLUGIN_NAME started with PID $(cat "$PID_FILE")."
    else
        echo "<WARNING> Daemon started, but PID file not created yet."
    fi
}

stop() {
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        echo "Stopping $PLUGIN_NAME daemon (PID $PID)..."
        kill -TERM "$PID" 2>/dev/null
        for i in {1..10}; do
            if ! kill -0 "$PID" 2>/dev/null; then
                rm -f "$PID_FILE"
                echo "$PLUGIN_NAME stopped."
                return 0
            fi
            sleep 1
        done
        echo "Process did not stop gracefully. Killing..."
        kill -KILL "$PID" 2>/dev/null
        rm -f "$PID_FILE"
    else
        echo "$PLUGIN_NAME daemon is not running (no PID file)."
        # Clean up any orphan processes
        pkill -f "vitoconnect-daemon.py" 2>/dev/null
    fi
}

status() {
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if kill -0 "$PID" 2>/dev/null; then
            echo "$PLUGIN_NAME daemon is RUNNING (PID $PID)."
            return 0
        fi
    fi
    echo "$PLUGIN_NAME daemon is STOPPED."
    return 1
}

case "$1" in
    start)
        start
        ;;
    stop)
        stop
        ;;
    restart)
        stop
        sleep 1
        start
        ;;
    status)
        status
        ;;
    *)
        # Default action when invoked by LoxBerry boot sequence
        start
        ;;
esac

exit 0
