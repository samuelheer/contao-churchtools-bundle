<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\Sync;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Slug\Slug;
use Contao\StringUtil;
use Psr\Log\LoggerInterface;
use SamuelHeer\ContaoChurchToolsBundle\ChurchTools\ChurchToolsClient;
use SamuelHeer\ContaoChurchToolsBundle\ChurchTools\Dto\Appointment;
use SamuelHeer\ContaoChurchToolsBundle\Connection\Connection;
use SamuelHeer\ContaoChurchToolsBundle\Connection\ConnectionRegistry;
use SamuelHeer\ContaoChurchToolsBundle\Exception\ChurchToolsException;

/**
 * Spiegelt ChurchTools-Termine in Contao-Events.
 *
 * Serien müssen nicht selbst aufgelöst werden: ChurchTools liefert je Instanz
 * einen Eintrag, wobei die base-ID über alle Instanzen einer Serie gleich bleibt.
 * Eindeutig wird eine Instanz erst zusammen mit ihrem Startdatum.
 */
class AppointmentSync
{
    /**
     * Rollierendes Zeitfenster. Der Blick zurück hält gerade vergangene Termine
     * verfügbar (Archiv, Rückschau), der Blick nach vorn deckt die Jahresplanung ab.
     */
    private const WINDOW_PAST = '-1 month';
    private const WINDOW_FUTURE = '+12 months';

    public function __construct(
        private readonly ChurchToolsClient $client,
        private readonly ConnectionRegistry $registry,
        private readonly ImageImporter $imageImporter,
        private readonly ContaoFramework $framework,
        private readonly Slug $slug,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    /**
     * Synchronisiert alle Contao-Kalender, die einer Verbindung zugeordnet sind.
     */
    public function syncAll(bool $dryRun = false, \DateTimeImmutable|null $from = null, \DateTimeImmutable|null $to = null): SyncReport
    {
        $this->framework->initialize();

        $report = new SyncReport();
        $calendars = CalendarModel::findAll();

        if (null === $calendars) {
            return $report;
        }

        foreach ($calendars as $calendar) {
            if (!$calendar->churchtoolsConnection) {
                continue;
            }

            // Ein Fehler darf nur seinen eigenen Mandanten treffen.
            try {
                $report->merge($this->syncCalendar($calendar, $dryRun, $from, $to));
            } catch (ChurchToolsException $e) {
                $report->error(\sprintf('Kalender "%s": %s', $calendar->title, $e->getMessage()));
                $this->logger?->error('ChurchTools-Sync fehlgeschlagen', ['calendar' => $calendar->title]);
            }
        }

        return $report;
    }

    /**
     * Das Zeitfenster lässt sich übersteuern - etwa um bei der Erstbefüllung
     * weiter zurückzugreifen oder einen bestimmten Zeitraum nachzuziehen.
     */
    public function syncCalendar(CalendarModel $calendar, bool $dryRun = false, \DateTimeImmutable|null $from = null, \DateTimeImmutable|null $to = null): SyncReport
    {
        $this->framework->initialize();

        $report = new SyncReport();
        $connection = $this->registry->find((int) $calendar->churchtoolsConnection);

        if (null === $connection) {
            $report->error(\sprintf('Kalender "%s" verweist auf eine unbekannte Verbindung.', $calendar->title));

            return $report;
        }

        $calendarIds = array_map('intval', StringUtil::deserialize($calendar->churchtoolsCalendarIds, true));
        $calendarIds = array_values(array_filter($calendarIds));

        if ([] === $calendarIds) {
            $report->note(\sprintf('Kalender "%s": keine ChurchTools-Kalender ausgewählt.', $calendar->title));

            return $report;
        }

        $from ??= new \DateTimeImmutable(self::WINDOW_PAST);
        $to ??= new \DateTimeImmutable(self::WINDOW_FUTURE);

        /** @var array<string, Appointment> $appointments */
        $appointments = [];

        foreach ($calendarIds as $calendarId) {
            foreach ($this->client->getAppointmentsForCalendar($connection, $calendarId, $from, $to) as $raw) {
                $appointment = Appointment::fromArray($raw, $calendarId);

                if (null === $appointment) {
                    continue;
                }

                // Interne Termine gelangen gar nicht erst in die Verarbeitung.
                if ($appointment->isInternal) {
                    ++$report->skippedInternal;
                    continue;
                }

                $appointments[$appointment->instanceKey()] = $appointment;
            }
        }

        $existing = $this->loadExisting((int) $calendar->id, $from, $to);

        foreach ($appointments as $key => $appointment) {
            $this->upsert($calendar, $connection, $appointment, $existing[$key] ?? null, $report, $dryRun);
            unset($existing[$key]);
        }

        // Was übrig bleibt, gibt es in ChurchTools nicht mehr: abschalten statt löschen.
        foreach ($existing as $orphan) {
            if (!$orphan->published) {
                continue;
            }

            ++$report->disabled;

            if (!$dryRun) {
                $orphan->published = false;
                $orphan->tstamp = time();
                $orphan->save();
            }
        }

        return $report;
    }

    /**
     * Bestehende importierte Termine des Kalenders - eingegrenzt auf das
     * abgefragte Zeitfenster.
     *
     * Ohne diese Eingrenzung gälte jeder Termin außerhalb des Fensters als in
     * ChurchTools gelöscht und würde bei jedem Lauf abgeschaltet. Vergangene
     * Termine würden so nach und nach still verschwinden.
     *
     * @return array<string, CalendarEventsModel>
     */
    private function loadExisting(int $calendarId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $events = CalendarEventsModel::findBy(
            ['pid=?', "ctInstanceKey!=''", 'startTime>=?', 'startTime<=?'],
            [$calendarId, $from->getTimestamp(), $to->getTimestamp()],
        );

        if (null === $events) {
            return [];
        }

        $indexed = [];

        foreach ($events as $event) {
            $indexed[(string) $event->ctInstanceKey] = $event;
        }

        return $indexed;
    }

    private function upsert(
        CalendarModel $calendar,
        Connection $connection,
        Appointment $appointment,
        CalendarEventsModel|null $event,
        SyncReport $report,
        bool $dryRun,
    ): void {
        $hash = $appointment->contentHash();
        $isNew = null === $event;

        // Unverändert und veröffentlicht: nichts tun, damit der Cache ruhig bleibt.
        if (!$isNew && $event->ctHash === $hash && $event->published) {
            ++$report->unchanged;

            return;
        }

        if ($isNew) {
            ++$report->created;
        } else {
            ++$report->updated;
        }

        if ($dryRun) {
            $report->note(\sprintf(
                '%s: %s am %s',
                $isNew ? 'neu' : 'geändert',
                $appointment->caption,
                $appointment->allDay
                    ? $appointment->start->format('d.m.Y').' (ganztägig)'
                    : $appointment->start->setTimezone($this->timezone())->format('d.m.Y H:i'),
            ));

            return;
        }

        $event ??= new CalendarEventsModel();
        $event->pid = $calendar->id;
        $event->tstamp = time();

        $this->applyDates($event, $appointment);

        $event->title = $appointment->caption;
        $event->teaser = $this->buildTeaser($appointment);
        $event->location = $appointment->location ?? '';
        $event->address = $appointment->address ?? '';
        $event->published = true;
        $event->author = (int) ($calendar->churchtoolsAuthor ?: 0);

        if ($isNew || !$event->alias) {
            $event->alias = $this->generateAlias($appointment, (int) $calendar->id);
        }

        // Der Termin-Link ersetzt nur dann die Detailseite, wenn das am Kalender
        // ausdrücklich gewünscht ist - sonst ginge der Flyer im Frontend verloren.
        if ($appointment->link && $calendar->churchtoolsLinkAsSource) {
            $event->source = 'external';
            $event->url = $appointment->link;
            $event->target = true;
        } else {
            $event->source = 'default';
        }

        $event->ctInstanceKey = $appointment->instanceKey();
        $event->ctAppointmentId = $appointment->baseId;
        $event->ctCalendarId = $appointment->calendarId;
        $event->ctConnection = $connection->id;
        $event->ctLink = $appointment->link ?? '';
        $event->ctHash = $hash;
        $event->ctSyncedAt = time();

        $this->applyImage($event, $connection, $appointment, $report);

        $event->save();
    }

    private function applyDates(CalendarEventsModel $event, Appointment $appointment): void
    {
        $timezone = $this->timezone();

        if ($appointment->allDay) {
            // Ganztagestermine sind reine Datumsangaben. Sie dürfen nicht in die
            // lokale Zeitzone umgerechnet werden - je nach Offset läge der Termin
            // sonst einen Tag daneben. Deshalb das Kalenderdatum unverändert
            // übernehmen und lokal auf Mitternacht setzen.
            $startDay = new \DateTimeImmutable($appointment->start->format('Y-m-d').' 00:00:00', $timezone);
            $endDay = new \DateTimeImmutable($appointment->end->format('Y-m-d').' 00:00:00', $timezone);

            $event->addTime = false;
            $event->startDate = $startDay->getTimestamp();
            $event->endDate = $endDay->getTimestamp();
            $event->startTime = $event->startDate;
            $event->endTime = $event->endDate;

            return;
        }

        $start = $appointment->start->setTimezone($timezone);
        $end = $appointment->end->setTimezone($timezone);

        // startDate ist der Tagesbeginn, startTime der vollständige Zeitpunkt.
        $event->startDate = $start->setTime(0, 0)->getTimestamp();
        $event->endDate = $end->setTime(0, 0)->getTimestamp();
        $event->addTime = true;
        $event->startTime = $start->getTimestamp();
        $event->endTime = $end->getTimestamp();
    }

    private function applyImage(CalendarEventsModel $event, Connection $connection, Appointment $appointment, SyncReport $report): void
    {
        if (null === $appointment->image) {
            $event->addImage = false;
            $event->singleSRC = null;

            return;
        }

        $uuid = $this->imageImporter->import($connection, $appointment->image);

        if (null === $uuid) {
            $event->addImage = false;

            // Den Hash verwerfen, damit der nächste Lauf es erneut versucht.
            // Sonst gälte der Termin als aktuell und das Bild fehlte dauerhaft.
            $event->ctHash = '';

            $report->error(\sprintf(
                'Flyer für "%s" konnte nicht geladen werden: %s',
                $appointment->caption,
                $this->imageImporter->lastError() ?? 'unbekannte Ursache',
            ));

            return;
        }

        ++$report->imagesImported;

        $event->addImage = true;
        $event->singleSRC = $uuid;
        $event->overwriteMeta = true;
        $event->alt = $appointment->caption;
        $event->imageTitle = $appointment->caption;
    }

    /**
     * Beschreibung und Zusatzinformation werden zum Teaser. Das interne Notizfeld
     * kommt hier bewusst nicht vor.
     */
    private function buildTeaser(Appointment $appointment): string
    {
        $parts = array_filter([$appointment->subtitle, $appointment->description, $appointment->information]);

        if ([] === $parts) {
            return '';
        }

        return implode('', array_map(
            static fn (string $part): string => '<p>'.nl2br(StringUtil::specialchars($part)).'</p>',
            $parts,
        ));
    }

    private function generateAlias(Appointment $appointment, int $calendarId): string
    {
        $base = $appointment->caption.' '.$appointment->start->setTimezone($this->timezone())->format('Y-m-d');

        return $this->slug->generate(
            $base,
            $calendarId,
            static fn (string $alias): bool => null !== CalendarEventsModel::findByAlias($alias),
        );
    }

    private function timezone(): \DateTimeZone
    {
        return new \DateTimeZone(date_default_timezone_get());
    }
}
