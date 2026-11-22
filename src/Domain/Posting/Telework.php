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
 * Telework ("teletravail"): "teletravail" required; "nombreJours" 1–31, "Obligatoire si le booleen teletravail est a
 * true" (enforced by the signature of {@see self::withTelework()}). "accordSalarie" and "pays" are optional here; the PDF rule requiring them is opt-in
 * ({@see PostingValidationPolicy::withPdfTeleworkRules()}). Personal data.
 */
final readonly class Telework
{
    use PersonalData;

    public const MIN_DAYS = 1;
    public const MAX_DAYS = 31;

    private function __construct(
        private bool $teleworking,
        private ?bool $workerAgreement,
        private ?Country $country,
        private ?int $daysPerMonth,
    ) {}

    /**
     * "teletravail" true: "nombreJours" is then required (Swagger description), hence not nullable.
     *
     * @param bool|null $workerAgreement "accordSalarie"
     * @param int       $daysPerMonth    "nombreJours"
     *
     * @throws InvalidValue "days_per_month.out_of_range"
     */
    public static function withTelework(?bool $workerAgreement, ?Country $country, int $daysPerMonth): self
    {
        return new self(true, $workerAgreement, $country, self::checkedDays($daysPerMonth));
    }

    /**
     * "teletravail" false. The contract forbids none of the other fields; "nombreJours" keeps its range.
     *
     * @param bool|null $workerAgreement "accordSalarie"
     * @param int|null  $daysPerMonth    "nombreJours"
     *
     * @throws InvalidValue "days_per_month.out_of_range"
     */
    public static function withoutTelework(?bool $workerAgreement, ?Country $country, ?int $daysPerMonth): self
    {
        return new self(false, $workerAgreement, $country, $daysPerMonth === null ? null : self::checkedDays($daysPerMonth));
    }

    public function teleworking(): bool
    {
        return $this->teleworking;
    }

    public function workerAgreement(): ?bool
    {
        return $this->workerAgreement;
    }

    public function country(): ?Country
    {
        return $this->country;
    }

    public function daysPerMonth(): ?int
    {
        return $this->daysPerMonth;
    }

    private static function checkedDays(int $daysPerMonth): int
    {
        if ($daysPerMonth < self::MIN_DAYS || $daysPerMonth > self::MAX_DAYS) {
            throw InvalidValue::because(
                'days_per_month.out_of_range',
                \sprintf('Invalid days_per_month: the value must be between %d and %d.', self::MIN_DAYS, self::MAX_DAYS),
                ['min' => self::MIN_DAYS, 'max' => self::MAX_DAYS],
            );
        }

        return $daysPerMonth;
    }
}
