<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Testing;

use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateAvailable;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateInProgress;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateRefusedOrUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateResult;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\DownloadedFile;

/**
 * State of one simulated dossier: builds a fresh {@see CertificateResult} at every read, so that the content stream of
 * an available certificate can be read again.
 *
 * @internal
 */
final readonly class SimulatedCertificate
{
    private const CONTENT_TYPE = 'application/pdf';
    private const HTTP_IN_PROGRESS = 204;
    private const HTTP_REFUSED = 404;

    private function __construct(private ?string $content, private bool $refused) {}

    public static function inProgress(): self
    {
        return new self(null, false);
    }

    public static function refused(): self
    {
        return new self(null, true);
    }

    public static function available(string $content): self
    {
        return new self($content, false);
    }

    public function toResult(): CertificateResult
    {
        if ($this->refused) {
            return CertificateRefusedOrUnknown::of(self::HTTP_REFUSED, ApiErrors::none());
        }
        if ($this->content === null) {
            return CertificateInProgress::of(self::HTTP_IN_PROGRESS, ApiErrors::none());
        }

        return CertificateAvailable::of(new DownloadedFile(new SimulatedBinaryStream($this->content), self::CONTENT_TYPE, null));
    }
}
