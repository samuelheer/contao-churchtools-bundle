<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

/*
 * Spalten für die Zuordnung zu ChurchTools. Sie sind nicht in einer Palette
 * enthalten - sie werden ausschließlich vom Sync gepflegt und im Backend
 * lediglich als Hinweis angezeigt.
 */

// Schlüssel der Termin-Instanz: base-ID plus Startzeitpunkt. Die base-ID allein
// genügt nicht, weil alle Instanzen einer Serie sie teilen.
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['ctInstanceKey'] = [
    'sql' => ['type' => 'string', 'length' => 64, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['ctAppointmentId'] = [
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['ctCalendarId'] = [
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['ctConnection'] = [
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['ctLink'] = [
    'sql' => ['type' => 'string', 'length' => 255, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['ctHash'] = [
    'sql' => ['type' => 'string', 'length' => 32, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['ctSyncedAt'] = [
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
];

// Index, weil der Sync jeden Lauf über diese Spalte sucht.
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['sql']['keys']['ctInstanceKey'] = 'index';
