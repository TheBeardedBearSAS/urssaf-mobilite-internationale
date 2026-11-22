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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Company;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\CompanyReference;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Declaration;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ExternalAccountNumber;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingDetails;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ReferenceNic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ReferenceSiren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Signature;

/**
 * Reads the required "declaration" and its four required children, at "<path>.declaringCompany",
 * "<path>.companyReference", "<path>.signature" and "<path>.posting".
 *
 * @internal
 */
final readonly class DeclarationReader
{
    private CompanyReader $companies;

    private PostingDetailsReader $details;

    public function __construct(private DraftReader $reader)
    {
        $this->companies = new CompanyReader($reader);
        $this->details = new PostingDetailsReader($reader);
    }

    public function required(string $path, ?DeclarationDraft $draft): ?Declaration
    {
        if (!$draft instanceof DeclarationDraft) {
            $this->reader->missing($path);

            return null;
        }
        $company = $this->companies->required($path . '.declaringCompany', $draft->declaringCompany);
        $reference = $this->companyReference($path . '.companyReference', $draft->companyReference);
        $signature = $this->signature($path . '.signature', $draft->signature);
        $posting = $this->details->required($path . '.posting', $draft->posting);
        if (!$company instanceof Company || !$reference instanceof CompanyReference) {
            return null;
        }

        return $signature instanceof Signature && $posting instanceof PostingDetails
            ? Declaration::of($company, $reference, $signature, $posting)
            : null;
    }

    private function companyReference(string $path, ?CompanyReferenceDraft $draft): ?CompanyReference
    {
        if (!$draft instanceof CompanyReferenceDraft) {
            $this->reader->missing($path);

            return null;
        }
        $siren = $this->reader->required($path . '.siren', $draft->siren, ReferenceSiren::fromString(...));
        $nic = $this->reader->required($path . '.nic', $draft->nic, ReferenceNic::fromString(...));
        $account = $this->reader->optional(
            $path . '.externalAccountNumber',
            $draft->externalAccountNumber,
            ExternalAccountNumber::fromString(...),
        );

        return $siren instanceof ReferenceSiren && $nic instanceof ReferenceNic ? CompanyReference::of($siren, $nic, $account) : null;
    }

    private function signature(string $path, ?SignatureDraft $draft): ?Signature
    {
        if (!$draft instanceof SignatureDraft) {
            $this->reader->missing($path);

            return null;
        }
        $personalData = $this->reader->flag($path . '.personalDataAuthorisation', $draft->personalDataAuthorisation);
        $honour = $this->reader->flag($path . '.declarationOnHonour', $draft->declarationOnHonour);

        return $personalData === null || $honour === null ? null : Signature::of($personalData, $honour);
    }
}
