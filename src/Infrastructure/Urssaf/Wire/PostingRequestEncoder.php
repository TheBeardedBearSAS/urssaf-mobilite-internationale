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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\CompanyReference;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Declarant;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\HostAddress;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingDetails;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Signature;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\SubstantialActivity;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Telework;

/**
 * Body of `POST /declaration/detachement/UE` ("DeclarationWithDeclarantDetachementSimplifie", api-reference §2.5).
 *
 * The keys are the French names of the contract, verbatim, including the spelling "activiteSubstentielle" (twice).
 * Rules of the encoding:
 * - an optional value that is not set is omitted, never sent as null;
 * - an address the contract says is ignored is not sent: the domain keeps none for a company whose "adresseConnue" is
 *   false nor for an "adresseAccueil" whose "adresseHabituelle" is true (ADR 0008), so the encoder never emits it;
 * - "activitesDeclarees" is one object, "activitesActuelles" a list; an empty list omits the key (no activity is
 *   declared either way, and the contract does not distinguish `[]` from absence);
 * - "activiteDeclareeCplt" with no field set is omitted (an empty object carries no information);
 * - "demande", required by the contract, is a {@see JsonObject}: a request without any optional member is `{}`, not `[]`.
 *
 * The result holds personal data: it goes to the HTTP body only, never to a log nor an exception message (ADR 0006).
 */
final class PostingRequestEncoder
{
    /**
     * Encode the result with {@see JsonEncoder::encode()}.
     *
     * @return array<string, mixed>
     */
    public function encode(PostingRequest $request): array
    {
        $declaration = $request->declaration();

        return [
            'declarant' => $this->declarant($request->declarant()),
            'declaration' => [
                'entrepriseDeclarant' => PartyEncoder::company($declaration->declaringCompany()),
                'referenceEntreprise' => $this->reference($declaration->companyReference()),
                'signature' => $this->signature($declaration->signature()),
                'demande' => JsonObject::of($this->posting($declaration->posting())),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function declarant(Declarant $declarant): array
    {
        return WireMembers::present([
            'nom' => $declarant->name()->value(),
            'email' => $declarant->email()->value(),
            'tel1' => $declarant->phone1()->value(),
            'tel2' => $declarant->phone2()?->value(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reference(CompanyReference $reference): array
    {
        return WireMembers::present([
            'siren' => $reference->siren()->value(),
            'nic' => $reference->nic()->value(),
            'numeroCompteExterne' => $reference->externalAccountNumber()?->value(),
        ]);
    }

    /**
     * @return array<string, bool>
     */
    private function signature(Signature $signature): array
    {
        return [
            'autorisationDonneesPerso' => $signature->personalDataAuthorisation(),
            'declarationHonneur' => $signature->declarationOnHonour(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function posting(PostingDetails $posting): array
    {
        $current = array_map(ActivityEncoder::activity(...), $posting->currentActivities());

        return WireMembers::present([
            'personne' => PartyEncoder::worker($posting->worker()),
            'dateDebut' => WireMembers::date($posting->startDate()),
            'dateFin' => WireMembers::date($posting->endDate()),
            'teletravail' => $this->telework($posting->telework()),
            'adresseAccueil' => $this->hostAddress($posting->hostAddress()),
            'memeDomaineActivite' => $posting->sameActivitySector()?->value,
            'infraPerenne' => $posting->durableInfrastructure()?->value,
            'activiteSubstentielle' => $this->substantialActivity($posting->substantialActivity()),
            'activitesDeclarees' => ActivityEncoder::optional($posting->declaredActivity()),
            'activitesActuelles' => $current === [] ? null : $current,
            'commentaire' => $posting->comment()?->value(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function telework(?Telework $telework): ?array
    {
        if (!$telework instanceof Telework) {
            return null;
        }

        return WireMembers::present([
            'teletravail' => $telework->teleworking(),
            'accordSalarie' => $telework->workerAgreement(),
            'pays' => $telework->country()?->value,
            'nombreJours' => $telework->daysPerMonth(),
        ]);
    }

    /**
     * The domain keeps no address for the usual home ("adresseHabituelle" true: the contract ignores it).
     *
     * @return array<string, mixed>|null
     */
    private function hostAddress(?HostAddress $host): ?array
    {
        if (!$host instanceof HostAddress) {
            return null;
        }

        return WireMembers::present([
            'adresseHabituelle' => $host->usualAddress(),
            'adresse' => PartyEncoder::address($host->address()),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function substantialActivity(?SubstantialActivity $activity): ?array
    {
        if (!$activity instanceof SubstantialActivity) {
            return null;
        }

        return WireMembers::present([
            'activiteSubstentielle' => $activity->answer()->value,
            'paysInteret' => $activity->countryOfInterest()?->value,
            'critereDActivite' => $activity->criterion()?->value,
        ]);
    }
}
