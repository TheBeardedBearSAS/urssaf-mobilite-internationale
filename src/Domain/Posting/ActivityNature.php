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
 * Nature of an activity ("Nature" schema, api-reference §3.6). Backed by the wire values, verbatim; "TOUS" is part of the contract.
 */
enum ActivityNature: string
{
    case TravailleurIndependant = 'TRAVAILLEUR_INDEPENDANT';
    case Salarie = 'SALARIE';
    case Fonctionnaire = 'FONCTIONNAIRE';
    case MembreDEquipage = 'MEMBRE_D_EQUIPAGE';
    case AgentContractuel = 'AGENT_CONTRACTUEL';
    case SalarieIntermittent = 'SALARIE_INTERMITTENT';
    case DemandeurEmploi = 'DEMANDEUR_EMPLOI';
    case ArtisteAuteur = 'ARTISTE_AUTEUR';
    case SalarieNonSalarie = 'SALARIE_NON_SALARIE';
    case NonSalarie = 'NON_SALARIE';
    case Pensionne = 'PENSIONNE';
    case Etudiant = 'ETUDIANT';
    case Autre = 'AUTRE';
    case Tous = 'TOUS';

    /**
     * @throws InvalidValue "activity_nature.unknown" for any value not in the contract (the value is never repeated)
     */
    public static function fromWire(string $value): self
    {
        return self::tryFrom($value) ?? throw InvalidValue::because('activity_nature.unknown', 'Invalid activity nature: the value is not in the list of the contract.');
    }
}
