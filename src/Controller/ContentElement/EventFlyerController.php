<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\Controller\ContentElement;

use Contao\CalendarEventsModel;
use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Events;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Flyerreihe der nächsten Termine.
 *
 * Ersetzt die bisherige Handarbeit, bei der jeder Event-Flyer als Bildelement
 * angelegt und mit Anzeigezeitraum versehen werden musste: Hier verschwindet ein
 * Flyer von selbst, sobald sein Termin vorbei ist.
 */
#[AsContentElement('churchtools_event_flyer', category: 'churchtools')]
class EventFlyerController extends AbstractContentElementController
{
    public function __construct(private readonly Studio $studio)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $calendarIds = array_map('intval', StringUtil::deserialize($model->churchtoolsCalendars, true));
        $calendarIds = array_values(array_filter($calendarIds));

        if ([] === $calendarIds) {
            $template->set('flyers', []);

            return $template->getResponse();
        }

        $limit = max(1, (int) ($model->numberOfItems ?: 4));
        $flyers = [];

        foreach ($this->findUpcoming($calendarIds, $limit) as $event) {
            $figure = $this->studio
                ->createFigureBuilder()
                ->fromUuid(StringUtil::binToUuid($event->singleSRC))
                ->setSize($model->size)
                ->enableLightbox((bool) $model->fullsize)
                ->buildIfResourceExists()
            ;

            if (null === $figure) {
                continue;
            }

            $flyers[] = [
                'figure' => $figure,
                'title' => $event->title,
                'href' => $event->ctLink ?: Events::generateEventUrl($event),
                'startDate' => (int) $event->startDate,
                'external' => (bool) $event->ctLink,
            ];
        }

        $template->set('flyers', $flyers);

        return $template->getResponse();
    }

    /**
     * Kommende Termine mit Bild. Ein Termin gilt bis zu seinem Ende als aktuell,
     * damit ein Flyer nicht am Morgen des Veranstaltungstags verschwindet.
     *
     * @param list<int> $calendarIds
     *
     * @return list<CalendarEventsModel>
     */
    private function findUpcoming(array $calendarIds, int $limit): array
    {
        $now = time();

        $events = CalendarEventsModel::findBy(
            [
                'pid IN ('.implode(',', array_fill(0, \count($calendarIds), '?')).')',
                'published = 1',
                'addImage = 1',
                "singleSRC != ''",
                '(endTime >= ? OR startTime >= ?)',
            ],
            [...$calendarIds, $now, $now],
            [
                'order' => 'startTime ASC',
                'limit' => $limit,
            ],
        );

        return null !== $events ? array_values($events->getModels()) : [];
    }
}
