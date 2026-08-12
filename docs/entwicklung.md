# Entwicklungsnotizen

Warum dieses Bundle so gebaut ist, wie es gebaut ist - und welche Fallen unterwegs zugeschnappt sind. Gedacht für den Moment, in dem man nach Monaten zurückkommt und sich fragt, warum eine Stelle so aussieht.

Alle technischen Angaben sind gegen **Contao 5.7** und **ChurchTools 3.135** verifiziert.

---

## 1. Die Leitentscheidungen

### ChurchTools ist das führende System

Das Bundle baut **nichts** nach, was ChurchTools bereits kann. E-Mail-Versand, Routinen, Follow-ups, Anmeldelogik, Wartelisten - alles bleibt dort. Contao bekommt eine Oberfläche und eine Anbindung, keine zweite Geschäftslogik.

Der Grund ist nicht Faulheit, sondern Wartbarkeit: Jede nachgebaute Logik driftet mit dem nächsten ChurchTools-Update auseinander, und niemand merkt es, bis etwas falsch verschickt wird.

### Termine werden gespiegelt, Personen nicht

Zwei bewusst unterschiedliche Wege:

| | Termine | Personen |
|---|---|---|
| Speicherung | in `tl_calendar_events` | **gar nicht**, nur Cache |
| Warum | Contaos Listen, Reader, Suche, Feeds und Cache-Invalidierung sollen unverändert funktionieren | Personendaten haben auf einem Webserver nichts dauerhaft verloren, und ein Spiegel müsste bei jedem Austritt nachgezogen werden |
| Ausfall | Website läuft weiter, Daten liegen lokal | Rückfall auf einen unbefristet abgelegten letzten guten Stand |

Für Termine wird ausdrücklich die **native** Contao-Tabelle benutzt, kein eigenes Modell. Dadurch funktionieren bestehende Module und Fremderweiterungen ohne eine einzige Anpassung.

### Freigabe ist eine Bringschuld

Ausgeliefert wird nur, wer in der konfigurierten Freigabegruppe steht. Ist keine Gruppe hinterlegt, erscheint **niemand** - eine fehlende Einstellung gilt nicht als Vollfreigabe. Geprüft wird zentral im `PersonProvider`, nicht in den Templates; deshalb greift die Sperre auch, wenn jemand eine Personen-ID hart in ein Insert-Tag schreibt.

### Mandantenfähigkeit von Anfang an

Eine Contao-Installation kann mehrere Websites mit **verschiedenen** ChurchTools-Instanzen bedienen. Deshalb ist die Verbindung eine eigene Entität (`tl_churchtools_connection`), keine Bundle-Konfiguration.

Die Instanz ist am Inhaltselement **absichtlich nicht wählbar**. Sie kommt von der Startseite - sonst könnte ein Element Personen einer fremden Gemeinde zeigen, und ein Redakteur müsste eine technische Entscheidung treffen, die er nicht überblicken kann.

Was daraus folgt und konsequent durchgehalten werden muss:

- **Cache-Schlüssel enthalten immer die Verbindungs-ID.** Ohne das sähe Mandant B die Personen von Mandant A - der schlimmste denkbare Fehler in diesem Bundle.
- **Dateipfade** liegen pro Verbindung.
- **Ein Verbindungsfehler betrifft nur seinen Mandanten**, die übrigen synchronisieren weiter.

### Tokens gehören nicht in die Datenbank

In der Verbindung steht nur der **Name** einer Umgebungsvariablen. So landet das Token weder in einem Datenbank-Backup noch in einem Export. Das Backend zeigt an, ob die Variable gefunden wurde - sonst sucht man den Fehler an der falschen Stelle.

---

## 2. Aufbau

```
Connection ─────► ConnectionRegistry ─────► ChurchToolsClient
(eine Instanz)     lädt Verbindungen,        HTTP, Auth, Retry,
                   löst Token auf,           Pagination
                   findet die Verbindung
                   zu Seite / Element
                            │
        ┌───────────────────┴───────────────────┐
        ▼                                       ▼
   AppointmentSync                        PersonProvider
   Serien, Mapping,                       Freigabe, Cache,
   Löscherkennung                         Stale-Fallback
        │                                       │
   ImageImporter                          GroupRoleResolver
   Flyer + Fokuspunkt                     „Leitung der Gruppe X"
        │                                       │
        ▼                                       ▼
   tl_calendar_events                   PersonRenderer / Insert-Tags
                                        AvatarController (Bild-Proxy)
```

Die Verbindung wird **jedem Client-Aufruf explizit übergeben**. Das ist etwas mehr Tipparbeit, macht aber an jeder Aufrufstelle sichtbar, für welchen Mandanten Daten geholt werden. Bei einem Bundle, dessen schlimmster Fehlerfall Datenvermischung ist, ist das die Mühe wert.

---

## 3. Fallstricke in Contao

### Twig-Templates werden flach eingelesen

**Das kostet garantiert einen halben Tag, wenn man es nicht weiß.** Contaos `TemplateLocator` scannt ein Bundle-Template-Verzeichnis mit `Finder->depth('< 1')`, also **flach**, solange es nicht als Namespace-Root markiert ist. Der Unterordner fällt dabei aus dem logischen Namen:

```
Datei:    contao/templates/content_element/foo.html.twig
Name:     @Contao/foo.html.twig            ← content_element/ fehlt
Gesucht:  @Contao/content_element/foo.html.twig
```

Der Fragment-Controller findet sein Template dann nie. Die Lösung ist die leere Marker-Datei **`contao/templates/.twig-root`** - danach bleiben Unterordner Teil des Namens.

> In einem **Projekt** (nicht Bundle) ist dieser Marker mit Vorsicht zu genießen: Dort bricht er die Override-Wirkung vorhandener `.html5`-Templates. In einem Bundle ohne Legacy-Templates ist er gefahrlos.

Zwei Anschlussfallen:

1. Die Twig-Hierarchie wird **nur beim Cache-Warmup** aufgebaut. Nach dem Verschieben von Templates reicht `cache:clear` nicht - es braucht `rm -rf var/cache/prod` plus `cache:warmup`.
2. `debug:contao-twig` in `dev` lieferte hier nur zwei Einträge statt 515 und damit ein völlig irreführendes Bild. **Immer gegen `prod` prüfen:**

```bash
vendor/bin/contao-console debug:contao-twig --env=prod | grep <name>
```

### Dynamische Template-Namen brauchen Array-Notation

```twig
{# bricht ab: "Optional templates are only supported in array notation" #}
{% include '@Contao/foo_' ~ variant ~ '.html.twig' %}

{# richtig #}
{% include ['@Contao/foo_' ~ variant ~ '.html.twig'] %}
```

Wer den Variantennamen aus der Datenbank nimmt, sollte ihn zusätzlich gegen eine feste Liste prüfen - sonst ist der Template-Pfad von außen steuerbar.

### Der Bundle-Alias wird aus dem Klassennamen abgeleitet

Symfony erwartet zu `ContaoChurchToolsBundle` den Alias `contao_church_tools`. Wer einen anderen will (weil „ChurchTools" ein Wort ist), muss `getContainerExtension()` überschreiben - sonst bricht schon die Installation ab.

### Contao 5.7 hat echte Boolean-Spalten

`$model->published = ''` wirft im Strict Mode einen SQL-Fehler:

```
Incorrect integer value: '' for column 'addImage'
```

Also `true` / `false` statt `'1'` / `''`. Betrifft alle Flags: `published`, `addImage`, `addTime`, `overwriteMeta`, `fullsize`.

### Die Überschrift setzt Contao selbst

`AbstractContentElementController` bereitet `headline` bereits als Struktur auf. Wer im eigenen Controller `$template->set('headline', $model->headline)` schreibt, überschreibt sie mit dem rohen serialisierten Datenbankwert - `_base.html.twig` greift dann auf einem String nach `.text` und die Seite quittiert mit 500.

### Widgets kommen nicht aus dem Container

Contao erzeugt Backend-Widgets nicht über Dependency Injection. Services, die ein Widget braucht, müssen `public: true` sein und über `System::getContainer()` geholt werden.

### Callback-Ziele heißen anders als gedacht

`list.label.label` - **nicht** `list.label.label_callback`. Ein falsches Ziel wirft keinen Fehler, der Callback läuft nur stillschweigend nie. Die gültigen Namen findet man am schnellsten so:

```bash
grep -rhoE "target: '[a-z._]+'" vendor/contao | sort -u
```

---

## 4. Fallstricke in der ChurchTools-API

### Serien sind bereits aufgelöst - aber die ID wiederholt sich

Der Termin-Endpunkt liefert **pro Instanz** einen Eintrag. Aber:

- `base.startDate` ist das Datum der **Serie**
- `calculated.startDate` das der **Instanz**
- **`base.id` ist bei allen Instanzen einer Serie gleich**

Eindeutig wird eine Instanz also erst über **`base.id` + Startzeitpunkt**. Wer nur die ID als Schlüssel nimmt, lässt eine wöchentliche Serie zu einem einzigen Termin kollabieren.

Eigene Auflösung von `repeatOption` und `exceptions` ist nicht nötig.

### `calendar_ids[]` wird ignoriert

Der Parameter am appointments-Endpunkt hat keine Wirkung - die Antwort ist mit und ohne identisch. Also **ein Aufruf je Kalender**. Nebeneffekt: Die Kalenderzugehörigkeit ist dadurch ohnehin eindeutig.

### Das Kalendermerkmal „öffentlich" ist als Filter wertlos

Es steht auch bei internen Kalendern (Putzplan, Sitzungen, private Belegungen) auf `true`. Brauchbar ist stattdessen **`isInternal` am einzelnen Termin**. Kalender müssen im Backend ausdrücklich ausgewählt werden.

### Bild-URLs sind öffentlich - und der Login-Header stört

```
mit  Authorization: Login …  →  HTTP 302, leer
ohne Authorization           →  HTTP 200, Bild
```

Downloads deshalb zuerst **ohne** Anmeldung anfordern und nur bei Ablehnung auf den angemeldeten Versuch zurückfallen. Andernfalls kommt kein einziges Bild an - und der Fehler sieht aus wie ein Berechtigungsproblem.

### Personenfelder heißen anders als erwartet

- Die Funktionsbezeichnung steht in **`job`**, nicht in `jobTitle` (letzteres ist immer `null`)
- Das Bildrecht in **`privacy_policy_use_image`**, ein echter Boolean

> Erfahrungswert: In einer gewachsenen Installation ist dieses Feld oft bei **niemandem** gesetzt. Dann erscheint kein einziges Personenbild - technisch völlig korrekt, aber für den Betreiber überraschend. Das gehört in die Einführungskommunikation, nicht in den Code.

### Rollen kommen von `/api/group/roles`

`/api/masterdata/groups/roles` existiert nicht (404). Die Rollen tragen ein `isLeader`-Flag, mit dem sich Leitungsrollen im Backend kennzeichnen lassen.

### Zeiten sind UTC

Sommer- und Winterzeit sind in den Rohdaten sichtbar (`08:00Z` wird zu `09:00Z` bei konstant 10 Uhr lokal). Wichtig dabei:

**Ganztagestermine dürfen nicht in die lokale Zeitzone umgerechnet werden.** Sie sind reine Datumsangaben; eine Umrechnung schiebt sie je nach Offset um einen Tag. Das Kalenderdatum unverändert übernehmen und lokal auf Mitternacht setzen.

### Pagination ist Pflicht

Listen kommen seitenweise, `meta.pagination.lastPage` beachten. Ein großzügiges `limit` kann leer zurückkommen - 100er-Seiten sind verlässlich.

---

## 5. Zwei Fehler, die den Datenbestand still zerstört hätten

Beide sind behoben, aber lehrreich - sie zeigen die Klasse von Fehlern, auf die man bei einem Sync achten muss.

### Löscherkennung ohne Fensterbegrenzung

Der Abgleich fragt ein Zeitfenster ab (voreingestellt ein Monat zurück, zwölf voraus), lud zur Löscherkennung aber **alle** importierten Termine des Kalenders. Alles außerhalb des Fensters galt damit als „in ChurchTools gelöscht" und wurde bei **jedem Lauf** abgeschaltet - das Terminarchiv hätte sich über Monate stillschweigend geleert.

**Regel:** Die Löscherkennung darf nur den Bereich betrachten, den die Abfrage tatsächlich abgedeckt hat.

Deshalb auch: Verschwundene Einträge werden **deaktiviert, nicht gelöscht**. Ein API-Fehler leert so nie den Kalender.

### Änderungs-Hash trotz misslungenem Teilschritt

Schlug der Bild-Download fehl, wurde der Feld-Hash trotzdem gespeichert. Beim nächsten Lauf galt der Termin als unverändert - das Bild wäre **nie** nachgeholt worden. Jetzt wird der Hash bei einem Fehlschlag verworfen.

**Regel:** Ein Hash über den Quellzustand darf erst gesetzt werden, wenn der Zielzustand vollständig hergestellt ist.

---

## 6. Bekannte Grenzen und offene Punkte

### Die Freigabeprüfung ist noch zu grob

`releasedIds()` nimmt **jedes** Mitglied der Freigabegruppe, ohne weitere Prüfung. Damit gilt als freigegeben, wer eigentlich nicht sollte:

| Feld | Folge |
|---|---|
| `groupMemberStatus` | Bei einer Gruppe mit Anmeldung zählen auch **„angefragt"** und **Warteliste** als freigegeben |
| `memberEndDate` | Ein Mitglied mit Enddatum in der Vergangenheit bleibt sichtbar |
| `isArchived`, `dateOfDeath` | Werden nur in der Backend-Suche gefiltert, nicht beim Ausliefern |

Solange die Freigabegruppe eine handverlesene Verwaltungsgruppe ist, fällt das nicht auf. Sobald sie eine Gruppe wird, in die man sich anmelden kann, kippt es. **Zu schließen in `PersonProvider::releasedIds()`.**

### Weitere Grenzen

- **Ein Bild pro Termin.** Mehrere Flyer sieht die Datenstruktur nicht vor.
- **Verschobene Serien** erzeugen neue Instanzen; die alten werden deaktiviert, nicht verschoben.
- **Keine Tests.** Es gibt weder Unit- noch Funktionstests, nur das manuelle Runbook.
- **Der Abgleich läuft synchron im Cron**, bewusst nicht über einen Messenger-Worker: Auf geteiltem Hosting läuft oft kein dauerhafter Worker, dann würde nie synchronisiert.

---

## 7. Wie man hier weiterarbeitet

### Lokal einbinden

```json
{
    "repositories": [
        { "type": "path", "url": "../../repos/contao-churchtools-bundle", "options": { "symlink": true } }
    ],
    "require": { "samuelheer/contao-churchtools-bundle": "@dev" }
}
```

```bash
composer update samuelheer/contao-churchtools-bundle
vendor/bin/contao-console contao:migrate
```

> Ein Path-Repository ist **nicht deploybar**. Vor einem Deploy muss das Paket über Packagist oder ein VCS-Repository bezogen werden.

### Nach jeder Änderung an Templates oder DCA

```bash
rm -rf var/cache/prod && vendor/bin/contao-console cache:warmup --env=prod
```

### Prüfen, ohne das Backend zu öffnen

```bash
vendor/bin/contao-console churchtools:sync --dry-run
vendor/bin/contao-console churchtools:person --search="<Name>"
vendor/bin/contao-console churchtools:person <ID>
vendor/bin/contao-console debug:contao-twig --env=prod | grep churchtools
```

### Die wichtigste Lehre aus dem Bau

Das Bundle war **statisch vollständig geprüft** - Syntax sauber, alle Klassen ladbar, alle Attribute instanziierbar, jedes `use`-Statement auflösbar. Trotzdem steckten zehn Fehler darin, davon vier so, dass gar nichts funktioniert hätte.

Gefunden wurden sie erst beim Einbinden in eine echte Installation und beim ersten tatsächlichen Seitenaufruf. Bei Contao-Bundles gilt deshalb: **Vor „fertig" steht ein echter Request gegen eine echte Instanz.** Alles davor ist Vorprüfung.

---

## 8. Geplante Ausbaustufen

| Stufe | Inhalt | Stand |
|---|---|---|
| 1 | Termine samt Flyer | umgesetzt |
| 2 | Personen und Ansprechpartner | umgesetzt |
| 3 | Anmeldungen und mehrstufige Formulare auf Basis der ChurchTools-Anmeldung | offen |
| 4 | ChurchTools-Beiträge auf der Website | offen |
| 5 | Verteilung an Drittkanäle | bewusst **außerhalb** dieses Bundles |

Zu Stufe 3: Formularfelder sollen aus der ChurchTools-Anmeldung **gelesen** und im Website-Layout gerendert werden; abgeschickt wird an ChurchTools. Ein eigenes Anmelde-Datenmodell entsteht nicht. Der Mehrwert liegt in der Oberfläche und in einer Dublettenprüfung vor dem Schreiben - Dubletten sind die bekannte Schwäche öffentlicher Anmeldungen. Mehrere Personen in einem Vorgang (Familien) werden in mehrere native Einzelanmeldungen zerlegt.

Zu Stufe 5: Social-Publishing und Newsletter brauchen dauerhaft laufende Prozesse, fremde OAuth-Tokens und Retry-Logik. Im Bundle würde daraus ein Monolith, der bei keiner anderen Gemeinde einsetzbar wäre. Das gehört in einen eigenen Dienst, der ChurchTools ebenfalls als Quelle liest.
