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
    .'{churchtools_legend},churchtoolsGroupId,churchtoolsRoleId,churchtoolsGroupLimit,churchtoolsPersonId;'
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

/*
 * Gruppe/Rolle und feste Person lassen sich kombinieren: z. B. "die Leitung der
 * Spürnasen" plus der Pastor als feste Ansprechperson. Beides ist optional;
 * die Personen aus der Gruppe kommen zuerst, die feste Person danach.
 */

// Neu laden beim Wechsel, damit die Rollenliste zum Gruppentyp passt.
$GLOBALS['TL_DCA']['tl_content']['fields']['churchtoolsGroupId'] = [
    'inputType' => 'select',
    'exclude' => true,
    'eval' => ['includeBlankOption' => true, 'chosen' => true, 'submitOnChange' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
];

$GLOBALS['TL_DCA']['tl_content']['fields']['churchtoolsRoleId'] = [
    'inputType' => 'select',
    'exclude' => true,
    'eval' => ['includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
];

// 0 = alle, 1 = nur eine Person (die erste in der Reihenfolge von ChurchTools)
$GLOBALS['TL_DCA']['tl_content']['fields']['churchtoolsGroupLimit'] = [
    'inputType' => 'select',
    'exclude' => true,
    'options' => [0, 1],
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['churchtoolsGroupLimitOptions'],
    'eval' => ['tl_class' => 'w50'],
    'sql' => ['type' => 'smallint', 'unsigned' => true, 'default' => 0],
];

// Namenssuche statt ID-Eingabe: live gegen die zentrale ChurchTools-Suche.
$GLOBALS['TL_DCA']['tl_content']['fields']['churchtoolsPersonId'] = [
    'inputType' => 'churchtoolsPersonPicker',
    'exclude' => true,
    'eval' => ['tl_class' => 'clr'],
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
