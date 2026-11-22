<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting;

/**
 * The request data ("declaration"): its four children are required by the contract. Optional in the Swagger, required
 * by the package (ADR 0008). Personal data.
 */
final readonly class Declaration
{
    use PersonalData;

    private function __construct(
        private Company $declaringCompany,
        private CompanyReference $companyReference,
        private Signature $signature,
        private PostingDetails $posting,
    ) {}

    /**
     * @param Company          $declaringCompany "entrepriseDeclarant"
     * @param CompanyReference $companyReference "referenceEntreprise"
     * @param PostingDetails   $posting          "demande"
     */
    public static function of(Company $declaringCompany, CompanyReference $companyReference, Signature $signature, PostingDetails $posting): self
    {
        return new self($declaringCompany, $companyReference, $signature, $posting);
    }

    public function declaringCompany(): Company
    {
        return $this->declaringCompany;
    }

    public function companyReference(): CompanyReference
    {
        return $this->companyReference;
    }

    public function signature(): Signature
    {
        return $this->signature;
    }

    public function posting(): PostingDetails
    {
        return $this->posting;
    }
}
