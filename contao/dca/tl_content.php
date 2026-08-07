<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

/*
 * Bewusst OHNE Auswahl der ChurchTools-Verbindung: Sie wird immer von der
 * Startseite geerbt. Sonst könnte ein Element Personen einer fremden Gemeinde
 * anzeigen, und der Redakteur müsste eine technische Entscheidung treffen,
 * die er nicht überblicken kann.
 */
$GLOBALS['TL_DCA']['tl_content']['palettes']['churchtools_person'] =
    '{type_legend},type,headline;'
    .'{churchtools_legend},churchtoolsSource;'
    .'{template_legend:hide},churchtoolsVariant,customTpl;'
    .'{protected_legend:hide},protected;'
    .'{expert_legend:hide},cssID;'
    .'{invisible_legend:hide},invisible,start,stop';

$GLOBALS['TL_DCA']['tl_content']['palettes']['churchtools_event_flyer'] =
    '{type_legend},type,headline;'
    .'{churchtools_legend},churchtoolsCalendars,numberOfItems;'
    .'{image_legend},size,fullsize;'
    .'{template_legend:hide},customTpl;'
    .'{protected_legend:hide},protected;'
    .'{expert_legend:hide},cssID;'
    .'{invisible_legend:hide},invisible,start,stop';

$GLOBALS['TL_DCA']['tl_content']['subpalettes']['churchtoolsSource_person'] = 'churchtoolsPersonId';
$GLOBALS['TL_DCA']['tl_content']['subpalettes']['churchtoolsSource_group'] = 'churchtoolsGroupId,churchtoolsRoleId';

$GLOBALS['TL_DCA']['tl_content']['palettes']['__selector__'][] = 'churchtoolsSource';

$GLOBALS['TL_DCA']['tl_content']['fields']['churchtoolsSource'] = [
    'inputType' => 'radio',
    'exclude' => true,
    'options' => ['person', 'group'],
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['churchtoolsSourceOptions'],
    'eval' => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'sql' => ['type' => 'string', 'length' => 16, 'default' => 'person'],
];

// Namenssuche statt ID-Eingabe: live gegen die zentrale ChurchTools-Suche.
$GLOBALS['TL_DCA']['tl_content']['fields']['churchtoolsPersonId'] = [
    'inputType' => 'churchtoolsPersonPicker',
    'exclude' => true,
    'eval' => ['mandatory' => true, 'tl_class' => 'clr'],
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
];

$GLOBALS['TL_DCA']['tl_content']['fields']['churchtoolsGroupId'] = [
    'inputType' => 'select',
    'exclude' => true,
    'eval' => ['includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
];

$GLOBALS['TL_DCA']['tl_content']['fields']['churchtoolsRoleId'] = [
    'inputType' => 'select',
    'exclude' => true,
    'eval' => ['includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
];

$GLOBALS['TL_DCA']['tl_content']['fields']['churchtoolsVariant'] = [
    'inputType' => 'select',
    'exclude' => true,
    'options' => ['card', 'inline', 'contact', 'list'],
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['churchtoolsVariantOptions'],
    'eval' => ['tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 16, 'default' => 'card'],
];

$GLOBALS['TL_DCA']['tl_content']['fields']['churchtoolsCalendars'] = [
    'inputType' => 'checkbox',
    'exclude' => true,
    'foreignKey' => 'tl_calendar.title',
    'eval' => ['multiple' => true, 'mandatory' => true, 'tl_class' => 'clr'],
    'sql' => ['type' => 'blob', 'notnull' => false],
    'relation' => ['type' => 'hasMany', 'load' => 'lazy'],
];
