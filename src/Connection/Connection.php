<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\Connection;

/**
 * Eine konfigurierte ChurchTools-Instanz.
 *
 * Mehrere Verbindungen können parallel bestehen, damit mehrere Gemeinde-Websites
 * in einer Contao-Installation auf unterschiedliche ChurchTools-Instanzen zugreifen.
 * Das Token wird zur Laufzeit aus der Umgebung gelesen und niemals gespeichert.
 */
final class Connection
{
    /**
     * @param list<string> $personFields Personenfelder, die nach außen gegeben werden dürfen
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $baseUrl,
        public readonly string $tokenEnvVar,
        public readonly string|null $token,
        public readonly string $filesPath,
        public readonly int|null $publicPersonGroupId,
        public readonly array $personFields = ['image', 'firstName', 'lastName', 'jobTitle'],
    ) {
    }

    public function hasToken(): bool
    {
        return null !== $this->token && '' !== $this->token;
    }

    /**
     * Personen werden nur ausgeliefert, wenn eine Freigabegruppe hinterlegt ist.
     * Ohne Gruppe gibt es keine stillschweigende Vollfreigabe.
     */
    public function hasPersonRelease(): bool
    {
        return null !== $this->publicPersonGroupId && $this->publicPersonGroupId > 0;
    }

    public function allowsPersonField(string $field): bool
    {
        return \in_array($field, $this->personFields, true);
    }

    /**
     * Präfix für alle Cache-Schlüssel und -Tags dieser Verbindung. Ohne diesen
     * Präfix könnten sich Mandanten gegenseitig sehen.
     */
    public function cachePrefix(): string
    {
        return 'ct.'.$this->id;
    }
}
