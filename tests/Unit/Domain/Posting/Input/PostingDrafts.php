<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Domain\Posting\Input;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\ActivityDeclarationDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\ActivityDetailsDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\AddressDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\CompanyDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\CompanyReferenceDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\DeclarantDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\DeclarationDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\HostAddressDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\PostingDetailsDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\PostingRequestDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\SignatureDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\SubstantialActivityDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\TeleworkDraft;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Input\WorkerDraft;

/**
 * Complete drafts with synthetic values only (SIREN and NIR are the examples of the official documentation).
 */
final class PostingDrafts
{
    public static function address(): AddressDraft
    {
        return new AddressDraft('1 rue Exemple', null, 'Villexemple', '00000', 'ITALIE');
    }

    public static function worker(): WorkerDraft
    {
        return new WorkerDraft(
            '0000000000000',
            '00',
            'Exemple',
            'Naissance',
            'Prenom',
            '2000-01-01',
            'Villexemple',
            'ESPAGNE',
            'FRANCE',
            self::address(),
            'test@example.invalid',
            '0000000001',
            '0000000002',
        );
    }

    public static function company(): CompanyDraft
    {
        return new CompanyDraft('Entreprise exemple', true, self::address(), 'company@example.invalid');
    }

    public static function activity(): ActivityDeclarationDraft
    {
        return new ActivityDeclarationDraft(
            'SALARIE',
            'ITALIE',
            self::company(),
            'AUTRE',
            'Autre nature',
            'MALTE',
            false,
            true,
            false,
            true,
            new ActivityDetailsDraft(true, false, null, true, false, null),
        );
    }

    public static function details(): PostingDetailsDraft
    {
        return new PostingDetailsDraft(
            self::worker(),
            '2026-01-01',
            '2026-06-30',
            new TeleworkDraft(true, true, 'ITALIE', 2),
            new HostAddressDraft(false, self::address()),
            'OUI',
            'NC',
            new SubstantialActivityDraft('NON', 'ITALIE', 'TempsTravail'),
            self::activity(),
            [self::activity(), self::activity()],
            'Commentaire',
        );
    }

    public static function emptyDetails(): PostingDetailsDraft
    {
        return new PostingDetailsDraft(null, null, null, null, null, null, null, null, null, [], null);
    }

    public static function declaration(PostingDetailsDraft $details): DeclarationDraft
    {
        return new DeclarationDraft(
            self::company(),
            new CompanyReferenceDraft('000000000', '00022', '000000000000000000'),
            new SignatureDraft(true, true),
            $details,
        );
    }

    public static function declarant(): DeclarantDraft
    {
        return new DeclarantDraft('Nom exemple', 'test@example.invalid', '0000000003', null);
    }

    public static function request(PostingDetailsDraft $details): PostingRequestDraft
    {
        return new PostingRequestDraft(self::declarant(), self::declaration($details));
    }
}
