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

    if [ ! -f "$DAEMON_SCRIPT" ]; then
        # Check relative path
        REL_DAEMON="$(cd "$(dirname "$0")/../bin" 2>/dev/null && pwd)/vitoconnect-daemon.py"
        if [ -f "$REL_DAEMON" ]; then
            DAEMON_SCRIPT="$REL_DAEMON"
        else
            echo "<ERROR> Daemon script not found at $DAEMON_SCRIPT."
            return 1
        fi
    fi

    if [ ! -f "$VENV_PYTHON" ]; then
        # Check relative venv
        REL_VENV="$(cd "$(dirname "$0")/../data/venv/bin" 2>/dev/null && pwd)/python3"
        if [ -f "$REL_VENV" ]; then
            VENV_PYTHON="$REL_VENV"
        elif command -v python3 >/dev/null 2>&1; then
            VENV_PYTHON=$(command -v python3)
        else
            echo "<ERROR> Python venv not found at $VENV_PYTHON."
            return 1
        fi
    fi

    mkdir -p "$(dirname "$LOG_FILE")"

    CURRENT_USER=$(id -u -n 2>/dev/null || whoami)
    echo "Starting $PLUGIN_NAME daemon as user $CURRENT_USER..."

    if [ "$CURRENT_USER" = "root" ]; then
        chown -R loxberry:loxberry "$(dirname "$LOG_FILE")" 2>/dev/null
        su - loxberry -c "nohup \"$VENV_PYTHON\" \"$DAEMON_SCRIPT\" >> \"$LOG_FILE\" 2>&1 &"
    else
        nohup "$VENV_PYTHON" "$DAEMON_SCRIPT" >> "$LOG_FILE" 2>&1 &
    fi
    sleep 1

    if [ -f "$PID_FILE" ]; then
        echo "$PLUGIN_NAME started with PID $(cat "$PID_FILE")."
    else
        PID=$(pgrep -f "vitoconnect-daemon.py" | head -n 1)
        if [ -n "$PID" ]; then
            echo "$PID" > "$PID_FILE" 2>/dev/null
            echo "$PLUGIN_NAME started with PID $PID."
        else
            echo "<WARNING> Daemon started, but PID file not created yet."
        fi
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
    PID=$(pgrep -f "vitoconnect-daemon.py" | head -n 1)
    if [ -n "$PID" ]; then
        echo "$PID" > "$PID_FILE" 2>/dev/null
        echo "$PLUGIN_NAME daemon is RUNNING (PID $PID)."
        return 0
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
