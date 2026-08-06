<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ContaoChurchToolsExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');
    }

    /**
     * Registriert einen eigenen, taggable Cache-Pool. Eigener Pool statt cache.app,
     * damit ein Leeren der ChurchTools-Daten nicht den Anwendungs-Cache mitnimmt.
     */
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('framework', [
            'cache' => [
                'pools' => [
                    'contao_churchtools.cache' => [
                        'adapter' => 'cache.app',
                        'tags' => true,
                    ],
                ],
            ],
        ]);
    }

    public function getAlias(): string
    {
        return 'contao_churchtools';
    }
}
