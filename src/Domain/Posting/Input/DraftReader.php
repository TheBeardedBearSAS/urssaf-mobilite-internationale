<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Validation\Violation;

/**
 * Collects the violations found while reading a draft: an {@see InvalidValue} becomes a
 * violation at the given path and the read value is then null. Only null means "absent": "" is parsed like any value.
 *
 * @internal
 */
final class DraftReader
{
    private ValidationResult $result;

    public function __construct()
    {
        $this->result = ValidationResult::valid();
    }

    /**
     * Missing value: "field.required" violation.
     *
     * @template T
     *
     * @param-immediately-invoked-callable $parse
     *
     * @param callable(string): T $parse
     *
     * @return T|null
     */
    public function required(string $path, ?string $value, callable $parse): mixed
    {
        if ($value === null) {
            $this->missing($path);

            return null;
        }

        return $this->attempt($path, static fn(): mixed => $parse($value));
    }

    /**
     * Missing value: null without violation.
     *
     * @template T
     *
     * @param-immediately-invoked-callable $parse
     *
     * @param callable(string): T $parse
     *
     * @return T|null
     */
    public function optional(string $path, ?string $value, callable $parse): mixed
    {
        return $value === null ? null : $this->attempt($path, static fn(): mixed => $parse($value));
    }

    /**
     * Required boolean: "field.required" violation when missing.
     */
    public function flag(string $path, ?bool $value): ?bool
    {
        if ($value === null) {
            $this->missing($path);
        }

        return $value;
    }

    /**
     * @template T
     *
     * @param-immediately-invoked-callable $build
     *
     * @param callable(): T $build
     *
     * @return T|null
     */
    public function attempt(string $path, callable $build): mixed
    {
        try {
            return $build();
        } catch (InvalidValue $exception) {
            $this->merge(ValidationResult::of($exception->toViolation($path)));

            return null;
        }
    }

    public function missing(string $path): void
    {
        $this->merge(ValidationResult::of(Violation::error($path, 'field.required', 'This field is required.')));
    }

    public function merge(ValidationResult $result): void
    {
        $this->result = $this->result->merge($result);
    }

    public function result(): ValidationResult
    {
        return $this->result;
    }
}
