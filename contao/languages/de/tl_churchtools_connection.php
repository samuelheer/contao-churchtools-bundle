<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_churchtools_connection']['title'] = ['Bezeichnung', 'Name der Gemeinde bzw. der Instanz, z. B. „ETG Scheppach".'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['baseUrl'] = ['Adresse', 'Basis-Adresse der ChurchTools-Instanz, z. B. https://etg.church.tools'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['tokenEnvVar'] = ['Name der Umgebungsvariablen', 'Hier steht nur der NAME der Variablen, z. B. CHURCHTOOLS_TOKEN_SCHEPPACH. Das Token selbst gehört in die .env-Datei und niemals in die Datenbank.'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['filesPath'] = ['Ordner für Bilder', 'Zielordner für importierte Flyer, z. B. files/etgscheppach/churchtools'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['publicPersonGroupId'] = ['Freigabegruppe für Personen', 'ID der ChurchTools-Gruppe, die festlegt, wer auf der Website erscheinen darf. Ohne Gruppe wird keine Person ausgeliefert.'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['imageReleaseGroupId'] = ['Freigabegruppe für Bilder (optional)', 'Leer: Das ChurchTools-Profilfoto erscheint bei jeder freigegebenen Person - wer es hochlädt, zeigt es bewusst. Mit Gruppe: nur bei deren aktiven Mitgliedern.'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['emailDomains'] = ['Erlaubte E-Mail-Domains', 'Kommagetrennt, z. B. etg-scheppach.de. Dann erscheint nur eine Adresse dieser Domain - auch wenn sie in ChurchTools nicht die Standardadresse ist. Private Adressen bleiben verborgen. Leer: die Standardadresse.'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['personFields'] = ['Freigegebene Personenfelder', 'Nur diese Felder verlassen ChurchTools. Das Bild ist das Profilfoto aus ChurchTools.'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['disable'] = ['Deaktivieren', 'Die Verbindung vorübergehend stilllegen.'];

$GLOBALS['TL_LANG']['tl_churchtools_connection']['personFieldOptions'] = [
    'image' => 'Bild',
    'firstName' => 'Vorname',
    'lastName' => 'Nachname',
    'job' => 'Funktion',
    'email' => 'E-Mail',
    'mobile' => 'Telefon',
];

$GLOBALS['TL_LANG']['tl_churchtools_connection']['title_legend'] = 'Verbindung';
$GLOBALS['TL_LANG']['tl_churchtools_connection']['files_legend'] = 'Dateien';
$GLOBALS['TL_LANG']['tl_churchtools_connection']['person_legend'] = 'Personen';
$GLOBALS['TL_LANG']['tl_churchtools_connection']['publish_legend'] = 'Veröffentlichung';

$GLOBALS['TL_LANG']['tl_churchtools_connection']['new'] = ['Neue Verbindung', 'Eine neue ChurchTools-Verbindung anlegen'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['edit'] = ['Verbindung bearbeiten', 'Verbindung ID %s bearbeiten'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['copy'] = ['Verbindung duplizieren', 'Verbindung ID %s duplizieren'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['delete'] = ['Verbindung löschen', 'Verbindung ID %s löschen'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['toggle'] = ['Verbindung aktivieren', 'Verbindung ID %s aktivieren/deaktivieren'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['show'] = ['Details anzeigen', 'Details der Verbindung ID %s anzeigen'];
