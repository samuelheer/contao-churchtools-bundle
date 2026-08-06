<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle;

use SamuelHeer\ContaoChurchToolsBundle\DependencyInjection\ContaoChurchToolsExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ContaoChurchToolsBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * Symfony würde aus dem Bundle-Namen den Alias "contao_church_tools" ableiten.
     * ChurchTools ist aber ein Wort, deshalb die Extension hier ausdrücklich setzen.
     */
    public function getContainerExtension(): ExtensionInterface|null
    {
        return $this->extension ??= new ContaoChurchToolsExtension();
    }
}
