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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;

/**
 * Answer of an accepted posting request: the array of "EnteteDeclaration" = { "id" }, kept whole and in order
 * (cardinality unexplained, api-reference §5.12; ADR 0004). Never empty. The caller must store it: nothing lists postings.
 */
final readonly class PostingReceipt
{
    /**
     * @param non-empty-list<DossierId> $dossierIds
     */
    private function __construct(private array $dossierIds) {}

    /**
     * @throws InvalidValue "posting_receipt.empty" without any identifier
     */
    public static function of(DossierId ...$dossierIds): self
    {
        $ids = array_values($dossierIds); // named or string-keyed spread arguments must still yield a list
        if ($ids === []) {
            throw InvalidValue::empty('posting_receipt');
        }

        return new self($ids);
    }

    /**
     * @return non-empty-list<DossierId>
     */
    public function dossierIds(): array
    {
        return $this->dossierIds;
    }

    /**
     * First identifier: the dossier number to pass to "GET /certificat/{id}" when there is only one.
     */
    public function first(): DossierId
    {
        return $this->dossierIds[0];
    }
}
