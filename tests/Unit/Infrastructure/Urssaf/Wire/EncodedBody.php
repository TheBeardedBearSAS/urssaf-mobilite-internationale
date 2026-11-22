<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Assert;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonObject;

/**
 * Typed access to the nested arrays of an encoded posting request.
 */
final class EncodedBody
{
    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public static function demande(array $body): array
    {
        /** @var JsonObject $demande */
        $demande = self::section($body, 'declaration')['demande'];
        Assert::assertInstanceOf(JsonObject::class, $demande);

        return $demande->members();
    }

    /**
     * @param array<string, mixed> $parent
     *
     * @return array<string, mixed>
     */
    public static function section(array $parent, string $key): array
    {
        /** @var array<string, mixed> $section */
        $section = $parent[$key];
        Assert::assertIsArray($section);

        return $section;
    }
}
