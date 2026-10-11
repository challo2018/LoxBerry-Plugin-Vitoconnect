#!/usr/bin/env python3
"""
PyViCare Manager for LoxBerry Vitoconnect Plugin
Handles PyViCare package status, PyPI version checks, upgrades, and venv management.
"""

import os
import sys
import json
import argparse
import subprocess
import urllib.request
import urllib.error
from pathlib import Path

# Standard LoxBerry paths
LBHOMEDIR = os.environ.get("LBHOMEDIR", "/opt/loxberry")
PLUGIN_NAME = "Vitoconnect"
DEFAULT_VENVPATTH = Path(LBHOMEDIR) / "data" / "plugins" / PLUGIN_NAME / "venv"
PID_FILE = Path("/run/shm") / f"{PLUGIN_NAME}.pid"
FALLBACK_PID_FILE = Path("/tmp") / f"{PLUGIN_NAME}.pid"


def get_venv_path(custom_path=None):
    if custom_path:
        return Path(custom_path)
    # Check environment or default
    lbpdata = os.environ.get("LBPDATA")
    if lbpdata:
        return Path(lbpdata) / PLUGIN_NAME / "venv"
    # Local fallback relative to repo if running locally
    local_venv = Path(__file__).resolve().parent.parent / "data" / "venv"
    if local_venv.exists():
        return local_venv
    return DEFAULT_VENVPATTH


def get_installed_version(venv_dir: Path):
    """Retrieve currently installed PyViCare version from venv."""
    pip_bin = venv_dir / "bin" / "pip"
    python_bin = venv_dir / "bin" / "python3"
    if not python_bin.exists():
        python_bin = venv_dir / "bin" / "python"
    
    if not python_bin.exists():
        return None

    try:
        cmd = [str(python_bin), "-c", "import PyViCare; print(PyViCare.__version__)"]
        res = subprocess.run(cmd, capture_output=True, text=True, timeout=10)
        if res.returncode == 0 and res.stdout.strip():
            return res.stdout.strip()
    except Exception:
        pass

    # Fallback to pip show
    if pip_bin.exists():
        try:
            cmd = [str(pip_bin), "show", "PyViCare"]
            res = subprocess.run(cmd, capture_output=True, text=True, timeout=10)
            if res.returncode == 0:
                for line in res.stdout.splitlines():
                    if line.startswith("Version:"):
                        return line.split(":", 1)[1].strip()
        except Exception:
            pass

    return None


def get_pypi_version():
    """Fetch latest PyViCare release information from PyPI API."""
    url = "https://pypi.org/pypi/PyViCare/json"
    req = urllib.request.Request(
        url,
        headers={"User-Agent": "LoxBerry-Vitoconnect/2.0"},
    )
    try:
        with urllib.request.urlopen(req, timeout=5) as response:
            if response.status == 200:
                data = json.loads(response.read().decode("utf-8"))
                return {
                    "version": data.get("info", {}).get("version"),
                    "summary": data.get("info", {}).get("summary"),
                    "releases_count": len(data.get("releases", {})),
                }
    except Exception as e:
        return {"error": str(e)}
    return {"error": "Unknown error querying PyPI"}


def is_daemon_running():
    """Check if vitoconnect daemon is currently running."""
    for pidfile in [PID_FILE, FALLBACK_PID_FILE]:
        if pidfile.exists():
            try:
                pid = int(pidfile.read_text().strip())
                # Check if process with this PID is running
                os.kill(pid, 0)
                return True
            except (ValueError, ProcessLookupError, PermissionError):
                continue
    return False


def setup_venv(venv_dir: Path):
    """Create virtual environment and install PyViCare and paho-mqtt."""
    venv_dir.parent.mkdir(parents=True, exist_ok=True)
    if not venv_dir.exists():
        cmd = [sys.executable, "-m", "venv", str(venv_dir)]
        res = subprocess.run(cmd, capture_output=True, text=True)
        if res.returncode != 0:
            return {"success": False, "error": f"Failed to create venv: {res.stderr}"}

    pip_bin = venv_dir / "bin" / "pip"
    if not pip_bin.exists():
        return {"success": False, "error": "pip not found in venv"}

    # Upgrade pip and install packages
    upgrade_cmd = [str(pip_bin), "install", "--upgrade", "pip"]
    subprocess.run(upgrade_cmd, capture_output=True)

    install_cmd = [str(pip_bin), "install", "PyViCare", "paho-mqtt"]
    res = subprocess.run(install_cmd, capture_output=True, text=True)
    if res.returncode == 0:
        version = get_installed_version(venv_dir)
        return {"success": True, "installed_version": version}
    else:
        return {"success": False, "error": res.stderr}


def upgrade_pyvicare(venv_dir: Path, target_version=None):
    """Upgrade or install specific version of PyViCare inside venv."""
    pip_bin = venv_dir / "bin" / "pip"
    if not pip_bin.exists():
        # Try setting up venv first
        setup_res = setup_venv(venv_dir)
        if not setup_res.get("success"):
            return setup_res

    pkg = f"PyViCare=={target_version}" if target_version else "PyViCare"
    cmd = [str(pip_bin), "install", "--upgrade", pkg]
    try:
        res = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
        if res.returncode == 0:
            new_version = get_installed_version(venv_dir)
            return {
                "success": True,
                "message": f"Successfully updated PyViCare to {new_version}",
                "version": new_version,
            }
        else:
            return {
                "success": False,
                "error": res.stderr or res.stdout,
            }
    except Exception as e:
        return {"success": False, "error": str(e)}


def main():
    parser = argparse.ArgumentParser(description="PyViCare Package & Venv Manager for LoxBerry")
    parser.add_argument("--info", action="store_true", help="Print status info as JSON")
    parser.add_argument("--upgrade", action="store_true", help="Upgrade PyViCare to latest version")
    parser.add_argument("--install-version", type=str, help="Install specific PyViCare version")
    parser.add_argument("--setup-venv", action="store_true", help="Initialize venv and required packages")
    parser.add_argument("--venv", type=str, help="Custom path to venv")

    args = parser.parse_args()
    venv_dir = get_venv_path(args.venv)

    if args.setup_venv:
        result = setup_venv(venv_dir)
        print(json.dumps(result, indent=2))
        return 0 if result.get("success") else 1

    if args.upgrade:
        result = upgrade_pyvicare(venv_dir)
        print(json.dumps(result, indent=2))
        return 0 if result.get("success") else 1

    if args.install_version:
        result = upgrade_pyvicare(venv_dir, target_version=args.install_version)
        print(json.dumps(result, indent=2))
        return 0 if result.get("success") else 1

    # Default is --info
    installed = get_installed_version(venv_dir)
    pypi_data = get_pypi_version()
    latest = pypi_data.get("version")

    update_available = False
    if installed and latest:
        update_available = (installed != latest)

    status = {
        "installed_version": installed,
        "latest_pypi_version": latest,
        "update_available": update_available,
        "venv_exists": venv_dir.exists(),
        "venv_path": str(venv_dir),
        "daemon_running": is_daemon_running(),
    }
    print(json.dumps(status, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())
