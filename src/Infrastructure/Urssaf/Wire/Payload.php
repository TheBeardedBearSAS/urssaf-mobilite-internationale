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

/**
 * Defensive view of a decoded JSON object or array. Accessors never trust the shape: a missing or mistyped value reads
 * as null (or is refused by the `require*` / `entries` accessors), and a failure message names the field, never its value.
 */
final readonly class Payload
{
    /**
     * @param array<array-key, array<array-key, mixed>|scalar|null> $data
     */
    private function __construct(private array $data) {}

    /**
     * @throws UnreadablePayload when the decoded JSON is neither an object nor an array
     */
    public static function fromDecoded(mixed $decoded): self
    {
        if (!\is_array($decoded)) {
            throw UnreadablePayload::because('body is neither a JSON object nor a JSON array');
        }

        return self::wrap($decoded);
    }

    /**
     * @param array<array-key, mixed> $data decoded JSON object or array
     */
    private static function wrap(array $data): self
    {
        /** @var array<array-key, array<array-key, mixed>|scalar|null> $typed */
        $typed = $data;

        return new self($typed);
    }

    /**
     * True for a JSON array (and for `{}` / `[]`, which PHP cannot tell apart).
     */
    public function isList(): bool
    {
        return array_is_list($this->data);
    }

    /**
     * String value of a key; null when absent, null or of another type.
     */
    public function string(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return \is_string($value) ? $value : null;
    }

    /**
     * @throws UnreadablePayload when the key is absent or its value is not a non-empty string
     */
    public function requireString(string $key): string
    {
        $value = $this->string($key) ?? '';
        if ($value === '') {
            throw UnreadablePayload::because(\sprintf('field "%s" is missing or empty', $key));
        }

        return $value;
    }

    /**
     * Elements of a top-level JSON array, each of which must be an object.
     *
     * @return list<self>
     *
     * @throws UnreadablePayload when this payload is not a list of objects
     */
    public function entries(): array
    {
        if (!array_is_list($this->data)) {
            throw UnreadablePayload::because('expected a JSON array');
        }

        $entries = [];
        foreach ($this->data as $entry) {
            if (!\is_array($entry) || $entry !== [] && array_is_list($entry)) {
                throw UnreadablePayload::because('expected a JSON array of objects');
            }
            $entries[] = self::wrap($entry);
        }

        return $entries;
    }
}
