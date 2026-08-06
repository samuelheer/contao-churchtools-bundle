<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_churchtools_connection']['title'] = ['Name', 'Name of the church or instance.'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['baseUrl'] = ['URL', 'Base URL of the ChurchTools instance, e.g. https://example.church.tools'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['tokenEnvVar'] = ['Environment variable name', 'Only the NAME of the variable, e.g. CHURCHTOOLS_TOKEN_MAIN. The token itself belongs in your .env file, never in the database.'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['filesPath'] = ['Image folder', 'Target folder for imported flyers, e.g. files/church/churchtools'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['publicPersonGroupId'] = ['Person release group', 'ID of the ChurchTools group that decides who may appear on the website. Without a group, no person is served.'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['personFields'] = ['Released person fields', 'Only these fields leave ChurchTools. The image additionally requires image usage to be granted in ChurchTools.'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['disable'] = ['Disable', 'Temporarily disable this connection.'];

$GLOBALS['TL_LANG']['tl_churchtools_connection']['personFieldOptions'] = [
    'image' => 'Image',
    'firstName' => 'First name',
    'lastName' => 'Last name',
    'job' => 'Role',
    'email' => 'Email',
    'mobile' => 'Phone',
];

$GLOBALS['TL_LANG']['tl_churchtools_connection']['title_legend'] = 'Connection';
$GLOBALS['TL_LANG']['tl_churchtools_connection']['files_legend'] = 'Files';
$GLOBALS['TL_LANG']['tl_churchtools_connection']['person_legend'] = 'People';
$GLOBALS['TL_LANG']['tl_churchtools_connection']['publish_legend'] = 'Publishing';

$GLOBALS['TL_LANG']['tl_churchtools_connection']['new'] = ['New connection', 'Create a new ChurchTools connection'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['edit'] = ['Edit connection', 'Edit connection ID %s'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['copy'] = ['Duplicate connection', 'Duplicate connection ID %s'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['delete'] = ['Delete connection', 'Delete connection ID %s'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['toggle'] = ['Toggle connection', 'Enable/disable connection ID %s'];
$GLOBALS['TL_LANG']['tl_churchtools_connection']['show'] = ['Show details', 'Show details of connection ID %s'];
