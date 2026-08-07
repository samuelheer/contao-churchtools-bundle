<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\Controller;

use SamuelHeer\ContaoChurchToolsBundle\ChurchTools\ChurchToolsClient;
use SamuelHeer\ContaoChurchToolsBundle\Connection\ConnectionRegistry;
use SamuelHeer\ContaoChurchToolsBundle\Exception\ChurchToolsException;
use SamuelHeer\ContaoChurchToolsBundle\Person\PersonProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Personensuche für das Backend-Auswahlfeld.
 *
 * Sucht über die zentrale ChurchTools-Suche und markiert je Treffer, ob die
 * Person für die Website freigegeben ist. Damit sieht der Redakteur sofort,
 * warum jemand später nicht erscheinen würde - statt sich zu wundern.
 */
class PersonSearchController
{
    public function __construct(
        private readonly ConnectionRegistry $registry,
        private readonly ChurchToolsClient $client,
        private readonly PersonProvider $personProvider,
        private readonly Security $security,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        // Die Route liegt unter /contao, greift also hinter der Backend-Firewall.
        // Die zusätzliche Prüfung stellt sicher, dass hier niemand ohne Anmeldung
        // Personendaten abfragen kann.
        if (!$this->security->isGranted('ROLE_USER')) {
            throw new AccessDeniedHttpException();
        }

        $connection = $this->registry->forContentElement((int) $request->query->get('id'))
            ?? $this->onlyConnection();

        if (null === $connection) {
            return new JsonResponse([
                'error' => 'Für diese Seite ist keine ChurchTools-Verbindung hinterlegt. Bitte an der Startseite eintragen.',
                'results' => [],
            ]);
        }

        try {
            $hits = $this->client->searchPersons($connection, (string) $request->query->get('q', ''));
        } catch (ChurchToolsException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'results' => []]);
        }

        $released = $this->personProvider->releasedIds($connection);
        $results = [];

        foreach ($hits as $hit) {
            $results[] = [
                'id' => $hit['id'],
                'name' => $hit['name'],
                'released' => \in_array($hit['id'], $released, true),
            ];
        }

        return new JsonResponse([
            'connection' => $connection->title,
            'results' => $results,
        ]);
    }

    private function onlyConnection(): object|null
    {
        $all = $this->registry->all();

        return 1 === \count($all) ? reset($all) : null;
    }
}
