<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\Exception;

/**
 * ChurchTools hat nicht oder fehlerhaft geantwortet. Aufrufer sollen darauf mit
 * dem letzten bekannten Stand reagieren, nicht mit einem Fehler im Frontend.
 */
class ChurchToolsUnavailableException extends ChurchToolsException
{
}
