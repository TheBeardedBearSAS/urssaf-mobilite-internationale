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
 * HTTP 200: the certificate is available; its content is streamed, never buffered (ADR 0005).
 */
final readonly class CertificateAvailable implements CertificateResult
{
    private const HTTP_STATUS = 200;

    private function __construct(private DownloadedFile $file) {}

    public static function of(DownloadedFile $file): self
    {
        return new self($file);
    }

    public function file(): DownloadedFile
    {
        return $this->file;
    }

    public function httpStatus(): int
    {
        return self::HTTP_STATUS;
    }
}
