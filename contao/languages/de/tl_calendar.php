<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_calendar']['churchtools_legend'] = 'ChurchTools';
$GLOBALS['TL_LANG']['tl_calendar']['churchtoolsConnection'] = ['ChurchTools-Verbindung', 'Aus welcher Instanz die Termine kommen. Ist eine Verbindung gewählt, pflegt der Abgleich diesen Kalender - Änderungen von Hand werden dann überschrieben.'];
$GLOBALS['TL_LANG']['tl_calendar']['churchtoolsCalendarIds'] = ['ChurchTools-Kalender', 'Welche Kalender übernommen werden. Die Liste kommt aus ChurchTools; nach dem Wechsel der Verbindung kurz speichern.'];
$GLOBALS['TL_LANG']['tl_calendar']['churchtoolsLinkAsSource'] = ['Termin-Link statt Detailseite', 'Ist am Termin ein Link hinterlegt, führt der Titel direkt dorthin. Ohne diese Option bleibt die Detailseite samt Flyer erhalten.'];
$GLOBALS['TL_LANG']['tl_calendar']['churchtoolsAuthor'] = ['Autor importierter Termine', 'Wird bei automatisch angelegten Terminen eingetragen.'];

$GLOBALS['TL_LANG']['tl_calendar']['churchtoolsSyncInterval'] = ['Abgleich-Intervall', 'Wie oft der automatische Abgleich läuft. „Nur manuell" heißt: ausschließlich über die Schaltfläche oder die Konsole.'];
$GLOBALS['TL_LANG']['tl_calendar']['churchtoolsLastSync'] = ['Letzter Abgleich', 'Zeitpunkt des letzten erfolgreichen Laufs.'];
$GLOBALS['TL_LANG']['tl_calendar']['churchtoolsSync'] = ['Jetzt abgleichen', 'Termine aus ChurchTools für Kalender ID %s sofort holen'];

$GLOBALS['TL_LANG']['tl_calendar']['churchtoolsSyncIntervalOptions'] = [
    'hourly' => 'Stündlich',
    'daily' => 'Täglich',
    'manual' => 'Nur manuell',
];
