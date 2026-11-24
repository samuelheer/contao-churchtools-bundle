<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

// ChurchTools löst Serien selbst auf, jede Instanz wird ein eigener Contao-Termin.
// Für eine Flyer- oder Kartenreihe soll eine Serie aber nur einmal erscheinen.
PaletteManipulator::create()
    ->addField('churchtoolsNextOnly', 'cal_hideRunning', PaletteManipulator::POSITION_AFTER)
    ->applyToPalette('eventlist', 'tl_module')
;

$GLOBALS['TL_DCA']['tl_module']['fields']['churchtoolsNextOnly'] = [
    'inputType' => 'checkbox',
    'exclude' => true,
    'eval' => ['tl_class' => 'w50 m12'],
    'sql' => ['type' => 'boolean', 'default' => false],
];
