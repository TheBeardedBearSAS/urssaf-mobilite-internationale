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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\PostingRequestEncoder;

/**
 * The encoded keys of a complete request must be exactly the properties of the published schema
 * `DeclarationDetachementSimplifie`: no invented key, no forgotten one, French spelling included.
 */
final class PostingRequestContractTest extends TestCase
{
    private const CONTRACT = __DIR__ . '/../../../../../docs/openapi/mobi-prd.json';

    public function testTheEncodedKeysAreExactlyThePropertiesOfTheContract(): void
    {
        $schemas = self::schemas();
        /** @var array<string, mixed> $wire */
        $wire = json_decode(
            JsonEncoder::encode((new PostingRequestEncoder())->encode(SyntheticPostings::full())),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $expected = self::schemaPaths($schemas['DeclarationDetachementSimplifie'], $schemas, '');
        $actual = self::valuePaths($wire, '');
        sort($expected);
        sort($actual);

        static::assertContains('declaration.demande.activiteSubstentielle.activiteSubstentielle', $expected);
        static::assertSame($expected, $actual);
    }

    public function testTheEncodedJsonIsAnObject(): void
    {
        $payload = JsonDecoder::decode(JsonEncoder::encode((new PostingRequestEncoder())->encode(SyntheticPostings::minimal())));

        static::assertFalse($payload->isList());
    }

    /**
     * @return array<string, mixed>
     */
    private static function schemas(): array
    {
        /** @var array{components: array{schemas: array<string, mixed>}} $contract */
        $contract = json_decode((string) file_get_contents(self::CONTRACT), true, 512, JSON_THROW_ON_ERROR);

        return $contract['components']['schemas'];
    }

    /**
     * @param array<string, mixed> $schemas
     *
     * @return list<string>
     */
    private static function schemaPaths(mixed $schema, array $schemas, string $prefix): array
    {
        static::assertIsArray($schema);
        if (\array_key_exists('$ref', $schema)) {
            static::assertIsString($schema['$ref']);

            return self::schemaPaths($schemas[substr($schema['$ref'], \strlen('#/components/schemas/'))], $schemas, $prefix);
        }
        if (\array_key_exists('items', $schema)) {
            return self::schemaPaths($schema['items'], $schemas, $prefix);
        }
        if (!\array_key_exists('properties', $schema)) {
            return [$prefix];
        }

        /** @var array<string, mixed> $properties */
        $properties = $schema['properties'];

        return array_merge([], ...array_map(
            static fn(string $name): array => self::schemaPaths($properties[$name], $schemas, self::join($prefix, $name)),
            array_keys($properties),
        ));
    }

    /**
     * Leaf paths of a decoded body; list items share the path of their list, duplicates are folded.
     *
     * @return list<string>
     */
    private static function valuePaths(mixed $value, string $prefix): array
    {
        if (!\is_array($value)) {
            return [$prefix];
        }

        $isList = array_is_list($value);

        return array_values(array_unique(array_merge([], ...array_map(
            static fn(int|string $name): array => self::valuePaths($value[$name], $isList ? $prefix : self::join($prefix, (string) $name)),
            array_keys($value),
        ))));
    }

    private static function join(string $prefix, string $name): string
    {
        return $prefix === '' ? $name : $prefix . '.' . $name;
    }
}
