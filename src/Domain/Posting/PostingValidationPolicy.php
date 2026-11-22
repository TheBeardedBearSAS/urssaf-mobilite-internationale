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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Validation\Violation;

/**
 * Opt-in local rules beyond the Swagger (ADR 0008). The default validates the Swagger rules only, which the typed
 * objects and the draft reader already enforce, so its {@see self::validate()} always passes.
 *
 * Violation paths are those of the draft reader (English draft property names), e.g. "declaration.posting.telework.country".
 */
final readonly class PostingValidationPolicy
{
    private const TELEWORK_PATH = 'declaration.posting.telework.';

    private function __construct(private bool $pdfTeleworkRules) {}

    /**
     * Swagger rules only.
     */
    public static function default(): self
    {
        return new self(false);
    }

    /**
     * Also applies the PDF rule (api-reference §5.7): "accordSalarie" and "pays" must be present when "teletravail" is true.
     */
    public function withPdfTeleworkRules(): self
    {
        return new self(true);
    }

    public function appliesPdfTeleworkRules(): bool
    {
        return $this->pdfTeleworkRules;
    }

    public function validate(PostingRequest $request): ValidationResult
    {
        $telework = $request->declaration()->posting()->telework();
        $result = ValidationResult::valid();
        if (!$this->pdfTeleworkRules || !$telework instanceof Telework || !$telework->teleworking()) {
            return $result;
        }
        if (!$telework->country() instanceof Country) {
            $result = $result->with(self::requiredWhenTeleworking('country'));
        }
        if ($telework->workerAgreement() === null) {
            $result = $result->with(self::requiredWhenTeleworking('workerAgreement'));
        }

        return $result;
    }

    private static function requiredWhenTeleworking(string $field): Violation
    {
        return Violation::error(self::TELEWORK_PATH . $field, 'field.required', 'This field is required when teleworking (PDF rule).');
    }
}
