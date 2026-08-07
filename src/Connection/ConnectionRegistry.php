<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\Connection;

use Contao\ArticleModel;
use Contao\ContentModel;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection as DbalConnection;
use SamuelHeer\ContaoChurchToolsBundle\Exception\ConnectionNotConfiguredException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Lädt die konfigurierten Verbindungen und löst ihre Tokens aus der Umgebung auf.
 */
class ConnectionRegistry
{
    /** @var array<int, Connection>|null */
    private array|null $connections = null;

    public function __construct(
        private readonly DbalConnection $db,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return array<int, Connection>
     */
    public function all(): array
    {
        if (null !== $this->connections) {
            return $this->connections;
        }

        $this->connections = [];

        // Die Tabelle existiert erst nach contao:migrate. Vorher darf nichts krachen.
        if (!$this->db->createSchemaManager()->tablesExist(['tl_churchtools_connection'])) {
            return $this->connections;
        }

        $rows = $this->db->fetchAllAssociative(
            'SELECT * FROM tl_churchtools_connection WHERE disable = 0 ORDER BY title',
        );

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $this->connections[$id] = $this->hydrate($row);
        }

        return $this->connections;
    }

    public function get(int $id): Connection
    {
        $connection = $this->all()[$id] ?? null;

        if (null === $connection) {
            throw new ConnectionNotConfiguredException(\sprintf('Es gibt keine aktive ChurchTools-Verbindung mit der ID %d.', $id));
        }

        return $connection;
    }

    public function find(int|null $id): Connection|null
    {
        if (null === $id || $id < 1) {
            return null;
        }

        return $this->all()[$id] ?? null;
    }

    /**
     * Die Verbindung der aktuellen Root-Seite. Standardkontext für alles, was im
     * Frontend ohne eigene Zuordnung gerendert wird (Insert-Tags, Inhaltselemente).
     */
    public function forCurrentRootPage(): Connection|null
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return null;
        }

        $pageModel = $request->attributes->get('pageModel');

        if (!$pageModel instanceof PageModel) {
            return null;
        }

        return $this->forPage($pageModel);
    }

    /**
     * Die Verbindung, die für eine bestimmte Seite gilt - über deren Startseite.
     */
    public function forPage(PageModel|null $page): Connection|null
    {
        if (null === $page) {
            return null;
        }

        $page->loadDetails();

        // rootId zeigt auf die Startseite des Baums; dort hängt die Verbindung.
        $root = PageModel::findByPk($page->rootId) ?? $page;

        return $this->find((int) ($root->churchtoolsConnection ?? 0));
    }

    /**
     * Die Verbindung eines Inhaltselements - im Backend gibt es keine Seite im
     * Request, deshalb über Artikel und Seite des Elements aufgelöst.
     */
    public function forContentElement(int $contentId): Connection|null
    {
        if ($contentId < 1) {
            return null;
        }

        $content = ContentModel::findByPk($contentId);

        if (null === $content || 'tl_article' !== ($content->ptable ?: 'tl_article')) {
            return null;
        }

        $article = ArticleModel::findByPk($content->pid);

        if (null === $article) {
            return null;
        }

        return $this->forPage(PageModel::findByPk($article->pid));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Connection
    {
        $tokenEnvVar = (string) ($row['tokenEnvVar'] ?? '');

        // Contao speichert Mehrfach-Checkboxen serialisiert.
        $personFields = StringUtil::deserialize($row['personFields'] ?? null, true);
        $personFields = array_values(array_filter(array_map('strval', $personFields)));

        return new Connection(
            id: (int) $row['id'],
            title: (string) $row['title'],
            baseUrl: rtrim((string) $row['baseUrl'], '/'),
            tokenEnvVar: $tokenEnvVar,
            token: $this->resolveToken($tokenEnvVar),
            filesPath: trim((string) ($row['filesPath'] ?? ''), '/'),
            publicPersonGroupId: (int) ($row['publicPersonGroupId'] ?? 0) ?: null,
            personFields: $personFields ?: ['image', 'firstName', 'lastName', 'jobTitle'],
        );
    }

    /**
     * Das Token steht nie in der Datenbank, nur der Name seiner Umgebungsvariablen.
     */
    private function resolveToken(string $envVar): string|null
    {
        if ('' === $envVar) {
            return null;
        }

        $value = $_ENV[$envVar] ?? $_SERVER[$envVar] ?? getenv($envVar);

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
