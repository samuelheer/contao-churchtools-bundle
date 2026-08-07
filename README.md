<img src="https://img.shields.io/badge/maintained-yes-brightgreen" alt="maintained">
<img src="https://img.shields.io/badge/license-LGPL--3.0--or--later-blue" alt="license">
<img src="https://img.shields.io/badge/Contao-5.7%20LTS-orange" alt="Contao 5.7">
<img src="https://img.shields.io/badge/PHP-%5E8.3-777bb4" alt="PHP 8.3">

# ChurchTools für Contao

Bringt Termine samt Flyer und Personen aus [ChurchTools](https://church.tools) auf eine Contao-Website - ohne die Daten doppelt zu pflegen.

Kompatibel mit **Contao 5.7 LTS** und **PHP ^8.3**. Ausschließlich Twig-Templates, damit der Wechsel auf Contao 6 nichts kostet. Verifiziert gegen ChurchTools 3.135.

## Funktionen

- **Termin-Abgleich** aus beliebig vielen ChurchTools-Kalendern in ganz normale Contao-Events - Listen, Reader, Suche und Feeds funktionieren unverändert
- **Flyer** vom Termin werden mitgeholt, inklusive Fokuspunkt für sauberen Zuschnitt in Listenansichten
- **Serien, Ausnahmen und Zusatztermine** werden korrekt abgebildet
- **Personen** als Insert-Tag oder Inhaltselement - entweder fest gewählt oder als Rolle („die Leitung der Gruppe X"), die automatisch mitwechselt
- **Personenauswahl per Namenssuche** im Backend statt per ID
- **Inhaltselement „Termin-Flyer"**: zeigt die Flyer der nächsten Termine, vorbei ist vorbei
- **Mandantenfähig** - mehrere Websites in einer Contao-Installation können auf verschiedene ChurchTools-Instanzen zugreifen
- **Datenschutz eingebaut**: ausgeliefert wird nur, wer ausdrücklich freigegeben ist

Was das Bundle **nicht** tut: Es baut nichts nach, was ChurchTools bereits kann. E-Mail-Versand, Routinen, Follow-ups und Anmeldelogik bleiben dort.

## Installation

```bash
composer require samuelheer/contao-churchtools-bundle
vendor/bin/contao-console contao:migrate
```

Alternativ über den Contao Manager nach `samuelheer/contao-churchtools-bundle` suchen.

## Einrichtung

### 1. Dienstkonto in ChurchTools

Eine eigene Person mit eigener Berechtigungsgruppe anlegen, die **nur Leserechte** auf die freizugebenden Kalender und Gruppen hat, und dafür unter *Persönliche Einstellungen → Logintoken* ein Token erzeugen.

> Kein persönliches Token verwenden. Es hängt an einem Menschen, stirbt mit dessen Konto und schreibt jede Änderung im ChurchTools-Log auf dessen Namen.

### 2. Token in die Umgebung

```dotenv
# .env.local
CHURCHTOOLS_TOKEN_MAIN=…
```

Das Token wird **nie** in der Datenbank gespeichert - im Backend steht nur der Name der Variablen. So landet es weder in einem Datenbank-Backup noch in einem Export.

### 3. Verbindung anlegen

Backend → **System → ChurchTools-Verbindungen**

| Feld | Bedeutung |
|---|---|
| Bezeichnung | Name der Gemeinde bzw. Instanz |
| Adresse | Basis-URL, z. B. `https://example.church.tools` |
| Name der Umgebungsvariablen | z. B. `CHURCHTOOLS_TOKEN_MAIN` - nicht das Token selbst |
| Ordner für Bilder | Zielordner für importierte Flyer, z. B. `files/gemeinde/churchtools` |
| Freigabegruppe für Personen | ID der Gruppe, die entscheidet, wer auf der Website erscheinen darf |
| Freigegebene Personenfelder | Welche Felder ChurchTools überhaupt verlassen |

Die Übersicht zeigt an, ob das Token in der Umgebung gefunden wurde. Fehlt es, steht das dort - statt dass der Abgleich stillschweigend nichts tut.

### 4. Website zuordnen

In den Seiteneinstellungen der **Startseite** die Verbindung auswählen. Sie gilt für alle Personen-Elemente und Insert-Tags dieser Website. Am einzelnen Element ist die Instanz bewusst nicht wählbar, damit kein Element versehentlich Personen einer fremden Gemeinde anzeigt.

### 5. Kalender zuordnen

Einen Contao-Kalender öffnen, Verbindung wählen, **speichern**, dann die gewünschten ChurchTools-Kalender ankreuzen.

| Einstellung | Bedeutung |
|---|---|
| ChurchTools-Verbindung | Aus welcher Instanz die Termine kommen |
| ChurchTools-Kalender | Welche Kalender übernommen werden (Liste kommt aus der API) |
| Abgleich-Intervall | Stündlich, täglich oder nur manuell |
| Termin-Link statt Detailseite | Ist am Termin ein Link hinterlegt, führt der Titel direkt dorthin |

> **Wichtig:** Das Merkmal „öffentlich" am ChurchTools-Kalender taugt nicht als Filter - auch Putzpläne, Sitzungen und private Belegungen stehen dort auf öffentlich. Deshalb wird hier ausdrücklich ausgewählt.

Ein zugeordneter Kalender gehört ab dann dem Abgleich: Seine Termine sind im Backend schreibgeschützt, weil der nächste Lauf Handarbeit sonst stillschweigend überschreiben würde.

## Abgleich

Läuft automatisch über Contaos Cron, je Kalender nach dessen Intervall. Zusätzlich gibt es in der Kalenderliste die Schaltfläche **„Jetzt abgleichen"**.

Auf der Konsole:

```bash
# zeigt nur an, was geschehen würde
vendor/bin/contao-console churchtools:sync --dry-run

# führt aus
vendor/bin/contao-console churchtools:sync

# abweichender Zeitraum, etwa für die Erstbefüllung
vendor/bin/contao-console churchtools:sync --from=2025-01-01 --to=2026-12-31
```

### Was der Abgleich tut

- Übernommen wird jeder Termin, der **nicht als intern markiert** ist
- **Gelöschtes wird deaktiviert, nicht gelöscht** - so leert ein API-Fehler nie den Kalender
- Unveränderte Termine erzeugen **keine** Schreibvorgänge
- Termine außerhalb des Zeitfensters bleiben unangetastet

### Grenzen

- Das Zeitfenster reicht standardmäßig **einen Monat zurück und zwölf Monate voraus**. Ältere Termine werden nicht angefasst; für eine Erstbefüllung `--from`/`--to` verwenden.
- Serien werden von ChurchTools aufgelöst geliefert. Eine Instanz ist erst über **Termin-ID plus Startzeitpunkt** eindeutig - wird eine Serie in ChurchTools verschoben, entstehen neue Instanzen und die alten werden deaktiviert.
- Ein Termin kann **ein** Bild tragen. Mehrere Flyer pro Termin sind nicht vorgesehen.

## Personen anzeigen

### Insert-Tags

```
{{ct_person::42}}                 Person 42, Name im Fließtext
{{ct_person::42::card}}           Person 42 als Karte
{{ct_person_group::17}}           alle freigegebenen Mitglieder der Gruppe 17
{{ct_person_group::17::3}}        nur Rolle 3, z. B. die Leitung
{{ct_person_group::17::3::card}}  dieselbe Auswahl als Karte
```

### Inhaltselement „Person"

Wahlweise eine feste Person - im Backend über ein **Suchfeld** nach Namen gewählt - oder eine **Rolle in einer Gruppe**. Bei der Rolle wechselt die Anzeige automatisch mit, sobald sich in ChurchTools die Zuständigkeit ändert.

Darstellungen: `card`, `inline`, `contact`, `list`. Die Templates liegen unter `@Contao/churchtools/person_*.html.twig` und lassen sich im Projekt überschreiben.

## Datenschutz

Das Bundle ist hier absichtlich streng:

- **Personendaten werden nicht in die Contao-Datenbank gespiegelt**, sondern bei Bedarf geholt und kurz zwischengespeichert
- **Ausgeliefert wird nur, wer in der Freigabegruppe steht.** Ohne konfigurierte Gruppe erscheint niemand - eine fehlende Einstellung gilt nicht als Vollfreigabe. Das greift auch, wenn eine Personen-ID direkt in einem Insert-Tag steht
- **Nur ausgewählte Felder** verlassen ChurchTools; E-Mail und Telefon sind voreingestellt aus
- **Das Bildrecht aus ChurchTools wird respektiert** - ohne gesetzte Einwilligung wird kein Bild ausgeliefert
- **Personenbilder** laufen über eine eigene Route und werden nie im Web-Verzeichnis abgelegt. Fehlt die Freigabe, antwortet sie mit 404 statt mit einem Platzhalter, der die Existenz der Person bestätigen würde
- **Caches sind pro Verbindung getrennt** - Mandanten können sich nicht gegenseitig sehen

## Ausfallverhalten

Ist ChurchTools nicht erreichbar, bleibt die Website vollständig: Termine liegen ohnehin in der Contao-Datenbank, Personen kommen aus einem unbefristet abgelegten letzten guten Stand. Im Frontend erscheint keine Fehlermeldung.

## Fehlersuche

| Symptom | Ursache und Lösung |
|---|---|
| Übersicht meldet „Umgebungsvariable fehlt" | Die in der Verbindung genannte Variable ist nicht gesetzt. Nach dem Eintragen in `.env.local` den Cache leeren. |
| Abgleich meldet „ChurchTools ist nicht erreichbar" | Basis-URL prüfen (ohne abschließenden Schrägstrich) und ob das Token noch gültig ist. `churchtools:person` zeigt beides an. |
| Kalenderliste im Backend bleibt leer | Verbindung wählen und **speichern** - die Liste wird erst danach von der API geholt. |
| Termine kommen, aber ohne Flyer | Hat der Termin in ChurchTools ein Bild? Der Bericht des Abgleichs nennt die Ursache je Termin. |
| Person erscheint nicht auf der Website | `vendor/bin/contao-console churchtools:person <ID>` - meist fehlt der Eintrag in der Freigabegruppe. |
| Person erscheint, aber ohne Bild | In ChurchTools ist die Bildnutzung für diese Person nicht erlaubt. Das ist eine Einstellung dort, keine des Bundles. |
| Änderungen am Termin verschwinden wieder | Der Kalender ist einer Verbindung zugeordnet und gehört dem Abgleich. Termine in ChurchTools bearbeiten. |
| Eigenes Template greift nicht | Nach dem Anlegen `cache:clear` **und** `cache:warmup` - die Twig-Hierarchie wird nur beim Warmup neu gebaut. |

## Befehle

| Befehl | Zweck |
|---|---|
| `churchtools:sync` | Termine abgleichen, mit `--dry-run`, `--from`, `--to` |
| `churchtools:person <ID>` | Prüft Freigabe und zeigt, welche Felder ausgeliefert würden |
| `churchtools:person --search=<Name>` | Personensuche wie im Backend-Auswahlfeld |
| `churchtools:person --group=<ID> [--role=<ID>]` | Löst eine Gruppe bzw. Rolle auf |

## Lizenz

LGPL-3.0-or-later
