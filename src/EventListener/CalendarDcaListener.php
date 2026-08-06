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
use SamuelHeer\ContaoChurchToolsBundle\Connection\ConnectionRegistry;
use SamuelHeer\ContaoChurchToolsBundle\Exception\ChurchToolsException;

/**
 * Auswahlfelder für die Kalender- und Verbindungszuordnung.
 *
 * Die Kalenderliste kommt aus der API, damit niemand IDs abtippen muss.
 */
class CalendarDcaListener
{
    public function __construct(
        private readonly ConnectionRegistry $registry,
        private readonly ChurchToolsClient $client,
    ) {
    }

    /**
     * @return array<int, string>
     */
    #[AsCallback(table: 'tl_calendar', target: 'fields.churchtoolsConnection.options')]
    #[AsCallback(table: 'tl_page', target: 'fields.churchtoolsConnection.options')]
    public function getConnections(): array
    {
        $options = [];

        foreach ($this->registry->all() as $connection) {
            $options[$connection->id] = $connection->title;
        }

        return $options;
    }

    /**
     * Kalender der gewählten Instanz. Ohne gewählte Verbindung bleibt die Liste
     * leer - das ist deutlicher als eine Liste, die zum falschen Mandanten gehört.
     *
     * @return array<int, string>
     */
    #[AsCallback(table: 'tl_calendar', target: 'fields.churchtoolsCalendarIds.options')]
    public function getChurchToolsCalendars(DataContainer|null $dc = null): array
    {
        $connection = $this->registry->find((int) ($dc?->activeRecord?->churchtoolsConnection ?? 0));

        if (null === $connection) {
            return [];
        }

        try {
            $calendars = $this->client->getCalendars($connection);
        } catch (ChurchToolsException) {
            return [];
        }

        $options = [];

        foreach ($calendars as $calendar) {
            $options[$calendar['id']] = $calendar['name'].' ('.$calendar['id'].')';
        }

        return $options;
    }
}
