<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['CTE']['churchtools'] = 'ChurchTools';
$GLOBALS['TL_LANG']['CTE']['churchtools_person'] = ['Person', 'Shows a person from ChurchTools - either a fixed person or whoever holds a role in a group.'];
$GLOBALS['TL_LANG']['CTE']['churchtools_event_flyer'] = ['Event flyers', 'Shows the flyers of upcoming events. Past events disappear on their own.'];

$GLOBALS['TL_LANG']['MSC']['churchtools_preview'] = [
    'noConnection' => 'The root page of this website has no ChurchTools connection - nothing is shown.',
    'noRelease' => 'The connection has no person release group - nobody is shown.',
    'group' => 'Group "%s": %d active members, %d of them released.',
    'groupRole' => 'Group "%s", role "%s": %d active members, %d of them released.',
    'hidden' => 'Not released, not shown: %s',
    'more' => 'and %d more',
    'limitOne' => 'Only the first person of the role is shown.',
    'personHidden' => 'The fixed person (ID %d) is not released and is not shown.',
    'imageFieldOff' => '%s: no image - the "image" field is not released on the connection.',
    'imageNotInGroup' => '%s: no image - not in image release group %d.',
    'imageNoPhoto' => '%s: no image - no photo in ChurchTools.',
    'nobody' => 'Nobody is shown.',
];
