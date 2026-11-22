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

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Address;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Company;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Worker;

/**
 * "Entreprise", "Adresse" and "Personne" schemas of the declaration. The domain keeps no address for a company whose
 * "adresseConnue" is false (the contract says it is ignored), so none is ever emitted for it.
 *
 * @internal
 */
final class PartyEncoder
{
    /**
     * @return array<string, mixed>
     */
    public static function company(Company $company): array
    {
        return WireMembers::present([
            'raisonSociale' => $company->name()->value(),
            'adresse' => self::address($company->address()),
            'adresseConnue' => $company->addressKnown(),
            'email' => $company->email()->value(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function address(?Address $address): ?array
    {
        if (!$address instanceof Address) {
            return null;
        }

        return WireMembers::present([
            'adresse' => $address->line()->value(),
            'adresseCplt' => $address->complement()?->value(),
            'ville' => $address->city()?->value(),
            'codePostal' => $address->postalCode()?->value(),
            'pays' => $address->country()->value,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function worker(?Worker $worker): ?array
    {
        if (!$worker instanceof Worker) {
            return null;
        }

        return WireMembers::present([
            'nir' => $worker->nir()->value(),
            'cleNir' => $worker->nirKey()?->value(),
            'nomUsage' => $worker->usageName()->value(),
            'nomNaissance' => $worker->birthName()?->value(),
            'prenom' => $worker->firstName()->value(),
            'dateNaissance' => WireMembers::date($worker->birthDate()),
            'lieuNaissance' => $worker->birthPlace()?->value(),
            'paysNaissance' => $worker->birthCountry()?->value,
            'nationalite' => $worker->nationality()->value,
            'adresse' => self::address($worker->address()),
            'email' => $worker->email()?->value(),
            'tel1' => $worker->phone1()?->value(),
            'tel2' => $worker->phone2()?->value(),
        ]);
    }
}
