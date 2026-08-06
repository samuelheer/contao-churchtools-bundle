<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

/*
 * Die Verbindung an der Startseite ist der Standardkontext für alles, was im
 * Frontend ohne eigene Zuordnung gerendert wird - Insert-Tags, Inhaltselemente.
 * So können mehrere Gemeinde-Websites in einer Contao-Installation auf
 * unterschiedliche ChurchTools-Instanzen zugreifen.
 */
PaletteManipulator::create()
    ->addLegend('churchtools_legend', 'global_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('churchtoolsConnection', 'churchtools_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('root', 'tl_page')
    ->applyToPalette('rootfallback', 'tl_page')
;

$GLOBALS['TL_DCA']['tl_page']['fields']['churchtoolsConnection'] = [
    'inputType' => 'select',
    'exclude' => true,
    'eval' => [
        'includeBlankOption' => true,
        'chosen' => true,
        'tl_class' => 'w50',
    ],
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
];
