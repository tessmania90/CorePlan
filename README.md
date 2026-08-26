# 🚀 CorePlan

**CorePlan** ist ein kompromissloses "Zero-Bloat" Projektmanagement-System, das speziell für IT-Dienstleister, Consultants und kleine Teams entwickelt wurde. 

Wenn Jira zu überladen, Trello zu unstrukturiert und SaaS-Abos zu teuer oder aus Datenschutzgründen (On-Premise) keine Option sind, schlägt die Stunde von CorePlan. Es läuft komplett autark in einem einzigen Docker-Container, speichert Daten in einer rasend schnellen SQLite-Datenbank und erfordert keinerlei externe Abhängigkeiten.

## 🔥 Kern-Philosophie
CorePlan nutzt einen **Lean-Hybrid-Ansatz**. Es kombiniert die Verlässlichkeit klassischer Phasenmodelle (feste Deadlines, Steckbriefe) mit der Geschwindigkeit von Lean-Task-Listen. 

Zusätzlich setzt das System auf **Management by Exception**: Projektmanager müssen nicht täglich Dashboards kontrollieren. Das integrierte Hintergrund-Radar meldet sich völlig automatisch via Gotify (Push-Nachricht) oder SMTP (E-Mail), sobald Deadlines näher rücken oder überfällig sind.

## ✨ Features
* **Zero-Bloat Architektur:** Keine gigantischen Frameworks, kein Composer-Overhead. Native PHP 8+ Power mit SQLite.
* **Smartes Dashboard ("Mein Bereich"):** Personalisierte Übersicht über zugewiesene Projekte, Aufgaben und eine 7-Tage-Deadline-Warnung.
* **Rollenbasierte Rechte (RBAC):** Klar getrennte Rollen für Admins, Projektmanager (PM) und reguläre User.
* **Multi-Channel Alarme:** Globale Push-Benachrichtigungen an Admins via **Gotify** und personalisierte Aufgaben-Erinnerungen via **SMTP-Mail**.
* **Audit-Logging:** Lückenlose Aufzeichnung aller Systemereignisse (Wer hat wann welchen Task gelöscht oder abgehakt?).
* **Offline-Fähig:** Ideal für isolierte Firmennetzwerke. Das System "telefoniert" nicht nach Hause.

## 📦 Installation (Docker)
CorePlan ist in weniger als 10 Sekunden einsatzbereit. Erstelle einfach eine `docker-compose.yml`:

```yaml
version: '3.8'

services:
  coreplan:
    image: stessmann/coreplan:latest
    container_name: coreplan
    ports:
      - "8443:443" # Passe den Port nach deinen Bedürfnissen an
    volumes:
      - ./data:/var/www/data
    restart: unless-stopped
```

Starte den Container mit:
```bash
docker compose up -d
```

### 🔑 Erste Schritte

Bevor der Container komplett funktioniert müssen dem ./data ordner noch die berechtigungen für wwww-data gegeben werden. Damit die Datenbank auch schreibfähig ist.
```
chown -R 33 ./data
chmod +755 ./data
```
Nach dem Start ist CorePlan unter `https://deine-ip:8443` erreichbar. 
* **Standard-Login:** `admin`
* **Standard-Passwort:** `admin`

*(Bitte ändere das Passwort sofort nach dem ersten Login unter dem Tab "Benutzer" im Dashboard!)*

## ⏱️ Automatisierung (Cronjob)
Damit CorePlan tägliche Deadline-Erinnerungen per Mail und Gotify verschickt, richte auf deinem Docker-Host folgenden Cronjob ein (z.B. täglich um 08:00 Uhr):
```bash
0 8 * * * docker exec -i coreplan php /var/www/html/cron.php
```

## 💎 Editionen
Diese GitHub-Version ist die **Community Edition**. Sie enthält alle Features, ist jedoch auf **maximal 3 gleichzeitig aktive Projekte** limitiert. Abgeschlossene oder abgebrochene Projekte zählen nicht zum Limit.

Für den uneingeschränkten Unternehmenseinsatz (Standard / Enterprise Edition) mit unlimitierten Projekten wende dich für einen Lizenzschlüssel an [tessmann-digital.de](https://tessmann-digital.de).
