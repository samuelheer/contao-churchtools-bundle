# ChurchTools für Contao

Bringt Termine samt Flyer und Personen aus [ChurchTools](https://church.tools) auf eine Contao-Website - ohne die Daten doppelt zu pflegen.

Gebaut für **Contao 5.7 LTS** und **PHP 8.3+**, ausschließlich mit Twig-Templates, damit der Wechsel auf Contao 6 nichts kostet.

## Was es tut

- **Termine** aus ausgewählten ChurchTools-Kalendern erscheinen als ganz normale Contao-Events. Serien, Ausnahmen und Zusatztermine inklusive.
- **Flyer** vom Termin werden mitgeholt - samt Fokuspunkt, damit Hochformat-Bilder in Listenansichten nicht mittig zerschnitten werden.
- **Personen** als Insert-Tag oder Inhaltselement, wahlweise fest gewählt oder als Rolle: „die Leitung der Gruppe X". Wechselt die Zuständigkeit in ChurchTools, wechselt die Anzeige.
- **Mandantenfähig**: Mehrere Gemeinde-Websites in einer Contao-Installation können auf verschiedene ChurchTools-Instanzen zugreifen.

## Was es bewusst nicht tut

ChurchTools bleibt das führende System. Das Bundle baut nichts nach, was dort schon vorhanden ist - E-Mail-Versand, Routinen, Follow-ups und Anmeldelogik bleiben vollständig in ChurchTools.

## Installation

```bash
composer require samuelheer/contao-churchtools-bundle
vendor/bin/contao-console contao:migrate
```

## Einrichtung

### 1. Dienstkonto in ChurchTools

Eine eigene Person mit eigener Berechtigungsgruppe anlegen, die **nur Leserechte** auf die freizugebenden Kalender und Gruppen hat, und dafür ein Logintoken erzeugen.

Kein persönliches Token verwenden: Es hängt an einer Person, stirbt mit deren Konto und schreibt jede Änderung im ChurchTools-Log auf deren Namen.

### 2. Token in die Umgebung

```dotenv
# .env.local
CHURCHTOOLS_TOKEN_SCHEPPACH=…
CHURCHTOOLS_TOKEN_NEUHUETTEN=…
```

Das Token wird **nie** in der Datenbank gespeichert - im Backend steht nur der Name der Variablen. So landet es weder in einem Datenbank-Backup noch in einem Export.

### 3. Verbindung anlegen

Backend → **System → ChurchTools-Verbindungen**:

| Feld | Beispiel |
|---|---|
| Bezeichnung | ETG Scheppach |
| Adresse | `https://etg.church.tools` |
| Name der Umgebungsvariablen | `CHURCHTOOLS_TOKEN_SCHEPPACH` |
| Ordner für Bilder | `files/etgscheppach/churchtools` |
| Freigabegruppe für Personen | ID der Gruppe „Website öffentlich" |

Die Übersicht zeigt an, ob das Token in der Umgebung gefunden wurde. Fehlt es, steht das dort - statt dass der Abgleich stillschweigend nichts tut.

### 4. Website zuordnen

In den Seiteneinstellungen der **Startseite** die Verbindung auswählen. Sie ist der Standardkontext für Insert-Tags und Inhaltselemente dieser Website.

### 5. Kalender zuordnen

Einen Contao-Kalender öffnen, Verbindung wählen, speichern, dann die gewünschten ChurchTools-Kalender ankreuzen.

> Achtung: `isPublic` am ChurchTools-Kalender taugt nicht als Filter - auch Putzplan, Sitzungen und private Belegungen stehen dort auf „öffentlich". Deshalb wird hier ausdrücklich ausgewählt.

Ein zugeordneter Kalender gehört ab dann dem Abgleich: Seine Termine sind im Backend schreibgeschützt, weil der nächste Lauf Handarbeit sonst stillschweigend überschreiben würde.

## Abgleich

Läuft stündlich über Contaos Cron. Manuell:

```bash
# zeigt nur an, was geschehen würde
vendor/bin/contao-console churchtools:sync --dry-run

# führt aus
vendor/bin/contao-console churchtools:sync
```

Was in ChurchTools gelöscht wurde, wird in Contao **deaktiviert, nicht gelöscht**. So leert ein API-Fehler nie den Kalender.

## Personen anzeigen

### Insert-Tags

```
{{ct_person::42}}                 Person 42, Name im Fließtext
{{ct_person::42::card}}           Person 42 als Karte
{{ct_person_group::17}}           alle freigegebenen Mitglieder der Gruppe 17
{{ct_person_group::17::3}}        nur Rolle 3, z. B. die Leitung
{{ct_person_group::17::3::card}}  dieselbe Auswahl als Karte
```

### Inhaltselement

„ChurchTools → Person": feste Person oder Rolle einer Gruppe, Gruppen und Rollen kommen als Auswahlliste aus der API.

Darstellungen: `card`, `inline`, `contact`, `list`. Alle Templates liegen unter `@Contao/churchtools/person_*.html.twig` und lassen sich im Projekt überschreiben.

### Flyerreihe

„ChurchTools → Termin-Flyer" zeigt die Flyer der nächsten Termine. Ein Flyer verschwindet von selbst, sobald sein Termin vorbei ist - es gibt keinen Anzeigezeitraum mehr zu pflegen.

## Datenschutz

Das Bundle ist hier absichtlich streng:

- **Personendaten werden nicht in die Contao-Datenbank gespiegelt.** Sie werden bei Bedarf geholt und kurz zwischengespeichert.
- **Ausgeliefert wird nur, wer in der Freigabegruppe steht.** Ist keine Gruppe konfiguriert, erscheint niemand - eine fehlende Einstellung wird nicht als Vollfreigabe ausgelegt. Das gilt auch, wenn eine Personen-ID direkt in einem Insert-Tag steht.
- **Nur ausgewählte Felder verlassen ChurchTools.** Voreingestellt sind Bild, Vor- und Nachname sowie Funktion. E-Mail und Telefon sind ausgeschaltet; wo sie aktiviert werden, gibt das Template die Adresse verschleiert aus.
- **Das Bildrecht aus ChurchTools wird respektiert.** Ohne gesetztes `privacy_policy_use_image` wird kein Bild ausgeliefert.
- **Personenbilder laufen über eine eigene Route** und werden nie im Web-Verzeichnis abgelegt. Fehlt die Freigabe, antwortet sie mit 404 - nicht mit einem Platzhalter, der die Existenz der Person bestätigen würde.
- **Caches sind pro Verbindung getrennt.** Mandanten können sich nicht gegenseitig sehen.

## Ausfallverhalten

Ist ChurchTools nicht erreichbar, bleibt die Website vollständig: Termine liegen ohnehin in der Contao-Datenbank, Personen kommen aus einem unbefristet abgelegten letzten guten Stand. Es erscheint keine Fehlermeldung im Frontend.

## Getestet gegen

ChurchTools 3.135.1, Contao 5.7, PHP 8.3.

## Lizenz

LGPL-3.0-or-later
