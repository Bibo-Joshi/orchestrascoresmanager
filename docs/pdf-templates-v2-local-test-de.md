# Lokale OSM-Abnahme auf dem Ubuntu-Notebook

Das Compose-Projekt `osm-pdf-v2-notebook` verwendet Nextcloud
`34.0.5-apache` und MariaDB `10.6`. Nextcloud und MariaDB sind nur im
internen Docker-Netz. Ein HTTP-Proxy veröffentlicht ausschließlich
`127.0.0.1:8088`. MariaDB hat keinen veröffentlichten Port. Die persistenten
Bind-Mounts liegen unter `/mnt/osm-test/nextcloud` und
`/mnt/osm-test/mariadb`. Das Projekt verwendet keine vorhandenen Docker-Volumes.

## Image einhängen und vorbereiten

Vorausgesetzt sind Ubuntu mit Docker Engine und Compose v2, Zugriff auf den
lokalen Docker-Socket, Bash, `findmnt`, `openssl` und ausreichender freier
Platz auf dem ext4-Image (80 GB, ursprünglich etwa 75 GB frei). Für den
API-Vergleich werden zusätzlich `curl` und `jq` benötigt. Git und die
zum Checkout passende Node-/npm-Version werden zum Erstellen der App-Pakete
benötigt. Die folgenden Befehle vom Git-Checkout aus ausführen.

1. Die NTFS-Datenpartition im Dateimanager oder über ihren vorhandenen
   Systemeintrag einhängen. Bei fehlendem oder falschem Mount abbrechen.
2. Das bestehende Image einhängen. Das Image nach jedem Neustart erneut
   einhängen. Es wird weder neu erstellt noch formatiert.

   ```sh
   test "$(findmnt -n -M /media/kai/Daten -o FSTYPE)" = ntfs3 &&
     test -f /media/kai/Daten/kaibe/osm-test/osm-test.ext4 &&
     ! mountpoint -q /mnt/osm-test &&
     sudo mount -o loop -t ext4 /media/kai/Daten/kaibe/osm-test/osm-test.ext4 /mnt/osm-test &&
     findmnt --mountpoint /mnt/osm-test
   ```

3. Der Benutzer braucht Schreibrechte auf dem Stamm des eingehängten
   Images. Falls erforderlich, nach Prüfung des ext4-Mounts einmalig
   `sudo chown "$(id -u):$(id -g)" /mnt/osm-test` ausführen. Die Eigentümer
   der Unterordner verwalten die Container. Keine rekursive
   Eigentümeränderung vornehmen.
4. Konfiguration einmalig kopieren. Das Prüfskript muss erfolgreich sein,
   bevor ein Verzeichnis unter dem Mount angelegt wird.

   ```sh
   dev/osm-local-test/start.sh --check &&
     install -d -m 700 /mnt/osm-test/compose &&
     install -m 600 dev/osm-local-test/{compose.yaml,nginx.conf,.env.example} /mnt/osm-test/compose/ &&
     install -m 700 dev/osm-local-test/start.sh /mnt/osm-test/compose/start.sh
   ```

   Einmalig eine lokale `.env` erzeugen. Diese Werte müssen zur Datenbank
   passen, sobald sie initialisiert ist. Bestehende `.env` behalten.

   ```sh
   /mnt/osm-test/compose/start.sh --check && (
     umask 077
     set -C
     printf 'OSM_TEST_DB_PASSWORD=%s\nOSM_TEST_DB_ROOT_PASSWORD=%s\n' \
       "$(openssl rand -hex 24)" "$(openssl rand -hex 24)" > /mnt/osm-test/compose/.env
   )
   ```

Docker Engine 27.3.1 gehört zu den Versionen, bei denen Nachbarn im selben
Layer-2-Netz unter Umständen auch auf localhost veröffentlichte Docker-Ports
erreichen können. Den Test auf diesem Notebook ohne aktives untrusted LAN/WLAN
betreiben oder vorher am Host weitergeleiteten Verkehr aus dem LAN zum
Testport sperren und die Sperre von einem zweiten Rechner prüfen. Diese
Prüfung ist vor dem Start mit echten Daten erforderlich. Bei Docker mit
iptables-Backend lässt sich der weitergeleitete Verkehr zu diesem
Loopback-Port nach dem Start des Docker-Daemons und vor dem Projektstart
gezielt sperren. Beispiel für eine einmalig einzurichtende Regel:

```sh
sudo iptables -I DOCKER-USER 1 ! -i lo -p tcp \
  -m conntrack --ctorigdst 127.0.0.1 --ctorigdstport 8088 -j DROP
```

Die Regel nach einem Neustart prüfen und auf dem lokalen Rechner den
Loopback-Zugriff testen. Bei einem anderen Firewall-Backend die
entsprechende Sperre mit der vorhandenen Host-Firewall umsetzen.
[Docker-Dokumentation zur Portveröffentlichung](https://docs.docker.com/engine/network/port-publishing/)

## Start, Stopp und Aushängen

```sh
/mnt/osm-test/compose/start.sh
```

Für jeden Start oder Neustart über das Skript gehen. Es prüft NTFS-Mount,
ext4-Mount, zugehörige Loop-Datei und Schreibbarkeit, bevor es Compose aufruft.
Die Bind-Mounts erlauben Docker kein automatisches Anlegen fehlender
Hostverzeichnisse. Das Projekt startet nach einem Docker- oder Notebook-Neustart
nicht automatisch.

Weboberfläche unter <http://127.0.0.1:8088> öffnen. Bei einer frischen
Instanz im Assistenten einen eigenen Testadministrator anlegen. Die
MariaDB-Verbindung ist `db`, Datenbank und Benutzer heißen `nextcloud`.
Das Datenbankkennwort steht in der lokalen `.env`. Der Container erhält
diese Daten auch über seine Compose-Umgebung.

Für Verwaltungsbefehle in einer Bash-Sitzung:

```sh
cd /mnt/osm-test/compose
dc() {
  env -u DOCKER_HOST -u DOCKER_CONTEXT -u OSM_TEST_DB_PASSWORD -u OSM_TEST_DB_ROOT_PASSWORD \
    docker --host unix:///var/run/docker.sock compose \
    -p osm-pdf-v2-notebook --env-file /mnt/osm-test/compose/.env \
    -f /mnt/osm-test/compose/compose.yaml "$@"
}
occ() { dc exec -u www-data app php occ "$@"; }
dc ps
```

Vor dem Aushängen alle drei Projektcontainer mit `dc stop` stoppen und
mit `dc ps --status running` kontrollieren. Jede Shell aus dem Mount
herauswechseln, dann `sync` und `sudo umount /mnt/osm-test` ausführen.
Erst danach die NTFS-Partition aushängen. Wenn der Mount belegt ist,
verbliebene Container und Prozesse ermitteln und stoppen. Kein erzwungenes
oder verzögertes Aushängen verwenden. Zum Wechsel der Datenbestände auf
dem ext4-Image siehe Variante B.

## Variante A · Synthetischer Bestand und reguläres Upgrade

1. OSM-Ausgangsstand **2.0.0** aus `df7ac796d8f4df1beb848926329359b088eaf904`
   verwenden. Dieser Commit liegt vor der Template-Migration. Für die
   Neuinstallationsprobe direkt das aktuelle Testpaket auf einer separaten
   leeren Instanz verwenden.
2. App-Paket aus dem ausgewählten Commit erzeugen. Beispielbefehle aus dem
   Checkout. Für das spätere Upgrade `revision` durch den zu prüfenden
   Feature-Commit ersetzen und ein neues Paketverzeichnis verwenden:

   ```sh
   dev/osm-local-test/start.sh --check
   revision=df7ac796d8f4df1beb848926329359b088eaf904
   package=$(mktemp -d /mnt/osm-test/osm-package.XXXXXXXX)
   git archive "$revision" | tar -xf - -C "$package"
   (cd "$package" && npm ci && npm run build && rm -rf node_modules)
   ```

   Das Ausgangspaket in der frischen Instanz bereitstellen und aktivieren.
   `dc` und `occ` wie oben definieren und für diese Befehle dasselbe
   `package` verwenden.

   ```sh
   app_path=/var/www/html/custom_apps/orchestrascoresmanager
   dc exec app mkdir -p "$app_path"
   dc cp "$package/." "app:$app_path"
   dc exec app chown -R www-data:www-data "$app_path"
   occ app:enable orchestrascoresmanager
   occ config:app:get orchestrascoresmanager installed_version
   occ config:app:set orchestrascoresmanager allowed_edit_groups --type=array --value='["admin"]'
   ```

   Im letzten Befehl bei Bedarf den Namen der lokalen Nextcloud-Gruppe
   mit Bearbeitungsrechten verwenden.
3. Eine veröffentlichte Setlist mit Partitur- und Pauseneintrag, eine
   Partitur mit Schlagwort, eine Sammlung sowie benutzerbezogene
   Setlist-Einstellungen anlegen. Im Ausgangsstand bei Bedarf ein
   persönliches PDF-Profil speichern. API-Antworten als JSON unter
   `/mnt/osm-test` sichern und später vergleichen. Relevante OCS-Pfade
   unter `/ocs/v2.php/apps/orchestrascoresmanager` sind `/setlists/{id}`,
   `/setlists/{id}/entries`, `/scores`, `/tags`, `/foldercollections`,
   `/settings/user/setlists` und `/settings/user/pdf-layouts`. Für
   API-Aufrufe in der lokalen Instanz `OCS-APIRequest: true` und
   `Accept: application/json` mit einem lokalen Testkonto verwenden.
   Beispiel für den Snapshot einer Setlist. `id` und `local_user` an
   die Testinstanz anpassen. Das Kennwort bei der ersten Anfrage eingeben:

   ```sh
   id=1
   local_user=admin
   ocs_base=http://127.0.0.1:8088/ocs/v2.php/apps/orchestrascoresmanager
   curl -fsS --user "$local_user" -H 'OCS-APIRequest: true' -H 'Accept: application/json' \
     "$ocs_base/setlists/$id" | jq -eS '.ocs.data' > /mnt/osm-test/setlist-vorher.json
   ```

   Nach dem Upgrade in eine zweite Datei schreiben und mit `diff -u`
   vergleichen. Der neue Wert `defaultTemplateId: null` ist erwartet.
   Für abhängige Einträge und Einstellungen dieselbe Methode mit den
   zugehörigen Endpunkten verwenden.
4. Vor dem Upgrade die installierte Version prüfen. Im DB-Katalog
   müssen Template-Tabelle und Default-Spalte fehlen. Die unten gezeigte
   Abfrage der Migrationstabelle muss leer sein. Einen konsistenten Snapshot
   anlegen. Nextcloud hierfür in den Wartungsmodus setzen und den Webdienst
   stoppen, bevor DB und Dateibaum gesichert werden.

   ```sh
   occ maintenance:mode --on
   dc stop web app
   backup=$(mktemp -d /mnt/osm-test/osm-backup.XXXXXXXX)
   dc exec -T db sh -ec 'exec mariadb-dump --single-transaction --routines --triggers -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' > "$backup/db.sql"
   sudo tar --numeric-owner -C /mnt/osm-test -cpf "$backup/nextcloud.tar" nextcloud
   /mnt/osm-test/compose/start.sh
   ```

5. Das Paket des Feature-Commits wie in Schritt 2 bauen. Nur in der
   temporären Paketkopie `2.0.0` auf `2.0.1` setzen. Paketdateien wie oben
   nach `$app_path` kopieren und die Eigentümer setzen. Das Upgrade mit

   ```sh
   sed -i 's|<version>2.0.0</version>|<version>2.0.1</version>|' "$package/appinfo/info.xml"
   dc cp "$package/." "app:$app_path"
   dc exec app chown -R www-data:www-data "$app_path"
   occ upgrade
   dc stop web app
   /mnt/osm-test/compose/start.sh
   occ config:app:get orchestrascoresmanager installed_version
   ```

   Das Stoppen und erneute Starten lädt auch die PHP-Webprozesse neu.
   Der erwartete Wert von `installed_version` ist `2.0.1`.
6. Migration und FK prüfen. Bei einem anderen Tabellenpräfix als `oc_`
   die Abfragen entsprechend anpassen.

   ```sh
   dc exec db sh -ec 'mariadb -unextcloud -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"'
   ```

   ```sql
   SELECT version FROM oc_migrations
     WHERE app = 'orchestrascoresmanager'
       AND version = '001003003Date20261118000000';
   SHOW CREATE TABLE oc_osm_setlist_templates;
   SHOW CREATE TABLE oc_osm_setlists;
   ```

   Erwartet sind die Migration, der Primärschlüssel auf `id`, der
   Namensindex, die nullable und indizierte Spalte `default_template_id`
   und der Fremdschlüssel mit `ON DELETE SET NULL`. Vorher-/Nachher-JSON
   vergleichen. Eine vorhandene Setlist bekommt zunächst
   `defaultTemplateId: null`. Über `/setlist-templates` ein Template
   anlegen, lesen und ändern. Es als Default der Testsetlist setzen,
   einen PDF-Export mit Leseberechtigung prüfen, das Template löschen und
   den anschließenden Rückfall auf `null` sowie das Standard-PDF prüfen.

## Variante B · Späterer lokaler Restore einer NTSO-Sicherung

Diese Variante wird ausschließlich vom Betreiber auf dem Notebook
ausgeführt. Eine konsistente, geschützte Sicherung von DB, Nextcloud-Config,
App-Versionen und benötigten Dateien aus demselben Zeitstand verwenden.
Über einen geschützten Kanal direkt auf das Notebook übertragen und nur
im geschützten ext4-Bereich ablegen. Sicherungen, echte Zugangsdaten und
Produktiv-Konfigurationen gehören weder ins Repository noch in die Cloud.

- Vor dem Wechsel `dc down` **ohne** `-v` ausführen. Nur bei geprüftem
  Mount die beiden Testdatenverzeichnisse in einen neuen, eindeutig
  benannten Sicherungsordner auf demselben Image verschieben. Das
  Startskript erzeugt anschließend leere Verzeichnisse für das Projekt.
  Andere Container, Docker-Volumes und Verzeichnisse bleiben unberührt.
- Das leere Projekt mit dem Startskript initialisieren. Danach mit
  `dc stop web app` beide Webdienste stoppen. Nur den SQL-Dump der
  Nextcloud-Datenbank importieren. Keine Systemtabellen, produktiven
  Benutzerkonten des DB-Servers oder DB-Serverkonfiguration übernehmen.
  Einen Dump für die lokale Datenbank `nextcloud` aus einem geschützten
  Ordner auf dem Image einspielen:

  ```sh
  dc exec -T db sh -ec 'exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' < "$backup/db.sql"
  ```

  `$backup` bezeichnet hier den echten, lokal entschlüsselten
  Sicherungsordner unter `/mnt/osm-test`. Prüfen, dass der Dump keine
  `USE`-Anweisung für eine abweichende Datenbank enthält. Vor dem Start des App-Dienstes den passenden
  Nextcloud-Dateibaum samt Daten, Konfiguration und Apps bereitstellen.
  Datenbank und Dateien müssen dieselbe Backup-Generation bilden. Den
  Wartungsmodus in der lokalen Konfiguration vor dem ersten Start
  aktivieren. Produktive Objektspeicher können nur mit einem vollständigen,
  getrennten lokalen Restore verwendet werden.
- Die Config **offline** bereinigen. DB-Host ausschließlich `db`, lokale
  DB-Zugangsdaten, Datenpfad im Container, Trusted Domains ausschließlich
  lokal, externe Proxy-/URL-Overrides entfernen oder lokal setzen.
  Produktive Redis-, Mail-, Objekt-/Externalspeicher- und
  Authentifizierungsverbindungen entfernen oder sicher lokal ersetzen.
  Auch produktive Credentials und Integrationen aus der importierten
  Datenbank und aus App-Konfigurationen prüfen, insbesondere External
  Storage, Föderation, Webhooks, LDAP/SSO und Versanddienste. Die interne
  Netztrennung beibehalten. Keinen Cron-Container oder Host-Cron einrichten.
  Bei der lokalen Prüfung Hintergrundjobs auf einen kontrollierten
  Wartungsablauf beschränken.
- Erst nach Kontrolle der Isolierung vom lokalen Loopback öffnen.
  App-Version, Migrationstabelle und Datenbankverbindung prüfen.
  Vorher-/Nachher-Vergleiche nach IDs und Relationen durchführen und
  Stichproben aus Setlists, Einträgen, Partituren, Schlagworten, Sammlungen
  und Nutzerprofilen ziehen. Ein repräsentatives Standard- und Template-PDF
  vergleichen. [Wiederherstellungsverfahren](pdf-templates-v2-upgrade.md)

Ein Reset ist das bewusste Archivieren beziehungsweise Ersetzen nur dieser
beiden **Notebook-Testordner** bei gestopptem Projekt. Für einen Rollback
die passenden Dateien, Konfiguration und Datenbank gemeinsam aus derselben
Sicherung wiederherstellen. Es ist kein globaler Docker-Bereinigungsbefehl
erforderlich.

## Abnahmegrenzen

Die Stage-5-Ergebnisse stehen in der
[Fortschrittsdokumentation](pdf-templates-v2-progress.md). Die Notebook-
Mounts und der Zugriffsschutz unter Docker 27.3.1 müssen auf dem Notebook
abgenommen werden. Der Restore echter NTSO-Daten ist ein separater lokaler
Abnahmeschritt.
