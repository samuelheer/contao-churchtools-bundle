<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_content']['churchtools_legend'] = 'ChurchTools';
$GLOBALS['TL_LANG']['tl_content']['churchtoolsConnection'] = ['ChurchTools-Verbindung', 'Leer lassen, dann gilt die Verbindung dieser Website.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsGroupId'] = ['Gruppe', 'Aus welcher Gruppe die Personen kommen. Wechselt die Zuständigkeit in ChurchTools, wechselt die Anzeige automatisch mit.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsRoleId'] = ['Rolle', 'Auf eine Rolle eingrenzen, z. B. Leitung. Ohne Auswahl erscheinen alle freigegebenen Mitglieder.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsGroupLimit'] = ['Anzahl', 'Alle Personen dieser Rolle oder nur eine.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsPersonId'] = ['Zusätzliche Person', 'Optional: eine feste Person, die nach den Personen aus der Gruppe erscheint. Auch allein nutzbar, dann die Gruppe leer lassen.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsVariant'] = ['Darstellung', 'Wie die Person dargestellt wird.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsCalendars'] = ['Kalender', 'Aus welchen Kalendern die Flyer kommen.'];

$GLOBALS['TL_LANG']['tl_content']['churchtoolsGroupLimitOptions'] = [
    0 => 'Alle',
    1 => 'Nur eine Person',
];

$GLOBALS['TL_LANG']['tl_content']['churchtoolsVariantOptions'] = [
    'card' => 'Karte (Bild, Name, Funktion)',
    'inline' => 'Nur Name, im Fließtext',
    'contact' => 'Ansprechpartner mit Kontaktweg',
    'list' => 'Liste (mehrere nebeneinander)',
];
