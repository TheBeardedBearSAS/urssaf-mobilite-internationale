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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Declarant;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Declaration;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Label;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingValidationPolicy;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\PhoneNumber;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Validation\Violation;

/**
 * Turns a raw {@see PostingRequestDraft} into a {@see PostingRequest}, reporting every violation at once (ADR 0008):
 * - each field is parsed into its value object: "field.required" when null, the value object code otherwise
 *   (e.g. "label.too_long", "country.unknown"), at paths such as "declarant.email", "declaration.posting.worker.nir",
 *   "declaration.posting.currentActivities[1].company.name";
 * - "declarant", "declaration" and the four children of "declaration" are required; inside "demande" only the required
 *   keys of the optional objects are, plus "daysPerMonth" when teleworking;
 * - once the request could be built, the rules of the {@see PostingValidationPolicy} (a policy violation is not
 *   repeated at a path that already has one).
 *
 * The rejected values are never part of a violation.
 */
final readonly class PostingRequestReader
{
    private function __construct(private DraftReader $reader) {}

    public static function read(PostingRequestDraft $draft, PostingValidationPolicy $policy): PostingDraftReading
    {
        $reader = new DraftReader();
        $self = new self($reader);
        $declarant = $self->declarant($draft->declarant);
        $declaration = (new DeclarationReader($reader))->required('declaration', $draft->declaration);
        if (!$declarant instanceof Declarant || !$declaration instanceof Declaration) {
            return new IncompletePostingDraft($reader->result());
        }
        $request = PostingRequest::of($declarant, $declaration);
        $self->applyPolicy($policy->validate($request));

        return new CompletePostingDraft($reader->result(), $request);
    }

    private function declarant(?DeclarantDraft $draft): ?Declarant
    {
        if (!$draft instanceof DeclarantDraft) {
            $this->reader->missing('declarant');

            return null;
        }
        $name = $this->reader->required('declarant.name', $draft->name, Label::fromString(...));
        $email = $this->reader->required('declarant.email', $draft->email, EmailAddress::fromString(...));
        $phone1 = $this->reader->required('declarant.phone1', $draft->phone1, PhoneNumber::fromString(...));
        $phone2 = $this->reader->optional('declarant.phone2', $draft->phone2, PhoneNumber::fromString(...));
        if (!$name instanceof Label || !$email instanceof EmailAddress || !$phone1 instanceof PhoneNumber) {
            return null;
        }

        return Declarant::of($name, $email, $phone1, $phone2);
    }

    private function applyPolicy(ValidationResult $policyResult): void
    {
        $reported = array_map(static fn(Violation $violation): string => $violation->path(), $this->reader->result()->all());
        foreach ($policyResult->all() as $violation) {
            if (\in_array($violation->path(), $reported, true)) {
                continue;
            }
            $this->reader->merge(ValidationResult::of($violation));
        }
    }
}
