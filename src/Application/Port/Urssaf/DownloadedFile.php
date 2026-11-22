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
 * A file downloaded from URSSAF (the mobility certificate, a PDF naming the posted worker: personal data): its content
 * stream and the raw headers describing it, when present (ADR 0005). The content type is exposed, not enforced.
 *
 * The content is never buffered, logged nor put in an exception; it is hidden in debug output. Writing it to disk
 * (mode 0600, existing file refused unless forced) is the job of the caller, e.g. the console command.
 */
final readonly class DownloadedFile
{
    private const MASK = '***';

    public function __construct(
        private BinaryStream $stream,
        private ?string $contentType,
        private ?string $contentDisposition,
    ) {}

    public function stream(): BinaryStream
    {
        return $this->stream;
    }

    public function contentType(): ?string
    {
        return $this->contentType;
    }

    /**
     * Raw Content-Disposition header (it may carry a file name, so it is masked in debug output too).
     */
    public function contentDisposition(): ?string
    {
        return $this->contentDisposition;
    }

    /**
     * @return array{stream: string, contentType: string|null, contentDisposition: string|null}
     */
    public function __debugInfo(): array
    {
        return [
            'stream' => self::MASK,
            'contentType' => $this->contentType,
            'contentDisposition' => $this->contentDisposition === null ? null : self::MASK,
        ];
    }
}
