# CollectiveGPT auf Podman (ed-tech.app + nchan)

Dieser Stack betreibt **ed-tech.app** (CollectiveGPT, PHP) und einen
**nchan**-WebSocket-Server hinter Traefik.

## Architektur

```
Internet ──► Traefik ──► edtech-nginx ─┬─► edtech-php   (PHP-FPM, CollectiveGPT)
   (443)                   (Host:        └─► nchan        (Pub/Sub, WebSocket)
                        ed-tech.app)
```

- **edtech-nginx** — einziger nach außen (über Traefik) erreichbarer Dienst.
  Liefert statische Assets, reicht `*.php` an PHP-FPM und proxyt `/pub_id/`,
  `/sub_id/`, `/nchan_stub_status` an nchan (same-origin).
- **edtech-php** — PHP 8.3-FPM, Docroot ist der komplette Repo-Baum
  (`index.php` in der Wurzel). Hängt zusätzlich am externen `mariadb_net`.
- **nchan** — nginx + nchan-Modul, nur intern. PHP publiziert serverseitig
  über `/pub_id/`, Browser abonnieren per WebSocket über `/sub_id/`.

## Zielverzeichnis auf dem Server

Den **Inhalt** dieses Ordners (`setup/podman/`) nach `/podman/edtech-app/`
kopieren, das Repo selbst liegt darunter unter `CollectiveGPT/`:

```
/podman/edtech-app/
├── compose.yml
├── Dockerfile.php
├── php.ini
├── nginx/
│   ├── 00-upgrade-map.conf
│   ├── edtech.conf
│   └── snippets/{security,cache,php}.conf
├── nchan/
│   ├── Dockerfile
│   ├── nginx.conf
│   └── nchan.conf
├── podman-edtech.service
└── CollectiveGPT/            ← dieses Repo (separat hierher klonen)
```

Beispiel:

```bash
mkdir -p /podman/edtech-app
# Inhalt von setup/podman/ nach /podman/edtech-app/ kopieren
cp -a setup/podman/. /podman/edtech-app/
# Repo daneben klonen
cd /podman/edtech-app
git clone <REPO-URL> CollectiveGPT
```

## Voraussetzungen

- Laufender **Traefik** mit externem Netz `traefik_net`, den Entrypoints
  `web` (80) / `websecure` (443), einem Cert-Resolver `myresolver` und einer
  File-Middleware `redirect-to-https@file`.
- Externes Netz **`mariadb_net`** und ein MariaDB-Container daran.
- DNS: `ed-tech.app` zeigt auf den Server.

Fehlt eines der externen Netze, anlegen mit:

```bash
podman network create traefik_net
podman network create mariadb_net
```

## App-Konfiguration (nicht im Repo, manuell anlegen)

Diese Dateien sind per `.gitignore` ausgeschlossen und müssen im geklonten
Repo angelegt werden:

1. **`CollectiveGPT/php/pws.php`** — Kopie von `php/pws.NOGIT.php`, Werte setzen:
   - `$db_host` = Name des MariaDB-Containers (am `mariadb_net`)
   - `$db_user`, `$db_dbname`, `$db_pw`
   - `$nchan_pw` = **muss identisch sein** mit dem Passwort in
     `nchan/nchan.conf` (siehe unten)
   - `$py_api`, `$cookie_pw`, `$login_pw`, `$siteName`
2. **`nchan/nchan.conf`** — Platzhalter `CHANGE_ME_nchan_pw` durch dasselbe
   Passwort wie `$nchan_pw` ersetzen.
3. Datenbank + Benutzer anlegen und `setup/DBSETUP.sql` einspielen
   (siehe [MariaDB: Datenbank und Benutzer anlegen](#mariadb-datenbank-und-benutzer-anlegen)).
4. `optionen.json` und `chart-config.json` werden von der App zur Laufzeit in
   die Repo-Wurzel geschrieben — der PHP-Prozess (Alpine: UID/GID 82) braucht
   dort Schreibrechte:
   ```bash
   podman unshare chown -R 82:82 /podman/edtech-app/CollectiveGPT
   ```

## MariaDB: Datenbank und Benutzer anlegen

MariaDB läuft **nicht** in diesem Stack, sondern als eigener Container am
externen `mariadb_net`. Die folgenden Beispiele nutzen die Platzhalterwerte aus
`php/pws.NOGIT.php` (Name, Benutzer und Passwort sind dort alle `NOGIT`) —
in Produktion durch echte Werte ersetzen und identisch in `pws.php` eintragen.

Annahmen (anpassen): MariaDB-Container heißt `mariadb`, das root-Passwort ist
`ROOTPW`.

**1. Datenbank und Benutzer anlegen** (Charset passend zu `DBSETUP.sql`:
utf8mb4 / utf8mb4_general_ci):

```bash
podman exec -i mariadb mariadb -uroot -pROOTPW <<'SQL'
CREATE DATABASE IF NOT EXISTS `ed_tech_app`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS 'ed_tech_app'@'%' IDENTIFIED BY 'NOGIT';
GRANT ALL PRIVILEGES ON `ed_tech_app`.* TO 'ed_tech_app'@'%';
FLUSH PRIVILEGES;
SQL
```

> `'NOGIT'@'%'` erlaubt den Zugriff aus dem Container-Netz. Der Wert für den
> Host `@'%'` (statt `@'localhost'`) ist nötig, weil `edtech-php` über das
> Netzwerk (`$db_host` = Container-Name) verbindet, nicht über einen Socket.

**2. Schema einspielen** (`setup/DBSETUP.sql` aus dem geklonten Repo):

```bash
podman exec -i mariadb mariadb -uroot -pROOTPW NOGIT \
  < /podman/edtech-app/CollectiveGPT/setup/DBSETUP.sql
```

**3. Verbindung prüfen** (als App-Benutzer):

```bash
podman exec -i mariadb mariadb -uNOGIT -pNOGIT NOGIT -e "SHOW TABLES;"
```

Erwartet: `answers`, `send_prompts`, `template_prompt`, `usernames`,
`workshops`.

**4. In `pws.php` eintragen** — passend zu den obigen Werten:

```php
$db_host   = "mariadb";   // Name des MariaDB-Containers am mariadb_net
$db_dbname = "NOGIT";
$db_user   = "NOGIT";
$db_pw     = "NOGIT";
```

## Starten

```bash
cd /podman/edtech-app
podman compose up -d --build
podman compose exec edtech-nginx nginx -t
```

Als systemd-Service:

```bash
cp podman-edtech.service /etc/systemd/system/podman-edtech.service
sudo systemctl daemon-reload
systemctl enable --now podman-edtech.service
```

## Hinweise

- **nchan-Hairpin:** PHP ruft `https://ed-tech.app/pub_id/` auf (serverseitig).
  Der Aufruf geht über die öffentliche Adresse zurück auf den Server. Das
  funktioniert, sofern der Container `ed-tech.app` auflösen kann. Optional
  direkter (spart den Umweg) — im Service `edtech-php` ergänzen:
  ```yaml
  extra_hosts:
    - "ed-tech.app:HOST_GATEWAY"
  ```
  (Hairpin über Traefik bleibt aber der robusteste Weg für TLS.)
- **py-api / py-gpt** ist hier **nicht** enthalten (nur PHP + nchan wie
  angefragt). Die App referenziert `/py-api/chat-wizard`; wird das gebraucht,
  einen py-gpt-Dienst ergänzen und in `nginx/edtech.conf` eine
  `location /py-api/`-Proxy-Regel hinzufügen (vgl. `setup/docker/py-gpt/`).
- **MariaDB** läuft nicht in diesem Stack, sondern extern am `mariadb_net`.
