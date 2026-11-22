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

/**
 * Error item returned by URSSAF: {"code": "...", "message": "...", "description": "..."} (api-reference §1.3). The
 * three fields are kept raw and verbatim (French), each one null when absent or not a string; their semantics are not
 * published (the Swagger descriptions of "code" and "description" look swapped) and are not reinterpreted (ADR 0002).
 * Never logged nor put in an exception message (ADR 0006).
 */
final readonly class ApiError
{
    private function __construct(private ?string $code, private ?string $message, private ?string $description) {}

    public static function of(?string $code, ?string $message, ?string $description): self
    {
        return new self($code, $message, $description);
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function hasCode(string $code): bool
    {
        return $this->code === $code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code
            && $this->message === $other->message
            && $this->description === $other->description;
    }
}
