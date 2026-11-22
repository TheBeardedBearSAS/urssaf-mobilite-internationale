<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\PersonalDataNotSerializable;

/**
 * Phone number: 7 to 30 characters ("minLength" / "maxLength" of the contract), no pattern (ADR 0008). Kept verbatim.
 */
final readonly class PhoneNumber
{
    public const MIN_LENGTH = 7;
    public const MAX_LENGTH = 30;
    private const SUBJECT = 'phone_number';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "phone_number.invalid_length"
     */
    public static function fromString(string $value): self
    {
        $length = mb_strlen($value);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw InvalidValue::invalidLength(self::SUBJECT, self::MIN_LENGTH, self::MAX_LENGTH);
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Personal data: masked in debug output (var_dump, print_r, dumpers). There is deliberately no __toString(), and the object refuses to be serialized.
     *
     * @return array{value: string}
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }

    /**
     * Personal data must never be serialized (ADR 0006).
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw PersonalDataNotSerializable::create();
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw PersonalDataNotSerializable::create();
    }
}
