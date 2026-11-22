<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A throw-away directory under the project's var/ (never the system temporary directory), for the files written by the
 * commands. Fake content only.
 */
final readonly class ScratchDirectory
{
    private function __construct(private string $path) {}

    public static function create(): self
    {
        $path = \dirname(__DIR__, 4) . '/var/functional/out/' . bin2hex(random_bytes(8));
        if (!is_dir($path) && !mkdir($path, 0o700, true) && !is_dir($path)) {
            throw new RuntimeException('The scratch directory cannot be created.');
        }

        return new self($path);
    }

    public function path(string $name): string
    {
        return $this->path . '/' . $name;
    }

    public function remove(): void
    {
        (new Filesystem())->remove($this->path);
    }
}
