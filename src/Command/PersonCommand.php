<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\Command;

use SamuelHeer\ContaoChurchToolsBundle\ChurchTools\ChurchToolsClient;
use SamuelHeer\ContaoChurchToolsBundle\Connection\ConnectionRegistry;
use SamuelHeer\ContaoChurchToolsBundle\Person\GroupRoleResolver;
use SamuelHeer\ContaoChurchToolsBundle\Person\PersonProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Beantwortet die Frage „warum erscheint diese Person nicht auf der Website?".
 *
 * Zeigt, ob eine Person freigegeben ist und welche Felder tatsächlich
 * ausgeliefert würden - ohne dass man dafür eine Seite bauen muss.
 */
#[AsCommand(
    name: 'churchtools:person',
    description: 'Prüft, ob eine Person für die Website freigegeben ist und welche Felder ausgeliefert werden.',
)]
class PersonCommand extends Command
{
    public function __construct(
        private readonly ConnectionRegistry $registry,
        private readonly PersonProvider $personProvider,
        private readonly GroupRoleResolver $groupRoleResolver,
        private readonly ChurchToolsClient $client,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('personId', InputArgument::OPTIONAL, 'ID der Person in ChurchTools')
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'ID der Verbindung (ohne Angabe: die einzige vorhandene)')
            ->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Nach einem Namen suchen (wie das Backend-Auswahlfeld)')
            ->addOption('group', 'g', InputOption::VALUE_REQUIRED, 'Stattdessen eine Gruppe auflösen')
            ->addOption('role', 'r', InputOption::VALUE_REQUIRED, 'Auf eine Rolle eingrenzen')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connections = $this->registry->all();

        if ([] === $connections) {
            $io->error('Es ist keine ChurchTools-Verbindung eingerichtet.');

            return Command::FAILURE;
        }

        $connectionId = (int) ($input->getOption('connection') ?? 0);
        $connection = $connectionId > 0 ? $this->registry->find($connectionId) : reset($connections);

        if (null === $connection) {
            $io->error('Verbindung nicht gefunden.');

            return Command::FAILURE;
        }

        $io->section('Verbindung: '.$connection->title);
        $io->writeln('  Token: '.($connection->hasToken() ? 'gefunden' : 'FEHLT ('.$connection->tokenEnvVar.')'));
        $io->writeln('  Freigabegruppe: '.($connection->hasPersonRelease() ? (string) $connection->publicPersonGroupId : 'keine - es wird niemand ausgeliefert'));
        $io->writeln('  Freigegebene Felder: '.implode(', ', $connection->personFields));
        $io->newLine();

        if ($query = (string) ($input->getOption('search') ?? '')) {
            $hits = $this->client->searchPersons($connection, $query);

            if ([] === $hits) {
                $io->warning('Keine Treffer.');

                return Command::SUCCESS;
            }

            $released = $this->personProvider->releasedIds($connection);
            $rows = [];

            foreach ($hits as $hit) {
                $rows[] = [
                    $hit['id'],
                    $hit['name'],
                    \in_array($hit['id'], $released, true) ? 'ja' : 'nein',
                ];
            }

            $io->table(['ID', 'Name', 'freigegeben'], $rows);

            return Command::SUCCESS;
        }

        if ($groupId = (int) ($input->getOption('group') ?? 0)) {
            $roleId = (int) ($input->getOption('role') ?? 0) ?: null;
            $people = $this->groupRoleResolver->resolve($connection, $groupId, $roleId);

            if ([] === $people) {
                $io->warning('Keine ausspielbare Person in dieser Gruppe - nicht freigegeben oder Gruppe/Rolle leer.');

                return Command::SUCCESS;
            }

            $rows = [];

            foreach ($people as $person) {
                $public = $person->toPublicArray($connection);
                $rows[] = [$person->id, $public['fullName'], $public['job'] ?? '-', $public['hasImage'] ? 'ja' : 'nein'];
            }

            $io->table(['ID', 'Name', 'Funktion', 'Bild'], $rows);

            return Command::SUCCESS;
        }

        $personId = (int) $input->getArgument('personId');

        if ($personId < 1) {
            $io->error('Bitte eine Personen-ID angeben oder --group verwenden.');

            return Command::INVALID;
        }

        if (!$this->personProvider->isReleased($connection, $personId)) {
            $io->warning(\sprintf('Person %d ist NICHT freigegeben und würde auf der Website nicht erscheinen.', $personId));

            return Command::SUCCESS;
        }

        $person = $this->personProvider->get($connection, $personId);

        if (null === $person) {
            $io->warning('Person ist freigegeben, konnte aber nicht geladen werden.');

            return Command::SUCCESS;
        }

        $public = $person->toPublicArray($connection);
        $rows = [];

        foreach ($public as $key => $value) {
            $rows[] = [$key, \is_bool($value) ? ($value ? 'ja' : 'nein') : (string) ($value ?? '-')];
        }

        $io->success('Person ist freigegeben. Ausgeliefert würde:');
        $io->table(['Feld', 'Wert'], $rows);

        if (!$person->allowsImageUse) {
            $io->note('Kein Bild: In ChurchTools ist die Bildnutzung für diese Person nicht erlaubt.');
        }

        return Command::SUCCESS;
    }
}
