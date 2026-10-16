<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\ChurchTools\Dto;

use SamuelHeer\ContaoChurchToolsBundle\Connection\Connection;

/**
 * Eine Person aus ChurchTools, reduziert auf die Felder, die überhaupt nach
 * außen gehen können. Alles Weitere wird gar nicht erst übernommen.
 */
final class Person
{
    public function __construct(
        public readonly int $id,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string|null $nickname,
        public readonly string|null $job,
        public readonly string|null $imageUrl,
        public readonly string|null $email,
        public readonly string|null $mobile,
        /** @var list<string> alle Adressen der Person, Standardadresse zuerst */
        public readonly array $emails = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self|null
    {
        if (!isset($data['id'])) {
            return null;
        }

        return new self(
            id: (int) $data['id'],
            firstName: (string) ($data['firstName'] ?? ''),
            lastName: (string) ($data['lastName'] ?? ''),
            nickname: self::text($data['nickname'] ?? null),
            job: self::text($data['job'] ?? null),
            imageUrl: self::text($data['imageUrl'] ?? null),
            email: self::text($data['email'] ?? null),
            mobile: self::text($data['mobile'] ?? null),
            emails: self::emails($data),
        );
    }

    /**
     * Rufname, wenn gepflegt - sonst der Vorname.
     */
    public function displayFirstName(): string
    {
        return $this->nickname ?? $this->firstName;
    }

    public function fullName(): string
    {
        return trim($this->displayFirstName().' '.$this->lastName);
    }

    /**
     * "Samuel Heer" -> "SH" - Ersatz, wenn kein Foto ausgeliefert wird.
     */
    public function initials(): string
    {
        $initials = '';

        foreach ([$this->displayFirstName(), $this->lastName] as $part) {
            if ('' !== $part) {
                $initials .= mb_strtoupper(mb_substr($part, 0, 1));
            }
        }

        return $initials;
    }

    /**
     * Gibt nur die Felder heraus, die für diese Verbindung freigegeben sind.
     * Das Bild zusätzlich nur, wenn die Person in der Bildfreigabegruppe steht -
     * das prüft der Aufrufer über PersonProvider::isImageReleased().
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(Connection $connection, bool $imageReleased): array
    {
        // Die Schlüssel sind immer dieselben, nicht freigegebene Felder sind null.
        // Sonst müsste jedes Template prüfen, ob ein Feld überhaupt existiert -
        // und bräche, sobald jemand die Freigabe umstellt.
        return [
            'id' => $this->id,
            'fullName' => $this->fullName(),
            'initials' => $this->initials(),
            'firstName' => $connection->allowsPersonField('firstName') ? $this->displayFirstName() : null,
            'lastName' => $connection->allowsPersonField('lastName') ? $this->lastName : null,
            'job' => $connection->allowsPersonField('job') ? $this->job : null,
            'email' => $connection->allowsPersonField('email') ? $connection->publicEmail($this->emails) : null,
            'mobile' => $connection->allowsPersonField('mobile') ? $this->mobile : null,
            'hasImage' => $connection->allowsPersonField('image') && $imageReleased && null !== $this->imageUrl,
        ];
    }

    /**
     * Standardadresse plus alle weiteren aus ChurchTools, ohne Dubletten.
     *
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private static function emails(array $data): array
    {
        $emails = [self::text($data['email'] ?? null)];

        foreach (\is_array($data['emails'] ?? null) ? $data['emails'] : [] as $entry) {
            $emails[] = self::text(\is_array($entry) ? ($entry['email'] ?? null) : null);
        }

        return array_values(array_unique(array_filter($emails)));
    }

    private static function text(mixed $value): string|null
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' !== $value ? $value : null;
    }
}
