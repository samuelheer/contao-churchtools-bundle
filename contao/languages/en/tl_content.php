<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_content']['churchtools_legend'] = 'ChurchTools';
$GLOBALS['TL_LANG']['tl_content']['churchtoolsConnection'] = ['ChurchTools connection', 'Leave empty to use the connection of this website.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsSource'] = ['Who should be shown?', 'A fixed person or a role - with a role the display follows whoever is responsible in ChurchTools.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsPersonId'] = ['Person ID', 'ID of the person in ChurchTools.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsGroupId'] = ['Group', 'Which group the people come from.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsRoleId'] = ['Role', 'Limit to one role, e.g. leader. Without a selection all released members are shown.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsVariant'] = ['Display', 'How the person is rendered.'];
$GLOBALS['TL_LANG']['tl_content']['churchtoolsCalendars'] = ['Calendars', 'Which calendars the flyers come from.'];

$GLOBALS['TL_LANG']['tl_content']['churchtoolsSourceOptions'] = [
    'person' => 'A fixed person',
    'group' => 'A role in a group',
];

$GLOBALS['TL_LANG']['tl_content']['churchtoolsVariantOptions'] = [
    'card' => 'Card (image, name, role)',
    'inline' => 'Name only, inline',
    'contact' => 'Contact with details',
    'list' => 'List (several side by side)',
];
