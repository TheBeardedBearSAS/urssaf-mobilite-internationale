<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf;

use Countable;

/**
 * Immutable ordered list of {@see ApiError} parsed from an error body: one item for a single object, one per item for
 * a list, none for an empty or unreadable body (ADR 0002).
 */
final readonly class ApiErrors implements Countable
{
    /**
     * @param list<ApiError> $errors
     */
    private function __construct(private array $errors) {}

    public static function none(): self
    {
        return new self([]);
    }

    public static function of(ApiError ...$errors): self
    {
        return new self(array_values($errors));
    }

    public function isEmpty(): bool
    {
        return $this->errors === [];
    }

    public function hasCode(string $code): bool
    {
        foreach ($this->errors as $error) {
            if ($error->hasCode($code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The non-null codes, in order.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        $codes = [];
        foreach ($this->errors as $error) {
            $code = $error->code();
            if ($code !== null) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * @return list<ApiError>
     */
    public function all(): array
    {
        return $this->errors;
    }

    public function count(): int
    {
        return \count($this->errors);
    }
}
