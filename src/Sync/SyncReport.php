<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\Sync;

/**
 * Ergebnis eines Synchronisationslaufs - auch für den Probelauf, der nichts schreibt.
 */
final class SyncReport
{
    public int $created = 0;
    public int $updated = 0;
    public int $unchanged = 0;
    public int $disabled = 0;
    public int $skippedInternal = 0;
    public int $imagesImported = 0;

    /** @var list<string> */
    public array $errors = [];

    /** @var list<string> */
    public array $messages = [];

    public function note(string $message): void
    {
        $this->messages[] = $message;
    }

    public function error(string $message): void
    {
        $this->errors[] = $message;
    }

    public function merge(self $other): void
    {
        $this->created += $other->created;
        $this->updated += $other->updated;
        $this->unchanged += $other->unchanged;
        $this->disabled += $other->disabled;
        $this->skippedInternal += $other->skippedInternal;
        $this->imagesImported += $other->imagesImported;
        $this->errors = [...$this->errors, ...$other->errors];
        $this->messages = [...$this->messages, ...$other->messages];
    }

    public function hasChanges(): bool
    {
        return $this->created > 0 || $this->updated > 0 || $this->disabled > 0;
    }

    public function summary(): string
    {
        return \sprintf(
            'neu %d, geändert %d, unverändert %d, deaktiviert %d, intern übersprungen %d, Bilder %d, Fehler %d',
            $this->created,
            $this->updated,
            $this->unchanged,
            $this->disabled,
            $this->skippedInternal,
            $this->imagesImported,
            \count($this->errors),
        );
    }
}
