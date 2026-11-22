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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityCriterion;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\ActivityDeclaration;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Answer;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\FreeText;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\HostAddress;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingDetails;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\SubstantialActivity;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Telework;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\CalendarDate;

/**
 * Reads the required "demande" (every field of which is optional). The host address is not read at all when
 * "usualAddress" is true: the contract ignores it, so it raises no violation.
 *
 * @internal
 */
final readonly class PostingDetailsReader
{
    private WorkerReader $workers;

    private ActivityReader $activities;

    private AddressReader $addresses;

    public function __construct(private DraftReader $reader)
    {
        $this->workers = new WorkerReader($reader);
        $this->activities = new ActivityReader($reader);
        $this->addresses = new AddressReader($reader);
    }

    public function required(string $path, ?PostingDetailsDraft $draft): ?PostingDetails
    {
        if (!$draft instanceof PostingDetailsDraft) {
            $this->reader->missing($path);

            return null;
        }
        $reader = $this->reader;

        return PostingDetails::of(
            $this->workers->read($path . '.worker', $draft->worker),
            $reader->optional($path . '.startDate', $draft->startDate, CalendarDate::fromString(...)),
            $reader->optional($path . '.endDate', $draft->endDate, CalendarDate::fromString(...)),
            $this->telework($path . '.telework', $draft->telework),
            $this->hostAddress($path . '.hostAddress', $draft->hostAddress),
            $reader->optional($path . '.sameActivitySector', $draft->sameActivitySector, Answer::fromWire(...)),
            $reader->optional($path . '.durableInfrastructure', $draft->durableInfrastructure, Answer::fromWire(...)),
            $this->substantialActivity($path . '.substantialActivity', $draft->substantialActivity),
            $draft->declaredActivity instanceof ActivityDeclarationDraft
                ? $this->activities->read($path . '.declaredActivity', $draft->declaredActivity)
                : null,
            $this->currentActivities($path . '.currentActivities', $draft->currentActivities),
            $draft->comment === null ? null : FreeText::fromString($draft->comment),
        );
    }

    private function telework(string $path, ?TeleworkDraft $draft): ?Telework
    {
        if (!$draft instanceof TeleworkDraft) {
            return null;
        }
        $teleworking = $this->reader->flag($path . '.teleworking', $draft->teleworking);
        $agreement = $draft->workerAgreement;
        $country = $this->reader->optional($path . '.country', $draft->country, Country::fromWire(...));
        $days = $draft->daysPerMonth;
        $daysPath = $path . '.daysPerMonth';
        if ($teleworking === true) {
            if ($days === null) {
                $this->reader->missing($daysPath); // "Obligatoire si le booleen teletravail est a true"

                return null;
            }

            return $this->reader->attempt($daysPath, static fn(): Telework => Telework::withTelework($agreement, $country, $days));
        }
        // Without "teleworking" the range of "daysPerMonth" is still checked (as when not teleworking), then dropped.
        $telework = $this->reader->attempt($daysPath, static fn(): Telework => Telework::withoutTelework($agreement, $country, $days));

        return $teleworking === null ? null : $telework;
    }

    private function hostAddress(string $path, ?HostAddressDraft $draft): ?HostAddress
    {
        if (!$draft instanceof HostAddressDraft) {
            return null;
        }
        $usualAddress = $this->reader->flag($path . '.usualAddress', $draft->usualAddress);
        if ($usualAddress === true) {
            return HostAddress::usualHome();
        }
        $address = $draft->address instanceof AddressDraft ? $this->addresses->read($path . '.address', $draft->address) : null;

        return $usualAddress === null ? null : HostAddress::elsewhere($address);
    }

    private function substantialActivity(string $path, ?SubstantialActivityDraft $draft): ?SubstantialActivity
    {
        if (!$draft instanceof SubstantialActivityDraft) {
            return null;
        }
        $answer = $this->reader->required($path . '.answer', $draft->answer, Answer::fromWire(...));
        $country = $this->reader->optional($path . '.countryOfInterest', $draft->countryOfInterest, Country::fromWire(...));
        $criterion = $this->reader->optional($path . '.criterion', $draft->criterion, ActivityCriterion::fromWire(...));

        return $answer instanceof Answer ? SubstantialActivity::of($answer, $country, $criterion) : null;
    }

    /**
     * @param list<ActivityDeclarationDraft> $drafts
     *
     * @return list<ActivityDeclaration> the activities that could be read
     */
    private function currentActivities(string $path, array $drafts): array
    {
        $activities = [];
        foreach ($drafts as $index => $draft) {
            $activity = $this->activities->read(\sprintf('%s[%d]', $path, $index), $draft);
            if ($activity instanceof ActivityDeclaration) {
                $activities[] = $activity;
            }
        }

        return $activities;
    }
}
