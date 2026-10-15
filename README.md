# Viessmann Vitoconnect Plugin für LoxBerry (v2.0)

Ein modernes LoxBerry-Plugin zur nahtlosen Anbindung von Viessmann Heizsystemen (Gas, Öl, Wärmepumpen, Hybrid) an den Loxone Miniserver über die Viessmann Developer API und MQTT.

---

## Highlights in Version 2.0

* **PyViCare Core**: Umstellung des Abfrage- und Steuerkerns auf die bewährte Python-Bibliothek [`PyViCare`](https://github.com/openviess/PyViCare) (dieselbe Bibliothek, die auch von Home Assistant genutzt wird).
* **In-Place PyViCare Updates**: PyViCare kann jederzeit direkt über das LoxBerry Webfrontend auf die neueste PyPI-Version aktualisiert werden – **ohne auf ein neues LoxBerry-Plugin-Release warten zu müssen**.
* **Isoliertes Python-venv**: Installation erfolgt in einem plugin-eigenen Virtual Environment (`data/plugins/Vitoconnect/venv`), konform mit Debian PEP 668.
* **Echtes bidirektionales MQTT**:
  * **Senden**: Überträgt Sensoren und Statuswerte bei Änderung (*Change Detection*) an den MQTT Broker.
  * **Steuern**: Miniserver kann Zieltemperaturen, Betriebsarten und Warmwasser-Schnellaufheizung per MQTT-Befehl (`vitoconnect/set/...`) setzen.
* **LoxBerry MQTT Gateway Integration**: Erkennt automatisch Broker-Host und Zugangsdaten des LoxBerry MQTT Gateway Plugins.
* **API-Quota Tracking**: Kontinuierliche Überwachung des Viessmann-Tageslimits (1.450 Calls / 24h) inklusive Schutzmechanismus gegen Rate-Limits.
* **LoxBerry V4 & 3 Kompatibilität**: Unterstützung für `[MAINTAINER]`-Metadaten, sauberes Logging und Hintergrunddienst (`daemon/`).

---

## Voraussetzungen

1. **LoxBerry Version 3.0** oder neuer.
2. Ein kostenloser Entwickler-Account im [Viessmann Developer Portal](https://developer.viessmann.com/):
   * Erstelle dort einen API-Client.
   * Notiere deine **Client-ID**.
   * Setze die **Redirect URI** auf: `http://localhost:4200/`

---

## MQTT Topics

### Status & Sensoren (Lesen)
Basis-Topic standardmäßig: `vitoconnect`

| Topic | Beschreibung | Einheit / Wert |
| :--- | :--- | :--- |
| `vitoconnect/temperature/outside` | Außentemperatur | °C |
| `vitoconnect/temperature/boiler` | Kesseltemperatur | °C |
| `vitoconnect/dhw/temperature` | Warmwassertemperatur | °C |
| `vitoconnect/dhw/target_temperature` | Warmwasser-Solltemperatur | °C |
| `vitoconnect/dhw/one_time_charge` | Einmalige WW-Bereitung | `0` oder `1` |
| `vitoconnect/burner/active` | Brenner aktiv | `0` oder `1` |
| `vitoconnect/burner/hours` | Brenner-Betriebsstunden | h |
| `vitoconnect/circuit_0/normal_temperature` | Normal-Solltemperatur (HK 0) | °C |
| `vitoconnect/circuit_0/comfort_temperature` | Komfort-Solltemperatur (HK 0) | °C |
| `vitoconnect/circuit_0/reduced_temperature` | Absenk-Temperatur (HK 0) | °C |
| `vitoconnect/circuit_0/mode` | Betriebsart (HK 0) | z. B. `standby`, `dhw`, `dhwAndHeating`, `heating` (anlagenabhängig) |
| `vitoconnect/errors/count` | Anzahl aktiver Fehler | Zahl |
| `vitoconnect/quota/calls_24h` | API-Aufrufe der letzten 24 Std. | Zahl |
| `vitoconnect/quota/remaining` | Verbleibende API-Aufrufe | Zahl (max. 1.450) |

### Befehle an Viessmann (Steuern)

| Topic | Beispiel-Payload | Funktion |
| :--- | :--- | :--- |
| `vitoconnect/set/circuit_0/normal_temperature` | `21.5` | Setzt normale Solltemperatur |
| `vitoconnect/set/circuit_0/comfort_temperature` | `23.0` | Setzt Komfort-Temperatur |
| `vitoconnect/set/circuit_0/reduced_temperature` | `17.0` | Setzt Absenktemperatur |
| `vitoconnect/set/circuit_0/mode` | `dhwAndHeating` oder `heating` | Ändert Betriebsmodus (erlaubte Werte siehe Web-UI) |
| `vitoconnect/set/dhw/target_temperature` | `55.0` | Setzt Warmwasser-Sollwert |
| `vitoconnect/set/dhw/one_time_charge` | `1` / `0` oder `start` / `stop` | Startet/Stoppt WW-Push |
| `vitoconnect/poll` | `1` | Löst sofortige API-Abfrage aus |

---

## PyViCare Updates

Die Bibliothek `PyViCare` wird kontinuierlich von der Community gepflegt. Sobald Viessmann API-Änderungen vornimmt oder neue Heizungsmodelle unterstützt werden, kann `PyViCare` im Webfrontend unter **Settings & Service** mit einem Klick auf **PyViCare aktualisieren** auf den neuesten Stand gebracht werden.