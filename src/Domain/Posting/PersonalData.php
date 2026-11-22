<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\PersonalDataNotSerializable;

/**
 * Guards an object that carries personal data (ADR 0006): every property is masked in debug output (var_dump,
 * print_r, dumpers) and the object refuses to be serialized. There is deliberately no __toString().
 *
 * @internal
 */
trait PersonalData
{
    /**
     * @return array<string>
     */
    public function __debugInfo(): array
    {
        return array_map(static fn(): string => '***', get_object_vars($this));
    }

    /**
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
