<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Benchmark;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\Country;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\EnrolmentMapper;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\ErrorPayloadParser;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\PostingReceiptMapper;

/**
 * Decoding of the responses, from the raw JSON string, on synthetic payloads built from the constraints of the
 * contract (no official example exists): a page of enrolments, a posting receipt, the error envelopes (object, list,
 * unreadable body) and the lookup of a country of the closed list.
 */
final readonly class WireDecodingBench
{
    /** Size of the biggest page a caller may reasonably ask for (the maximum is not published). */
    private const ENROLMENTS_PER_PAGE = 500;

    private const RECEIPT_ENTRIES = 10;

    private const COUNTRY_LOOKUPS = 100;

    private EnrolmentMapper $enrolmentMapper;

    private PostingReceiptMapper $receiptMapper;

    private ErrorPayloadParser $errorParser;

    private string $enrolmentPage;

    private string $receipt;

    private string $errorObject;

    private string $errorList;

    public function __construct()
    {
        $this->enrolmentMapper = new EnrolmentMapper();
        $this->receiptMapper = new PostingReceiptMapper();
        $this->errorParser = new ErrorPayloadParser();
        $this->enrolmentPage = self::enrolments(self::ENROLMENTS_PER_PAGE);
        $this->receipt = self::receipt(self::RECEIPT_ENTRIES);
        $this->errorObject = '{"code":"ERR","message":"Erreur fonctionnelle","description":"Donnee invalide"}';
        $this->errorList = '[' . implode(',', array_fill(0, 20, $this->errorObject)) . ']';
    }

    public function benchDecodeAPageOfEnrolments(): void
    {
        $this->enrolmentMapper->listFromPayload(JsonDecoder::decode($this->enrolmentPage));
    }

    public function benchDecodeAPostingReceipt(): void
    {
        $this->receiptMapper->fromPayload(JsonDecoder::decode($this->receipt));
    }

    public function benchParseAnErrorObject(): void
    {
        $this->errorParser->parse($this->errorObject);
    }

    public function benchParseAListOfErrors(): void
    {
        $this->errorParser->parse($this->errorList);
    }

    public function benchParseAnUnreadableErrorBody(): void
    {
        $this->errorParser->parse('<html>Bad Gateway</html>');
    }

    /**
     * A hundred lookups per call, so that the measure is far above the timer resolution.
     */
    public function benchResolveCountries(): void
    {
        for ($lookup = 0; $lookup < self::COUNTRY_LOOKUPS; ++$lookup) {
            Country::fromWire('ZIMBABWE');
            Country::fromWire('ITALIE');
        }
    }

    private static function enrolments(int $count): string
    {
        $statuses = ['ACCEPTE', 'ENCOURS', 'REFUSE', 'SUPPRIME'];
        $categories = ['AA', 'AE', 'RG', 'ACT', 'RGACT', 'TIAC', 'TIPL', 'TIAE', 'TIPM', 'AUTRE'];
        $entries = [];
        for ($index = 0; $index < $count; ++$index) {
            $entries[] = [
                'idEnrolement' => self::uuid($index),
                'siren' => str_pad((string) $index, 9, '0', STR_PAD_LEFT),
                'nic' => str_pad((string) ($index % 100_000), 5, '0', STR_PAD_LEFT),
                'categorie' => $categories[$index % \count($categories)],
                'status' => $statuses[$index % \count($statuses)],
                'dateDemande' => '2026-01-15T09:30:00+0100',
                'dateMiseAJour' => '2026-02-01T18:45:10+0100',
            ];
        }

        return json_encode($entries, JSON_THROW_ON_ERROR);
    }

    private static function receipt(int $count): string
    {
        $entries = [];
        for ($index = 0; $index < $count; ++$index) {
            $entries[] = ['id' => self::uuid($index)];
        }

        return json_encode($entries, JSON_THROW_ON_ERROR);
    }

    private static function uuid(int $index): string
    {
        return \sprintf('00000000-0000-4000-8000-%012d', $index);
    }
}
