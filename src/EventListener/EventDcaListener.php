<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\EventListener;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Message;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Schützt importierte Termine vor Handarbeit.
 *
 * Ohne diesen Schutz überschreibt der nächste Lauf jede Änderung stillschweigend -
 * der Redakteur sieht seine Arbeit verschwinden und findet keinen Grund dafür.
 */
class EventDcaListener
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload')]
    public function lockImportedEvents(DataContainer|null $dc = null): void
    {
        if (null === $dc || !$dc->id || 'edit' !== ($_GET['act'] ?? '')) {
            return;
        }

        $this->framework->initialize();

        $event = CalendarEventsModel::findByPk($dc->id);

        if (null === $event || !$event->ctInstanceKey) {
            return;
        }

        foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields']) as $field) {
            $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$field]['eval']['readonly'] = true;
            $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$field]['eval']['disabled'] = true;
        }

        Message::addInfo($this->translator->trans('tl_calendar_events.churchtoolsLocked', [], 'contao_tl_calendar_events'));
    }
}
