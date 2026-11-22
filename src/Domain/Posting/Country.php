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
 * Country ("Pays" schema, api-reference §3.3, ADR 0011): the 225 non-empty values of the contract, in contract order,
 * aliases kept as separate cases, no ISO mapping. The empty string of the contract is refused. Request only: no raw
 * fallback. Case names are derived from the wire value (split on space, hyphen, apostrophe and underscore, each word
 * capitalised), e.g. "PAYS-BAS" is PaysBas.
 *
 * @mago-expect lint:too-many-enum-cases The contract defines 225 countries; splitting the closed list would invent a grouping (ADR 0011).
 */
enum Country: string
{
    case Allemagne = 'ALLEMAGNE';
    case Aurigny = 'AURIGNY';
    case Autriche = 'AUTRICHE';
    case Belgique = 'BELGIQUE';
    case Bulgarie = 'BULGARIE';
    case Chypre = 'CHYPRE';
    case Croatie = 'CROATIE';
    case Danemark = 'DANEMARK';
    case Espagne = 'ESPAGNE';
    case Estonie = 'ESTONIE';
    case Finlande = 'FINLANDE';
    case France = 'FRANCE';
    case Grece = 'GRECE';
    case Hongrie = 'HONGRIE';
    case Irlande = 'IRLANDE';
    case Islande = 'ISLANDE';
    case Italie = 'ITALIE';
    case Lettonie = 'LETTONIE';
    case Liechtenstein = 'LIECHTENSTEIN';
    case Lituanie = 'LITUANIE';
    case Luxembourg = 'LUXEMBOURG';
    case Malte = 'MALTE';
    case Norvege = 'NORVEGE';
    case PaysBas = 'PAYS-BAS';
    case Pologne = 'POLOGNE';
    case Portugal = 'PORTUGAL';
    case RepubliqueTcheque = 'REPUBLIQUE TCHEQUE';
    case Roumanie = 'ROUMANIE';
    case RoyaumeUni = 'ROYAUME-UNI';
    case Slovaquie = 'SLOVAQUIE';
    case Slovenie = 'SLOVENIE';
    case Suede = 'SUEDE';
    case Suisse = 'SUISSE';
    case Andorre = 'ANDORRE';
    case Argentine = 'ARGENTINE';
    case Bresil = 'BRESIL';
    case Canada = 'CANADA';
    case Chili = 'CHILI';
    case RepubliqueDeCoree = 'REPUBLIQUE DE COREE';
    case CoreeDuSud = 'COREE DU SUD';
    case EtatsUnis = 'ETATS-UNIS';
    case Inde = 'INDE';
    case Japon = 'JAPON';
    case Maroc = 'MAROC';
    case Monaco = 'MONACO';
    case Tunisie = 'TUNISIE';
    case Uruguay = 'URUGUAY';
    case Afghanistan = 'AFGHANISTAN';
    case AfriqueDuSud = 'AFRIQUE DU SUD';
    case Albanie = 'ALBANIE';
    case Algerie = 'ALGERIE';
    case Angola = 'ANGOLA';
    case AntiguaEtBarbuda = 'ANTIGUA-ET-BARBUDA';
    case ArabieSaoudite = 'ARABIE SAOUDITE';
    case Armenie = 'ARMENIE';
    case Australie = 'AUSTRALIE';
    case Azerbaidjan = 'AZERBAIDJAN';
    case Bahamas = 'BAHAMAS';
    case Bahrein = 'BAHREIN';
    case Bangladesh = 'BANGLADESH';
    case Barbade = 'BARBADE';
    case Belize = 'BELIZE';
    case Benin = 'BENIN';
    case Bhoutan = 'BHOUTAN';
    case Bielorussie = 'BIELORUSSIE';
    case Birmanie = 'BIRMANIE';
    case Bolivie = 'BOLIVIE';
    case BosnieHerzegovine = 'BOSNIE-HERZEGOVINE';
    case Botswana = 'BOTSWANA';
    case Brunei = 'BRUNEI';
    case Burkina = 'BURKINA';
    case Burundi = 'BURUNDI';
    case Cambodge = 'CAMBODGE';
    case Cameroun = 'CAMEROUN';
    case CapVert = 'CAP-VERT';
    case Centrafrique = 'CENTRAFRIQUE';
    case Chine = 'CHINE';
    case IleChristmas = 'ILE CHRISTMAS';
    case IlesCocosOuKeeling = 'ILES COCOS OU KEELING';
    case Colombie = 'COLOMBIE';
    case Comores = 'COMORES';
    case Congo = 'CONGO';
    case RepubliqueDemocratiqueDuCongo = 'REPUBLIQUE DEMOCRATIQUE DU CONGO';
    case IlesCook = 'ILES COOK';
    case RepubliquePopulaireDemocratiqueDeCoree = 'REPUBLIQUE POPULAIRE DEMOCRATIQUE DE COREE';
    case CoreeDuNord = 'COREE DU NORD';
    case CostaRica = 'COSTA RICA';
    case CoteDIvoire = 'COTE D\'IVOIRE';
    case Cuba = 'CUBA';
    case Djibouti = 'DJIBOUTI';
    case RepubliqueDominicaine = 'REPUBLIQUE DOMINICAINE';
    case Dominique = 'DOMINIQUE';
    case Egypte = 'EGYPTE';
    case EmiratsArabesUnis = 'EMIRATS ARABES UNIS';
    case Equateur = 'EQUATEUR';
    case Erythree = 'ERYTHREE';
    case Eswatini = 'ESWATINI';
    case Ethiopie = 'ETHIOPIE';
    case Fidji = 'FIDJI';
    case Gabon = 'GABON';
    case Gambie = 'GAMBIE';
    case Georgie = 'GEORGIE';
    case Ghana = 'GHANA';
    case Grenade = 'GRENADE';
    case Guam = 'GUAM';
    case Guatemala = 'GUATEMALA';
    case Guernesey = 'GUERNESEY';
    case Guinee = 'GUINEE';
    case GuineeEquatoriale = 'GUINEE EQUATORIALE';
    case GuineeBissao = 'GUINEE-BISSAO';
    case Guyana = 'GUYANA';
    case Haiti = 'HAITI';
    case IlesHeardEtMacdonald = 'ILES HEARD ET MACDONALD';
    case Herm = 'HERM';
    case Honduras = 'HONDURAS';
    case HongKong = 'HONG-KONG';
    case Indonesie = 'INDONESIE';
    case Iraq = 'IRAQ';
    case Iran = 'IRAN';
    case Israel = 'ISRAEL';
    case Jamaique = 'JAMAIQUE';
    case Jordanie = 'JORDANIE';
    case Kazakhstan = 'KAZAKHSTAN';
    case Kenya = 'KENYA';
    case Kirghizstan = 'KIRGHIZSTAN';
    case Kiribati = 'KIRIBATI';
    case Kosovo = 'KOSOVO';
    case Koweit = 'KOWEIT';
    case Laos = 'LAOS';
    case Lesotho = 'LESOTHO';
    case Liban = 'LIBAN';
    case Liberia = 'LIBERIA';
    case Libye = 'LIBYE';
    case Macao = 'MACAO';
    case MacedoineDuNord = 'MACEDOINE DU NORD';
    case Madagascar = 'MADAGASCAR';
    case Malaisie = 'MALAISIE';
    case Malawi = 'MALAWI';
    case Maldives = 'MALDIVES';
    case Mali = 'MALI';
    case IlesMariannesDuNord = 'ILES MARIANNES DU NORD';
    case IlesMarshall = 'ILES MARSHALL';
    case Jersey = 'JERSEY';
    case Jethou = 'JETHOU';
    case Maurice = 'MAURICE';
    case Mauritanie = 'MAURITANIE';
    case Mexique = 'MEXIQUE';
    case Micronesie = 'MICRONESIE';
    case Moldavie = 'MOLDAVIE';
    case Mongolie = 'MONGOLIE';
    case Montenegro = 'MONTENEGRO';
    case Mozambique = 'MOZAMBIQUE';
    case Namibie = 'NAMIBIE';
    case Nauru = 'NAURU';
    case Nepal = 'NEPAL';
    case Nicaragua = 'NICARAGUA';
    case Niger = 'NIGER';
    case Nigeria = 'NIGERIA';
    case Niue = 'NIUE';
    case IleNorfolk = 'ILE NORFOLK';
    case NouvelleCaledonie = 'NOUVELLE-CALEDONIE';
    case NouvelleZelande = 'NOUVELLE-ZELANDE';
    case Oman = 'OMAN';
    case Ouganda = 'OUGANDA';
    case Ouzbekistan = 'OUZBEKISTAN';
    case Pakistan = 'PAKISTAN';
    case Palaos = 'PALAOS';
    case Palestine = 'PALESTINE';
    case Panama = 'PANAMA';
    case PapouasieNouvelleGuinee = 'PAPOUASIE-NOUVELLE-GUINEE';
    case Paraguay = 'PARAGUAY';
    case Perou = 'PEROU';
    case Philippines = 'PHILIPPINES';
    case PolynesieFrancaise = 'POLYNESIE FRANCAISE';
    case PortoRico = 'PORTO RICO';
    case Qatar = 'QATAR';
    case Quebec = 'QUEBEC';
    case Russie = 'RUSSIE';
    case Rwanda = 'RWANDA';
    case SaharaOccidental = 'SAHARA OCCIDENTAL';
    case SaintBarthelemy = 'SAINT-BARTHELEMY';
    case SaintChristopheEtNieves = 'SAINT-CHRISTOPHE-ET-NIEVES';
    case SainteLucie = 'SAINTE-LUCIE';
    case SaintMarin = 'SAINT-MARIN';
    case SaintMartin = 'SAINT-MARTIN';
    case SaintPierreEtMiquelon = 'SAINT-PIERRE-ET-MIQUELON';
    case SaintVincentEtLesGrenadines = 'SAINT-VINCENT-ET-LES-GRENADINES';
    case Salomon = 'SALOMON';
    case Salvador = 'SALVADOR';
    case Samoa = 'SAMOA';
    case SamoaAmericaines = 'SAMOA AMERICAINES';
    case SaoTomeEtPrincipe = 'SAO TOME-ET-PRINCIPE';
    case Senegal = 'SENEGAL';
    case Serbie = 'SERBIE';
    case Seychelles = 'SEYCHELLES';
    case SierraLeone = 'SIERRA LEONE';
    case Singapour = 'SINGAPOUR';
    case Somalie = 'SOMALIE';
    case Soudan = 'SOUDAN';
    case SoudanDuSud = 'SOUDAN DU SUD';
    case SriLanka = 'SRI LANKA';
    case Suriname = 'SURINAME';
    case Syrie = 'SYRIE';
    case Tadjikistan = 'TADJIKISTAN';
    case Taiwan = 'TAIWAN';
    case Tanzanie = 'TANZANIE';
    case Tchad = 'TCHAD';
    case Thailande = 'THAILANDE';
    case TimorOriental = 'TIMOR ORIENTAL';
    case Togo = 'TOGO';
    case Tokelau = 'TOKELAU';
    case Tonga = 'TONGA';
    case TriniteEtTobago = 'TRINITE-ET-TOBAGO';
    case Turkmenistan = 'TURKMENISTAN';
    case Turquie = 'TURQUIE';
    case Tuvalu = 'TUVALU';
    case Ukraine = 'UKRAINE';
    case Vanuatu = 'VANUATU';
    case Vatican = 'VATICAN';
    case Venezuela = 'VENEZUELA';
    case IlesViergesDesEtatsUnis = 'ILES VIERGES DES ETATS UNIS';
    case Vietnam = 'VIETNAM';
    case Yemen = 'YEMEN';
    case Zambie = 'ZAMBIE';
    case Zimbabwe = 'ZIMBABWE';

    /**
     * @throws InvalidValue "country.unknown" for "" and for any value not in the contract (the value is never repeated)
     */
    public static function fromWire(string $value): self
    {
        return self::tryFrom($value) ?? throw InvalidValue::because('country.unknown', 'Invalid country: the value is not in the list of the contract.');
    }
}
