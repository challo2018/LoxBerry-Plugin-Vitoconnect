# Changelog

Alle relevanten Änderungen für das LoxBerry Vitoconnect Plugin werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/) und folgt [Semantic Versioning](https://semver.org/lang/de/).

---

## [2.0.0-beta.1] - 2026-10-11

### 🚀 Highlights & Architektur
* **PyViCare Core Engine**: Vollständige Neuentwicklung des Abfrage- und Steuerkerns in Python unter Verwendung der Community-gepflegten Bibliothek [`PyViCare`](https://github.com/openviess/PyViCare) (bekannt aus Home Assistant).
* **Offizieller OAuth2 PKCE Flow**: Zuverlässige Viessmann-Authentifizierung mit automatischem Refresh-Token (`refresh_token`). Fragiles HTML-/Passwort-Scraping und Regex-Parsing wurden vollständig entfernt.
* **Isoliertes Plugin-venv**: Installation und Betrieb erfolgen in einem dedizierten Python Virtual Environment (`data/plugins/Vitoconnect/venv`), konform mit Debian 12/13 PEP 668.
* **In-Place PyViCare Updates**: Direkte Aktualisierung der `PyViCare`-Bibliothek aus dem PyPI-Index über einen 1-Klick-Button im Webfrontend – kein Warten auf ein neues LoxBerry-Plugin-Release bei Viessmann-API-Änderungen.

### 📡 MQTT & Loxone Integration
* **Echtes bidirektionales MQTT**: Steuerung der Heizungsanlage erfolgt direkt über MQTT (`vitoconnect/set/...`), keine umständlichen Virtuellen Ausgangsbefehle mit Zugangsdaten im Miniserver mehr nötig.
* **Change-Detection**: Es werden nur veränderte Messwerte und Zustände an den MQTT Broker publiziert, wodurch die Netzwerk- und Miniserverlast drastisch sinkt. Alle 30 Minuten erfolgt ein automatischer Heartbeat-Refresh.
* **LoxBerry MQTT Gateway Integration**: Automatische Erkennung von Broker-Adresse, Port und Zugangsdaten aus dem LoxBerry MQTT Gateway Plugin.
* **API-Quota Tracking**: Kontinuierliche Überwachung des Viessmann-Tageslimits (1.450 Calls / 24h) inklusive Schutzmechanismus zur dynamischen Verlängerung des Abfrageintervalls bei drohendem Limit.
* **Abwärtskompatible HTTP-Brücke**: Bestehende Miniserver-Konfigurationen mit HTTP-GET-Aufrufen an `vitoconnect.php?action=setvalue...` werden weiterhin unterstützt und intern transparent an MQTT weitergeleitet.

### 💻 Webfrontend & Benutzererfahrung
* **Live-Datenpunkte Tabelle (`showdata.php`)**: Dynamische Anzeige aller tatsächlich von der Heizung erkannten Messwerte mit aktuellem Wert, Einheit, Typ-Badge und 1-Klick-Topic-Kopierfunktion für die Loxone Config.
* **Live-Suchfilter & Auto-Refresh**: Echtzeit-Filterung nach Beschreibung oder Topic und konfigurierbares Auto-Refresh (5s).
* **Interaktives Test-Panel**: Ermöglicht das Testen von Steuerbefehlen (Zieltemperaturen, Betriebsmodi, Warmwasser-Schnellaufheizung) direkt aus dem Browser vor der Konfiguration im Miniserver.
* **Fehlerdiagnose & Statusursache**: Automatische Erkennung und Anzeige des genauen Grundes, falls der Hintergrunddienst gestoppt ist (z. B. unvollständige Zugangsdaten, fehlendes venv oder letzter gemeldeter API-Fehler).
* **Live-Log Konsole (`log.php`)**: Scrollbares Dark-Mode-Terminal mit farbiger Syntax-Hervorhebung (`ERROR` rot, `WARN` gelb, `INFO` grün), Auto-Refresh und Steuerungstasten (`Starten`, `Neustarten`, `Stoppen`, `Log leeren`).
* **Responsives Layout & harmonische Typografie**: Ersatz der starren `.wide`-Banner durch dezente Card-Header und modernes, mobiles CSS-Grid für Smartphones, Tablets und Desktop.

### 🛠️ Behobene Fehler & Stabilität
* **Checkbox-Persistenz behoben**: HTML-Formularwerte (`"on"`) für „MQTT aktivieren“ und „LoxBerry MQTT Gateway nutzen“ werden nun zuverlässig als Booleans gespeichert und bleiben beim Neuladen der Seite erhalten.
* **Dienst-Start ohne Root-Rechte**: `daemon.sh` prüft nun den aktuellen Benutzer und verzichtet auf `su - loxberry`, wenn der Aufruf bereits aus dem Webfrontend (Benutzer `loxberry`) erfolgt.
* **Resiliente Pfad-Erkennung**: Pfade zu `config.json` und Verzeichnissen werden nun unabhängig von LoxBerry-Umgebungsvariablen zuverlässig aufgelöst.
* **Toleranter Dienst-Start**: Der Daemon stürzt bei fehlender Konfiguration nicht mehr ab, sondern geht in eine schonende Warteschleife (`waiting_for_config`), bis der Benutzer die Zugangsdaten im Web-UI speichert.

### 🧹 Bereinigung
* **Alte PHP-Bibliotheken entfernt**: Löschung der veralteten und nicht mehr benötigten Ordner `phpMQTT/`, `assert/`, `link/` und `exception-constructor-tools/`.
* **SamplePlugin V4 Metadaten**: `plugin.cfg` aktualisiert auf Version 2.0.0 mit `[MAINTAINER]`-Angabe für Fabian Franzmann und `LB_MINIMUM=3.0.0`.
