<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\Command;

use SamuelHeer\ContaoChurchToolsBundle\Sync\AppointmentSync;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'churchtools:sync',
    description: 'Synchronisiert ChurchTools-Termine in die zugeordneten Contao-Kalender.',
)]
class SyncCommand extends Command
{
    public function __construct(private readonly AppointmentSync $sync)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Zeigt an, was geschehen würde, ohne etwas zu schreiben.',
            )
            ->addOption(
                'from',
                null,
                InputOption::VALUE_REQUIRED,
                'Beginn des Zeitraums (JJJJ-MM-TT). Ohne Angabe: ein Monat zurück.',
            )
            ->addOption(
                'to',
                null,
                InputOption::VALUE_REQUIRED,
                'Ende des Zeitraums (JJJJ-MM-TT). Ohne Angabe: zwölf Monate voraus.',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $from = $this->parseDate($input->getOption('from'));
            $to = $this->parseDate($input->getOption('to'));
        } catch (\Exception) {
            $io->error('Ungültiges Datum. Erwartet wird das Format JJJJ-MM-TT.');

            return Command::INVALID;
        }

        if ($dryRun) {
            $io->note('Probelauf - es wird nichts geschrieben.');
        }

        if (null !== $from || null !== $to) {
            $io->note(\sprintf(
                'Abweichender Zeitraum: %s bis %s. Außerhalb liegende Termine bleiben unangetastet.',
                $from?->format('d.m.Y') ?? 'Standard',
                $to?->format('d.m.Y') ?? 'Standard',
            ));
        }

        $report = $this->sync->syncAll($dryRun, $from, $to);

        foreach ($report->messages as $message) {
            $io->writeln('  '.$message);
        }

        foreach ($report->errors as $error) {
            $io->warning($error);
        }

        $io->success($report->summary());

        // Fehler einzelner Mandanten sollen den Lauf sichtbar machen, ohne
        // die erfolgreich synchronisierten Kalender zu entwerten.
        return [] === $report->errors ? Command::SUCCESS : Command::FAILURE;
    }

    private function parseDate(mixed $value): \DateTimeImmutable|null
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        return new \DateTimeImmutable($value);
    }
}
