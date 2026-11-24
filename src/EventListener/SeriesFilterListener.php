<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Module;

/**
 * Zeigt von einer ChurchTools-Serie nur den frühesten Termin im Zeitraum.
 *
 * ChurchTools liefert Serien aufgelöst, der Abgleich legt daher je Instanz einen
 * eigenen Contao-Termin an - alle mit derselben ctAppointmentId. Ein wöchentlicher
 * Kurs füllte eine Kartenreihe sonst mit lauter gleichen Karten.
 *
 * Mehrtägige Termine stehen an jedem ihrer Tage im Array; sie bleiben vollständig
 * erhalten, weil nach der Contao-ID des Termins und nicht nach dem Tag entschieden wird.
 */
#[AsHook('getAllEvents')]
class SeriesFilterListener
{
    /**
     * @param array<int|string, array<int|string, list<array<string, mixed>>>> $events
     *
     * @return array<int|string, array<int|string, list<array<string, mixed>>>>
     */
    public function __invoke(array $events, array $calendars, int $start, int $end, Module $module): array
    {
        if (!$module->churchtoolsNextOnly) {
            return $events;
        }

        // Contao sortiert nur innerhalb eines Tages - die Tage selbst noch nicht.
        ksort($events);

        $now = time();

        /** @var array<int, int> $keep ctAppointmentId => Contao-ID des behaltenen Termins */
        $keep = [];

        foreach ($events as $day => $times) {
            foreach ($times as $time => $list) {
                foreach ($list as $i => $event) {
                    $seriesId = (int) ($event['ctAppointmentId'] ?? 0);

                    // Von Hand angelegte Termine sind keine Serie im Sinne von ChurchTools.
                    if ($seriesId < 1) {
                        continue;
                    }

                    // Bereits vergangene Instanzen zählen nicht als „nächster Termin".
                    // Contao filtert sie erst nach diesem Hook heraus.
                    // Ganztägige Termine enden laut endTime um 0 Uhr ihres letzten Tages.
                    $endsAt = (int) ($event['endTime'] ?? $event['startTime'] ?? 0);

                    if (!($event['addTime'] ?? false)) {
                        $endsAt += 86399;
                    }

                    if ($endsAt < $now && !isset($keep[$seriesId])) {
                        continue;
                    }

                    $keep[$seriesId] ??= (int) $event['id'];

                    if ($keep[$seriesId] !== (int) $event['id']) {
                        unset($events[$day][$time][$i]);
                    }
                }

                if ([] === $events[$day][$time]) {
                    unset($events[$day][$time]);
                } else {
                    $events[$day][$time] = array_values($events[$day][$time]);
                }
            }

            if ([] === $events[$day]) {
                unset($events[$day]);
            }
        }

        return $events;
    }
}
