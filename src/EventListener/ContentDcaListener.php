<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use SamuelHeer\ContaoChurchToolsBundle\ChurchTools\ChurchToolsClient;
use SamuelHeer\ContaoChurchToolsBundle\Connection\Connection;
use SamuelHeer\ContaoChurchToolsBundle\Connection\ConnectionRegistry;
use SamuelHeer\ContaoChurchToolsBundle\Exception\ChurchToolsException;

/**
 * Auswahllisten für das Personen-Inhaltselement.
 *
 * Gruppen und Rollen kommen aus der API, damit im Backend niemand IDs abtippt.
 */
class ContentDcaListener
{
    public function __construct(
        private readonly ConnectionRegistry $registry,
        private readonly ChurchToolsClient $client,
    ) {
    }

    /**
     * @return array<int, string>
     */
    #[AsCallback(table: 'tl_content', target: 'fields.churchtoolsGroupId.options')]
    public function getGroups(DataContainer|null $dc = null): array
    {
        $connection = $this->resolveConnection($dc);

        if (null === $connection) {
            return [];
        }

        try {
            $groups = $this->client->getGroups($connection);
        } catch (ChurchToolsException) {
            return [];
        }

        $options = [];

        foreach ($groups as $group) {
            if (isset($group['id'])) {
                $options[(int) $group['id']] = ($group['name'] ?? '').' ('.$group['id'].')';
            }
        }

        asort($options);

        return $options;
    }

    /**
     * @return array<int, string>
     */
    #[AsCallback(table: 'tl_content', target: 'fields.churchtoolsRoleId.options')]
    public function getRoles(DataContainer|null $dc = null): array
    {
        $connection = $this->resolveConnection($dc);

        if (null === $connection) {
            return [];
        }

        try {
            $roles = $this->client->getGroupTypeRoles($connection);
        } catch (ChurchToolsException) {
            return [];
        }

        $options = [];

        foreach ($roles as $role) {
            if (isset($role['id'])) {
                $options[(int) $role['id']] = ($role['name'] ?? '').' ('.$role['id'].')';
            }
        }

        asort($options);

        return $options;
    }

    /**
     * Am Element gewählte Verbindung, sonst die der zugehörigen Startseite.
     */
    private function resolveConnection(DataContainer|null $dc): Connection|null
    {
        $explicit = $this->registry->find((int) ($dc?->activeRecord?->churchtoolsConnection ?? 0));

        if (null !== $explicit) {
            return $explicit;
        }

        // Im Backend gibt es keine Root-Seite im Request; als Rückfall dient die
        // einzige konfigurierte Verbindung, sofern es nur eine gibt.
        $all = $this->registry->all();

        return 1 === \count($all) ? reset($all) : null;
    }
}
