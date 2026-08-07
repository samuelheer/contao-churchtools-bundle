<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

use SamuelHeer\ContaoChurchToolsBundle\Widget\PersonPickerWidget;

$GLOBALS['BE_MOD']['system']['churchtools_connections'] = [
    'tables' => ['tl_churchtools_connection'],
];

// Personenauswahl per Namenssuche statt per ID.
$GLOBALS['BE_FFL']['churchtoolsPersonPicker'] = PersonPickerWidget::class;
