<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_calendar']['churchtools_legend'] = 'ChurchTools';
$GLOBALS['TL_LANG']['tl_calendar']['churchtoolsConnection'] = ['ChurchTools connection', 'Which instance the events come from. Once a connection is set, the sync owns this calendar and manual edits will be overwritten.'];
$GLOBALS['TL_LANG']['tl_calendar']['churchtoolsCalendarIds'] = ['ChurchTools calendars', 'Which calendars to import. The list is loaded from ChurchTools; save once after changing the connection.'];
$GLOBALS['TL_LANG']['tl_calendar']['churchtoolsLinkAsSource'] = ['Use event link instead of detail page', 'If the event carries a link, the title points there directly. Without this option the detail page including the flyer is kept.'];
$GLOBALS['TL_LANG']['tl_calendar']['churchtoolsAuthor'] = ['Author of imported events', 'Set on automatically created events.'];

$GLOBALS['TL_LANG']['tl_calendar']['churchtoolsSyncInterval'] = ['Sync interval', 'How often the automatic sync runs. "Manual only" means: exclusively via the button or the console.'];
$GLOBALS['TL_LANG']['tl_calendar']['churchtoolsLastSync'] = ['Last sync', 'Time of the last successful run.'];
$GLOBALS['TL_LANG']['tl_calendar']['churchtoolsSync'] = ['Sync now', 'Fetch ChurchTools events for calendar ID %s immediately'];

$GLOBALS['TL_LANG']['tl_calendar']['churchtoolsSyncIntervalOptions'] = [
    'hourly' => 'Hourly',
    'daily' => 'Daily',
    'manual' => 'Manual only',
];
