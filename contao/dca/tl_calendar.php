<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()
    ->addLegend('churchtools_legend', 'title_legend', PaletteManipulator::POSITION_AFTER)
    ->addField(
        ['churchtoolsConnection', 'churchtoolsCalendarIds', 'churchtoolsLinkAsSource'],
        'churchtools_legend',
        PaletteManipulator::POSITION_APPEND,
    )
    ->applyToPalette('default', 'tl_calendar')
;

$GLOBALS['TL_DCA']['tl_calendar']['fields']['churchtoolsConnection'] = [
    'inputType' => 'select',
    'exclude' => true,
    'eval' => [
        'includeBlankOption' => true,
        'chosen' => true,
        'submitOnChange' => true,
        'tl_class' => 'w50',
    ],
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['churchtoolsCalendarIds'] = [
    'inputType' => 'checkbox',
    'exclude' => true,
    'eval' => ['multiple' => true, 'tl_class' => 'clr'],
    'sql' => ['type' => 'blob', 'notnull' => false],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['churchtoolsLinkAsSource'] = [
    'inputType' => 'checkbox',
    'exclude' => true,
    'eval' => ['tl_class' => 'clr'],
    'sql' => ['type' => 'boolean', 'default' => false],
];

// Autor für importierte Termine - ohne Angabe bleibt das Feld leer.
$GLOBALS['TL_DCA']['tl_calendar']['fields']['churchtoolsAuthor'] = [
    'inputType' => 'select',
    'exclude' => true,
    'foreignKey' => 'tl_user.name',
    'eval' => ['includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
    'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
];
