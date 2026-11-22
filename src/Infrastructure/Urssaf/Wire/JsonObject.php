<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire;

use JsonSerializable;
use stdClass;

/**
 * Members that must be encoded as a JSON object even when empty (`{}`), which a PHP array cannot express. The member
 * names are field names, never consecutive integers.
 */
final readonly class JsonObject implements JsonSerializable
{
    /**
     * @param array<string, mixed> $members
     */
    private function __construct(private array $members) {}

    /**
     * @param array<string, mixed> $members
     */
    public static function of(array $members): self
    {
        return new self($members);
    }

    /**
     * @return array<string, mixed>
     */
    public function members(): array
    {
        return $this->members;
    }

    /**
     * An empty array would encode as `[]`; any other member list has non-numeric names, so it encodes as an object.
     *
     * @return array<string, mixed>|stdClass
     */
    public function jsonSerialize(): array|stdClass
    {
        return $this->members === [] ? new stdClass() : $this->members;
    }
}
