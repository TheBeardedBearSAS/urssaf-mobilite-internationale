<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Enrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Nic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\DossierId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;

/**
 * Visibly synthetic identifiers and enrolments for the application tests.
 */
final class EnrolmentSamples
{
    public const ENROLMENT_ID = '0b9f3f0e-0000-4000-8000-000000000001';
    public const DOSSIER_ID = '0b9f3f0e-0000-4000-8000-0000000000d1';
    /** SIREN of the reference company of the synthetic posting requests. */
    public const SIREN = '000000000';

    private function __construct() {}

    public static function tenant(): TenantId
    {
        return TenantId::fromString('tenant-1');
    }

    public static function enrolmentId(): EnrolmentId
    {
        return EnrolmentId::fromString(self::ENROLMENT_ID);
    }

    public static function dossierId(): DossierId
    {
        return DossierId::fromString(self::DOSSIER_ID);
    }

    public static function enrolment(?string $rawStatus, ?string $siren = self::SIREN): Enrolment
    {
        return Enrolment::of(
            self::enrolmentId(),
            $siren === null ? null : Siren::fromString($siren),
            Nic::fromString('00001'),
            'RG',
            $rawStatus,
            null,
            null,
        );
    }
}
