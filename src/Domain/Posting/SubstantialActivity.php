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
 * Substantial activity in France ("activiteSubstentielle", spelling of the contract): the answer is required.
 */
final readonly class SubstantialActivity
{
    private function __construct(
        private Answer $answer,
        private ?Country $countryOfInterest,
        private ?ActivityCriterion $criterion,
    ) {}

    /**
     * @param Answer                 $answer            "activiteSubstentielle": is the activity in France above 25 %
     * @param Country|null           $countryOfInterest "paysInteret"
     * @param ActivityCriterion|null $criterion         "critereDActivite"
     */
    public static function of(Answer $answer, ?Country $countryOfInterest, ?ActivityCriterion $criterion): self
    {
        return new self($answer, $countryOfInterest, $criterion);
    }

    public function answer(): Answer
    {
        return $this->answer;
    }

    public function countryOfInterest(): ?Country
    {
        return $this->countryOfInterest;
    }

    public function criterion(): ?ActivityCriterion
    {
        return $this->criterion;
    }
}
