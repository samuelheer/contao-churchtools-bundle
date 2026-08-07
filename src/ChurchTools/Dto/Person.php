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
        public readonly bool $allowsImageUse,
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
            allowsImageUse: self::truthy($data['privacy_policy_use_image'] ?? null),
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
     * Gibt nur die Felder heraus, die für diese Verbindung freigegeben sind.
     * Das Bild zusätzlich nur, wenn ChurchTools die Bildnutzung erlaubt.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(Connection $connection): array
    {
        // Die Schlüssel sind immer dieselben, nicht freigegebene Felder sind null.
        // Sonst müsste jedes Template prüfen, ob ein Feld überhaupt existiert -
        // und bräche, sobald jemand die Freigabe umstellt.
        return [
            'id' => $this->id,
            'fullName' => $this->fullName(),
            'firstName' => $connection->allowsPersonField('firstName') ? $this->displayFirstName() : null,
            'lastName' => $connection->allowsPersonField('lastName') ? $this->lastName : null,
            'job' => $connection->allowsPersonField('job') ? $this->job : null,
            'email' => $connection->allowsPersonField('email') ? $this->email : null,
            'mobile' => $connection->allowsPersonField('mobile') ? $this->mobile : null,
            'hasImage' => $connection->allowsPersonField('image') && $this->allowsImageUse && null !== $this->imageUrl,
        ];
    }

    private static function text(mixed $value): string|null
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' !== $value ? $value : null;
    }

    /**
     * ChurchTools liefert das Bildrecht je nach Version als 1/0, "1"/"" oder bool.
     * Im Zweifel gilt: kein ausdrückliches Ja bedeutet nein.
     */
    private static function truthy(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_int($value)) {
            return $value > 0;
        }

        if (\is_string($value)) {
            return \in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'ja'], true);
        }

        return false;
    }
}
