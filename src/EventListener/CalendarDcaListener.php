<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\EventListener;

use Contao\CalendarModel;
use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Environment;
use Contao\Input;
use Contao\Message;
use SamuelHeer\ContaoChurchToolsBundle\ChurchTools\ChurchToolsClient;
use SamuelHeer\ContaoChurchToolsBundle\Connection\ConnectionRegistry;
use SamuelHeer\ContaoChurchToolsBundle\Exception\ChurchToolsException;
use SamuelHeer\ContaoChurchToolsBundle\Sync\AppointmentSync;

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
        private readonly AppointmentSync $sync,
        private readonly ContaoFramework $framework,
    ) {
    }

    /**
     * Führt den Abgleich eines einzelnen Kalenders auf Knopfdruck aus.
     *
     * Contao ruft diesen Callback bei jedem Laden der Kalenderliste auf; gehandelt
     * wird nur, wenn tatsächlich die Schaltfläche gedrückt wurde.
     */
    #[AsCallback(table: 'tl_calendar', target: 'config.onload')]
    public function handleSyncRequest(DataContainer|null $dc = null): void
    {
        if ('churchtoolsSync' !== Input::get('key')) {
            return;
        }

        $this->framework->initialize();

        $calendar = CalendarModel::findByPk(Input::get('id'));

        if (null === $calendar || !$calendar->churchtoolsConnection) {
            Message::addError('Dieser Kalender ist keiner ChurchTools-Verbindung zugeordnet.');
            $this->redirectBack();
        }

        try {
            $report = $this->sync->syncCalendar($calendar);
        } catch (ChurchToolsException $e) {
            Message::addError('Abgleich fehlgeschlagen: '.$e->getMessage());
            $this->redirectBack();

            return;
        }

        foreach ($report->errors as $error) {
            Message::addError($error);
        }

        if ([] === $report->errors) {
            $calendar->churchtoolsLastSync = time();
            $calendar->save();
            Message::addConfirmation('Abgleich abgeschlossen: '.$report->summary());
        }

        $this->redirectBack();
    }

    private function redirectBack(): never
    {
        Controller::redirect(str_replace(['&key=churchtoolsSync', '&id='.Input::get('id')], '', Environment::get('requestUri')));
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
