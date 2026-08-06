<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_content']['churchtools_legend'] = 'ChurchTools';
$GLOBALS['TL_LANG']['tl_content']['churchtoolsConnection'] = ['ChurchTools-Verbindung', 'Leer lassen, dann gilt die Verbindung dieser Website.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsSource'] = ['Wer soll angezeigt werden?', 'Eine feste Person oder eine Rolle - bei einer Rolle wechselt die Anzeige automatisch mit, wenn sich in ChurchTools die Zuständigkeit ändert.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsPersonId'] = ['Personen-ID', 'ID der Person in ChurchTools.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsGroupId'] = ['Gruppe', 'Aus welcher Gruppe die Personen kommen.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsRoleId'] = ['Rolle', 'Auf eine Rolle eingrenzen, z. B. Leitung. Ohne Auswahl erscheinen alle freigegebenen Mitglieder.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsVariant'] = ['Darstellung', 'Wie die Person dargestellt wird.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsCalendars'] = ['Kalender', 'Aus welchen Kalendern die Flyer kommen.'];

$GLOBALS['TL_LANG']['tl_content']['churchtoolsSourceOptions'] = [
    'person' => 'Eine feste Person',
    'group' => 'Eine Rolle in einer Gruppe',
];

$GLOBALS['TL_LANG']['tl_content']['churchtoolsVariantOptions'] = [
    'card' => 'Karte (Bild, Name, Funktion)',
    'inline' => 'Nur Name, im Fließtext',
    'contact' => 'Ansprechpartner mit Kontaktweg',
    'list' => 'Liste (mehrere nebeneinander)',
];
