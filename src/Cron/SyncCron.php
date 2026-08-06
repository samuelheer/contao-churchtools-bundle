<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Psr\Log\LoggerInterface;
use SamuelHeer\ContaoChurchToolsBundle\Sync\AppointmentSync;

/**
 * Stündlicher Abgleich.
 *
 * Der Lauf geschieht bewusst direkt und nicht über einen Messenger-Worker:
 * Auf geteiltem Hosting läuft oft kein dauerhafter Worker, dann würde nie
 * synchronisiert. Contaos Cron läuft ohnehin außerhalb der Seitenauslieferung.
 */
class SyncCron
{
    public function __construct(
        private readonly AppointmentSync $sync,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    #[AsCronJob('hourly')]
    public function __invoke(): void
    {
        $report = $this->sync->syncAll();

        if ($report->hasChanges() || [] !== $report->errors) {
            $this->logger?->info('ChurchTools-Sync: '.$report->summary());
        }
    }
}
