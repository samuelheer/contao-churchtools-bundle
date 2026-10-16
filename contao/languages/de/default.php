<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['CTE']['churchtools'] = 'ChurchTools';
$GLOBALS['TL_LANG']['CTE']['churchtools_person'] = ['Person', 'Zeigt eine Person aus ChurchTools - fest gewählt oder als Rolle einer Gruppe.'];
$GLOBALS['TL_LANG']['CTE']['churchtools_event_flyer'] = ['Termin-Flyer', 'Zeigt die Flyer der nächsten Termine. Vorbei ist vorbei - Flyer verschwinden von selbst.'];

$GLOBALS['TL_LANG']['MSC']['churchtools_preview'] = [
    'noConnection' => 'Die Startseite dieser Website hat keine ChurchTools-Verbindung - es wird nichts angezeigt.',
    'noRelease' => 'An der Verbindung ist keine Freigabegruppe für Personen hinterlegt - es erscheint niemand.',
    'group' => 'Gruppe „%s“: aktiv %d, davon freigegeben %d.',
    'groupRole' => 'Gruppe „%s“, Rolle „%s“: aktiv %d, davon freigegeben %d.',
    'hidden' => 'Nicht freigegeben, erscheinen nicht: %s',
    'more' => 'und %d weitere',
    'limitOne' => 'Es wird nur die erste Person der Rolle angezeigt.',
    'personHidden' => 'Die feste Person (ID %d) ist nicht freigegeben und erscheint nicht.',
    'imageFieldOff' => '%s: ohne Bild - das Feld „Bild“ ist an der Verbindung nicht freigegeben.',
    'imageNotInGroup' => '%s: ohne Bild - nicht in der Bildfreigabegruppe %d.',
    'imageNoPhoto' => '%s: ohne Bild - in ChurchTools ist kein Foto hinterlegt.',
    'nobody' => 'Es wird niemand angezeigt.',
];
