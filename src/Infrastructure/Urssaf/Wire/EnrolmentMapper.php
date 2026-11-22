<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Enrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentCategory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Nic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\Instant;

/**
 * "EnrolementRequest" and "Enrolement" schemas (api-reference §2.1, ADR 0009).
 */
final class EnrolmentMapper
{
    /**
     * @return array{siren: string, nic?: string, categorie: string} "nic" absent for the whole company
     */
    public function toRequestBody(Siren $siren, ?Nic $nic, EnrolmentCategory $category): array
    {
        $body = ['siren' => $siren->value()];
        if ($nic instanceof Nic) {
            $body['nic'] = $nic->value();
        }
        $body['categorie'] = $category->value;

        return $body;
    }

    /**
     * No field is required by the contract but the identifier. "categorie" and "status" are kept raw (unknown values
     * never break a read); a present but malformed value is refused.
     *
     * @throws UnreadablePayload "idEnrolement" missing or not a UUID, malformed "siren", "nic" or date
     */
    public function fromPayload(Payload $payload): Enrolment
    {
        try {
            return Enrolment::of(
                EnrolmentId::fromString($payload->requireString('idEnrolement')),
                self::siren($payload->string('siren')),
                self::nic($payload->string('nic')),
                $payload->string('categorie'),
                $payload->string('status'),
                self::instant($payload->string('dateDemande')),
                self::instant($payload->string('dateMiseAJour')),
            );
        } catch (InvalidValue $exception) {
            throw UnreadablePayload::causedBy('a field of the enrolment is malformed', $exception);
        }
    }

    /**
     * @return list<Enrolment>
     *
     * @throws UnreadablePayload when the payload is not an array of readable enrolments
     */
    public function listFromPayload(Payload $payload): array
    {
        $enrolments = [];
        foreach ($payload->entries() as $entry) {
            $enrolments[] = $this->fromPayload($entry);
        }

        return $enrolments;
    }

    /**
     * @throws InvalidValue
     */
    private static function siren(?string $value): ?Siren
    {
        return $value === null ? null : Siren::fromString($value);
    }

    /**
     * @throws InvalidValue
     */
    private static function nic(?string $value): ?Nic
    {
        return $value === null ? null : Nic::fromString($value);
    }

    /**
     * @throws UnreadablePayload
     */
    private static function instant(?string $value): ?Instant
    {
        return $value === null ? null : DateEncoding::decodeInstant($value);
    }
}
