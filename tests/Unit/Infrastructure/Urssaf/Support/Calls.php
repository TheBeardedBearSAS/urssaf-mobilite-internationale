<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support;

use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\ApiRequest;

/**
 * One canonical call per operation, sent through the HTTP client with the method that fits its answer.
 */
final class Calls
{
    public const ENROLMENT_ID = '0b9f3f0e-0000-4000-8000-000000000001';
    public const DOSSIER_ID = '0b9f3f0e-0000-4000-8000-0000000000d1';

    /** A complete synthetic enrolment answer. */
    public const ENROLMENT = '{"idEnrolement":"0b9f3f0e-0000-4000-8000-000000000001","siren":"000000000","nic":"00001","categorie":"RG","status":"ENCOURS","dateDemande":"2026-01-02T03:04:05Z","dateMiseAJour":"2026-01-03T03:04:05Z"}';

    private function __construct() {}

    public static function request(Operation $operation): ApiRequest
    {
        return match ($operation) {
            Operation::RequestEnrolment => ApiRequest::post($operation, '/enrolements', '{"siren":"000000000"}'),
            Operation::ListEnrolments => ApiRequest::get($operation, '/enrolements', ['offset' => '0', 'limit' => '20']),
            Operation::GetEnrolment => ApiRequest::get($operation, '/enrolements/' . self::ENROLMENT_ID),
            Operation::DeleteEnrolment => ApiRequest::delete($operation, '/enrolements/' . self::ENROLMENT_ID),
            Operation::SubmitPosting => ApiRequest::post($operation, '/declaration/detachement/UE', '{}'),
            Operation::GetCertificate => ApiRequest::download($operation, '/certificat/' . self::DOSSIER_ID),
        };
    }

    /**
     * Sends the canonical call of the operation and discards its result.
     */
    public static function run(Stack $stack, Operation $operation): void
    {
        $client = $stack->jsonClient();
        $tenant = Stacks::tenant();
        $credentials = $stack->credentials->forTenant($tenant);
        $request = self::request($operation);

        if ($operation === Operation::DeleteEnrolment) {
            $client->acknowledge($tenant, $credentials, $request);

            return;
        }

        if ($operation === Operation::GetCertificate) {
            $client->download($tenant, $credentials, $request);

            return;
        }

        $client->send($tenant, $credentials, $request);
    }

    /**
     * A declared success of the operation.
     */
    public static function succeed(Stack $stack, Operation $operation): void
    {
        match ($operation) {
            Operation::RequestEnrolment => $stack->respond(201, '{"idEnrolement":"' . self::ENROLMENT_ID . '"}'),
            Operation::ListEnrolments => $stack->respond(200, '[]'),
            Operation::GetEnrolment => $stack->respond(200, '{"idEnrolement":"' . self::ENROLMENT_ID . '"}'),
            Operation::DeleteEnrolment => $stack->respond(200, ''),
            Operation::SubmitPosting => $stack->respond(200, '[{"id":"' . self::DOSSIER_ID . '"}]'),
            Operation::GetCertificate => $stack->respond(200, '%PDF-1.7 synthetic', ['Content-Type' => 'application/pdf']),
        };
    }
}
