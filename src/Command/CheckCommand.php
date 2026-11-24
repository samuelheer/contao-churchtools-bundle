<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\Command;

use Contao\CalendarModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection as Database;
use SamuelHeer\ContaoChurchToolsBundle\ChurchTools\ChurchToolsClient;
use SamuelHeer\ContaoChurchToolsBundle\ChurchTools\Dto\Person;
use SamuelHeer\ContaoChurchToolsBundle\Connection\Connection;
use SamuelHeer\ContaoChurchToolsBundle\Connection\ConnectionRegistry;
use SamuelHeer\ContaoChurchToolsBundle\Exception\ChurchToolsException;
use SamuelHeer\ContaoChurchToolsBundle\Person\PersonProvider;
use SamuelHeer\ContaoChurchToolsBundle\Sync\AppointmentSync;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Gesamtcheck einer Installation: Was ist eingerichtet, was greift nicht?
 *
 * Gedacht für „auf der Website fehlt etwas" ebenso wie für den Blick nach einem
 * Update. Der Befehl schreibt nichts; der Termin-Abgleich läuft als Probelauf.
 * Personen erscheinen nur mit ID, nie mit Namen - die Ausgabe landet gern in
 * Tickets und Chats.
 */
#[AsCommand(
    name: 'churchtools:check',
    description: 'Prüft Verbindungen, Personenfreigaben und Kalender und zeigt, was nicht greift.',
)]
class CheckCommand extends Command
{
    private int $errors = 0;

    private int $warnings = 0;

    private SymfonyStyle $io;

    public function __construct(
        private readonly ConnectionRegistry $registry,
        private readonly ChurchToolsClient $client,
        private readonly PersonProvider $personProvider,
        private readonly AppointmentSync $sync,
        private readonly ContaoFramework $framework,
        private readonly Database $db,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'Nur diese Verbindung prüfen (ID)')
            ->addOption('skip-sync', null, InputOption::VALUE_NONE, 'Ohne Probelauf des Termin-Abgleichs (schneller)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->framework->initialize();

        $connections = $this->registry->all();
        $only = (int) ($input->getOption('connection') ?? 0);

        if ($only > 0) {
            $connections = array_filter($connections, static fn (Connection $c): bool => $c->id === $only);
        }

        if ([] === $connections) {
            $this->io->error('Keine ChurchTools-Verbindung gefunden.');

            return Command::FAILURE;
        }

        foreach ($connections as $connection) {
            $this->io->title('Verbindung „'.$connection->title.'" (ID '.$connection->id.')');

            if (!$this->checkConnection($connection)) {
                continue;
            }

            $this->checkPersons($connection);
            $this->checkCalendars($connection, !$input->getOption('skip-sync'));
        }

        $this->io->newLine();

        if ($this->errors > 0) {
            $this->io->error(\sprintf('%d Fehler, %d Hinweise.', $this->errors, $this->warnings));

            return Command::FAILURE;
        }

        if ($this->warnings > 0) {
            $this->io->warning(\sprintf('Keine Fehler, %d Hinweise.', $this->warnings));

            return Command::SUCCESS;
        }

        $this->io->success('Alles in Ordnung.');

        return Command::SUCCESS;
    }

    private function checkConnection(Connection $connection): bool
    {
        $this->io->section('Zugang');
        $this->line('Adresse', $connection->baseUrl);

        if (!$connection->hasToken()) {
            $this->error('Token fehlt: Umgebungsvariable '.$connection->tokenEnvVar.' ist nicht gesetzt.');

            return false;
        }

        try {
            $account = $this->client->whoami($connection);
        } catch (ChurchToolsException $e) {
            $this->error('ChurchTools nicht erreichbar: '.$e->getMessage());

            return false;
        }

        if (null === $account) {
            $this->error('Anmeldung fehlgeschlagen - Token ungültig oder abgelaufen?');

            return false;
        }

        $this->ok('Anmeldung als Dienstkonto funktioniert');

        return true;
    }

    private function checkPersons(Connection $connection): void
    {
        $this->io->section('Personen');

        if (!$connection->hasPersonRelease()) {
            $this->warn('Keine Freigabegruppe gesetzt - es erscheint keine Person auf der Website.');

            return;
        }

        $released = $this->checkGroup($connection, 'Freigabegruppe', (int) $connection->publicPersonGroupId, $this->personProvider->releasedIds($connection));

        if ($connection->hasImageRelease()) {
            $this->checkGroup($connection, 'Bildfreigabegruppe', (int) $connection->imageReleaseGroupId, $this->personProvider->imageReleasedIds($connection));
        } else {
            $this->line('Bildfreigabegruppe', 'keine - Profilfoto erscheint mit der Personenfreigabe');
        }

        $this->line('Freigegebene Felder', implode(', ', $connection->personFields) ?: '(keine)');
        $this->checkPersonUsage($connection, $released);
    }

    /**
     * @param list<int> $memberIds
     *
     * @return list<int> die tatsächlich auslieferbaren IDs
     */
    private function checkGroup(Connection $connection, string $label, int $groupId, array $memberIds): array
    {
        try {
            $group = $this->client->getGroup($connection, $groupId);
        } catch (ChurchToolsException) {
            $group = null;
        }

        if (null === $group) {
            $this->error(\sprintf('%s %d existiert nicht oder ist für das Dienstkonto nicht sichtbar.', $label, $groupId));

            return [];
        }

        $inactive = [];

        foreach ($memberIds as $personId) {
            try {
                $data = $this->client->getPerson($connection, $personId);
            } catch (ChurchToolsException) {
                continue;
            }

            $person = null !== $data ? Person::fromArray($data) : null;

            if (null === $person || $person->isInactive) {
                $inactive[] = $personId;
            }
        }

        $this->line($label, \sprintf('%s (%d) - %d aktive Mitglieder', $group['name'] ?? '?', $groupId, \count($memberIds)));

        if ([] !== $inactive) {
            $this->warn(\sprintf(
                '%d davon archiviert oder verstorben (werden nicht ausgeliefert, sollten aus der Gruppe): ID %s',
                \count($inactive),
                implode(', ', $inactive),
            ));
        }

        return array_values(array_diff($memberIds, $inactive));
    }

    /**
     * Personen-Elemente und Insert-Tags, die ins Leere zeigen.
     *
     * @param list<int> $released
     */
    private function checkPersonUsage(Connection $connection, array $released): void
    {
        $found = 0;
        $problems = [];

        $elements = $this->db->fetchAllAssociative(
            "SELECT id, churchtoolsPersonId, churchtoolsGroupId FROM tl_content WHERE type = 'churchtools_person' AND invisible = 0",
        );

        foreach ($elements as $row) {
            if ($this->registry->forContentElement((int) $row['id'])?->id !== $connection->id) {
                continue;
            }

            ++$found;
            $personId = (int) $row['churchtoolsPersonId'];

            if ($personId > 0 && !\in_array($personId, $released, true)) {
                $problems[] = \sprintf('Element %d: feste Person %d wird nicht ausgeliefert', $row['id'], $personId);
            }

            if (0 === $personId && 0 === (int) $row['churchtoolsGroupId']) {
                $problems[] = \sprintf('Element %d: weder Person noch Gruppe gewählt', $row['id']);
            }
        }

        $tagged = $this->db->fetchAllAssociative(
            "SELECT id, text, html FROM tl_content WHERE invisible = 0 AND (text LIKE '%{{ct_person::%' OR html LIKE '%{{ct_person::%')",
        );

        foreach ($tagged as $row) {
            if ($this->registry->forContentElement((int) $row['id'])?->id !== $connection->id) {
                continue;
            }

            preg_match_all('/\{\{ct_person::(\d+)/', ($row['text'] ?? '').' '.($row['html'] ?? ''), $matches);

            foreach (array_unique(array_map('intval', $matches[1])) as $personId) {
                ++$found;

                if (!\in_array($personId, $released, true)) {
                    $problems[] = \sprintf('Element %d: Insert-Tag für Person %d bleibt leer', $row['id'], $personId);
                }
            }
        }

        $this->line('Verwendung', \sprintf('%d Personen-Elemente und Insert-Tags', $found));

        foreach ($problems as $problem) {
            $this->warn($problem);
        }
    }

    private function checkCalendars(Connection $connection, bool $dryRun): void
    {
        $this->io->section('Kalender');

        $calendars = CalendarModel::findBy('churchtoolsConnection', $connection->id);

        if (null === $calendars) {
            $this->line('Kalender', 'keiner mit dieser Verbindung');

            return;
        }

        try {
            $ctCalendars = array_column($this->client->getCalendars($connection), 'name', 'id');
            $ctTags = array_column($this->client->getAppointmentTags($connection), 'name', 'id');
        } catch (ChurchToolsException $e) {
            $this->error('Kalender oder Tags nicht abrufbar: '.$e->getMessage());

            return;
        }

        foreach ($calendars as $calendar) {
            $this->io->writeln(\sprintf('<options=bold>%s</> (Contao-Kalender %d)', $calendar->title, $calendar->id));

            $ids = array_map('intval', StringUtil::deserialize($calendar->churchtoolsCalendarIds, true));
            $tags = array_map('intval', StringUtil::deserialize($calendar->churchtoolsTagIds, true));

            if ([] === $ids) {
                $this->error('Keine ChurchTools-Kalender ausgewählt.');
            } else {
                $this->line('ChurchTools-Kalender', implode(', ', array_map(static fn (int $id): string => ($ctCalendars[$id] ?? '?? fehlt').' ('.$id.')', $ids)));

                foreach ($ids as $id) {
                    if (!isset($ctCalendars[$id])) {
                        $this->error(\sprintf('ChurchTools-Kalender %d existiert nicht mehr oder ist nicht sichtbar.', $id));
                    }
                }
            }

            if ([] === $tags) {
                $this->line('Tag-Filter', 'keiner - alle öffentlichen Termine');
            } else {
                $this->line('Tag-Filter', implode(', ', array_map(static fn (int $id): string => ($ctTags[$id] ?? '?? gelöscht').' ('.$id.')', $tags)));

                if ([] === array_intersect($tags, array_keys($ctTags))) {
                    $this->error('Keiner der gewählten Tags existiert noch - der Abgleich würde alle Termine abschalten.');
                }
            }

            $this->checkReader($calendar);
            $this->checkLastSync($calendar);
            $this->checkStock($calendar);

            if ($dryRun) {
                try {
                    $report = $this->sync->syncCalendar($calendar, true);
                    $this->line('Probelauf', $report->summary());

                    foreach ($report->errors as $error) {
                        $this->error($error);
                    }
                } catch (ChurchToolsException $e) {
                    $this->error('Probelauf fehlgeschlagen: '.$e->getMessage());
                }
            }

            $this->io->newLine();
        }
    }

    private function checkReader(CalendarModel $calendar): void
    {
        $page = $calendar->jumpTo ? PageModel::findWithDetails((int) $calendar->jumpTo) : null;

        if (null === $page) {
            $this->warn('Keine Weiterleitungsseite (Detailseite) - Termine ohne Link führen ins Leere, eigene Links öffnen in neuem Tab.');

            return;
        }

        $this->line('Detailseite', \sprintf('%s (%d), Domain %s', $page->title, $page->id, $page->domain ?: '(keine)'));
    }

    private function checkLastSync(CalendarModel $calendar): void
    {
        $interval = (string) ($calendar->churchtoolsSyncInterval ?: 'hourly');
        $last = (int) $calendar->churchtoolsLastSync;

        if ($last < 1) {
            $this->warn('Noch nie erfolgreich abgeglichen.');

            return;
        }

        $age = time() - $last;
        $this->line('Letzter Abgleich', \sprintf('%s (vor %s), Intervall %s', date('d.m.Y H:i', $last), $this->duration($age), $interval));

        $limit = match ($interval) {
            'hourly' => 3 * 3600,
            'daily' => 2 * 86400,
            default => null,
        };

        if (null !== $limit && $age > $limit) {
            $this->warn('Der Abgleich hängt hinterher - läuft der Contao-Cron?');
        }
    }

    private function checkStock(CalendarModel $calendar): void
    {
        $row = $this->db->fetchAssociative(
            "SELECT COUNT(*) AS total,
                    SUM(addImage = 1) AS images,
                    SUM(source = 'external') AS links,
                    COUNT(DISTINCT ctAppointmentId) AS series
             FROM tl_calendar_events
             WHERE pid = ? AND published = 1 AND ctInstanceKey != '' AND endTime >= ?",
            [$calendar->id, time()],
        ) ?: [];

        $this->line('Kommende Termine', \sprintf(
            '%d (aus %d ChurchTools-Terminen), davon %d mit Bild, %d mit eigenem Link',
            $row['total'] ?? 0,
            $row['series'] ?? 0,
            $row['images'] ?? 0,
            $row['links'] ?? 0,
        ));
    }

    private function duration(int $seconds): string
    {
        return match (true) {
            $seconds < 120 => $seconds.' s',
            $seconds < 7200 => intdiv($seconds, 60).' min',
            $seconds < 172800 => intdiv($seconds, 3600).' h',
            default => intdiv($seconds, 86400).' Tagen',
        };
    }

    private function line(string $label, string $value): void
    {
        $this->io->writeln(\sprintf('  %-22s %s', $label.':', $value));
    }

    private function ok(string $message): void
    {
        $this->io->writeln('  <info>✓</info> '.$message);
    }

    private function warn(string $message): void
    {
        ++$this->warnings;
        $this->io->writeln('  <comment>! '.$message.'</comment>');
    }

    private function error(string $message): void
    {
        ++$this->errors;
        $this->io->writeln('  <error>✗ '.$message.'</error>');
    }
}
