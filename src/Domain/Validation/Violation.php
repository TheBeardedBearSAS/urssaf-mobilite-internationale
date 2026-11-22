<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Validation;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvariantViolation;

/**
 * A single validation finding.
 *
 * - path: dotted path of the offending field, list items indexed with brackets (e.g. "worker.birthDate",
 *   "activities[2].country"); empty string targets the validated subject itself.
 * - code: stable snake_case code "<subject>.<reason>" (e.g. "siren.invalid_length").
 *
 * The message and the parameters never carry the rejected value.
 */
final readonly class Violation
{
    private const PATH_PATTERN = '/^[A-Za-z_]\w*(?:\[\d+\])*(?:\.[A-Za-z_]\w*(?:\[\d+\])*)*$/D';
    private const CODE_PATTERN = '/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/D';

    /**
     * @param array<string, scalar> $params
     */
    private function __construct(
        private string $path,
        private string $code,
        private string $message,
        private array $params,
    ) {
        if ($path !== '' && preg_match(self::PATH_PATTERN, $path) !== 1) {
            throw InvariantViolation::because(
                'violation.invalid_path',
                'A violation path must be a dotted path such as "worker.birthDate" or "activities[2].country".',
            );
        }
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw InvariantViolation::because(
                'violation.invalid_code',
                'A violation code must be a dotted snake_case identifier such as "subject.reason".',
            );
        }
    }

    /**
     * @param array<string, scalar> $params
     */
    public static function error(string $path, string $code, string $message, array $params = []): self
    {
        return new self($path, $code, $message, $params);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, scalar>
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * Nests the violation under a parent path: "country" prefixed by "activities[2]" becomes "activities[2].country".
     */
    public function withPathPrefix(string $prefix): self
    {
        if ($prefix === '') {
            return $this;
        }

        return new self($this->path === '' ? $prefix : $prefix . '.' . $this->path, $this->code, $this->message, $this->params);
    }

    public function equals(self $other): bool
    {
        return $this->path === $other->path
            && $this->code === $other->code
            && $this->message === $other->message
            && $this->params === $other->params;
    }
}
