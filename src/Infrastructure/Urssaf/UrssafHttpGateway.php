<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf;

use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateAvailable;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateInProgress;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateRefusedOrUnknown;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateResult;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\DownloadedFile;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\MobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\UrssafResourceNotFound;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Enrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentCategory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentPage;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Nic;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\DossierId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingReceipt;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\ApiResponse;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\EnrolmentMapper;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\PostingReceiptMapper;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\PostingRequestEncoder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\UnreadablePayload;

/**
 * HTTP implementation of the {@see MobilityGateway} port. Build it with {@see UrssafGatewayFactory}.
 *
 * It classifies and never retries nor resends (ADR 0004); the failures are documented on the port.
 */
final readonly class UrssafHttpGateway implements MobilityGateway
{
    public function __construct(
        private CredentialsProvider $credentialsProvider,
        private JsonApiClient $client,
        private bool $certificateBadRequestAsInProgress,
        private EnrolmentMapper $enrolmentMapper = new EnrolmentMapper(),
        private PostingRequestEncoder $postingEncoder = new PostingRequestEncoder(),
        private PostingReceiptMapper $receiptMapper = new PostingReceiptMapper(),
    ) {}

    public function requestEnrolment(TenantId $tenantId, Siren $siren, ?Nic $nic, EnrolmentCategory $category): Enrolment
    {
        $response = $this->client->send(
            $tenantId,
            $this->credentialsProvider->forTenant($tenantId),
            ApiRequest::post(
                Operation::RequestEnrolment,
                UrssafEndpoints::ENROLMENTS_PATH,
                JsonEncoder::encode($this->enrolmentMapper->toRequestBody($siren, $nic, $category)),
            ),
        );

        try {
            return $this->enrolmentMapper->fromPayload($response->payload());
        } catch (UnreadablePayload $exception) {
            throw ApiResponse::unreadable(Operation::RequestEnrolment, $response->status(), $exception);
        }
    }

    /**
     * @throws InvalidValue "offset.negative" or "limit.not_positive", before any call
     */
    public function listEnrolments(TenantId $tenantId, int $offset, int $limit, ?string $sort): EnrolmentPage
    {
        if ($offset < 0) {
            throw InvalidValue::negative('offset');
        }
        if ($limit < 1) {
            throw InvalidValue::notPositive('limit');
        }

        $query = ['offset' => $offset, 'limit' => $limit];
        if ($sort !== null) {
            $query['sort'] = $sort;
        }

        $response = $this->client->send(
            $tenantId,
            $this->credentialsProvider->forTenant($tenantId),
            ApiRequest::get(Operation::ListEnrolments, UrssafEndpoints::ENROLMENTS_PATH, $query),
        );

        try {
            return EnrolmentPage::of($this->enrolmentMapper->listFromPayload($response->payload()), $offset, $limit);
        } catch (UnreadablePayload $exception) {
            throw ApiResponse::unreadable(Operation::ListEnrolments, $response->status(), $exception);
        }
    }

    public function getEnrolment(TenantId $tenantId, EnrolmentId $enrolmentId): Enrolment
    {
        $response = $this->client->send(
            $tenantId,
            $this->credentialsProvider->forTenant($tenantId),
            ApiRequest::get(Operation::GetEnrolment, self::enrolmentPath($enrolmentId)),
        );

        try {
            return $this->enrolmentMapper->fromPayload($response->payload());
        } catch (UnreadablePayload $exception) {
            throw ApiResponse::unreadable(Operation::GetEnrolment, $response->status(), $exception);
        }
    }

    public function deleteEnrolment(TenantId $tenantId, EnrolmentId $enrolmentId): void
    {
        $this->client->acknowledge(
            $tenantId,
            $this->credentialsProvider->forTenant($tenantId),
            ApiRequest::delete(Operation::DeleteEnrolment, self::enrolmentPath($enrolmentId)),
        );
    }

    public function submitPosting(TenantId $tenantId, PostingRequest $request): PostingReceipt
    {
        $response = $this->client->send(
            $tenantId,
            $this->credentialsProvider->forTenant($tenantId),
            ApiRequest::post(
                Operation::SubmitPosting,
                UrssafEndpoints::POSTING_PATH,
                JsonEncoder::encode($this->postingEncoder->encode($request)),
            ),
        );

        try {
            return $this->receiptMapper->fromPayload($response->payload());
        } catch (UnreadablePayload $exception) {
            throw ApiResponse::unreadable(Operation::SubmitPosting, $response->status(), $exception);
        }
    }

    /**
     * 200 is the file, 204 (and 400 unless disabled, ADR 0003) means "in progress", 404 "refused or unknown"; every
     * other failure is an exception.
     */
    public function getCertificate(TenantId $tenantId, DossierId $dossierId): CertificateResult
    {
        try {
            $file = $this->client->download(
                $tenantId,
                $this->credentialsProvider->forTenant($tenantId),
                ApiRequest::download(
                    Operation::GetCertificate,
                    UrssafEndpoints::CERTIFICATE_PATH . '/' . $dossierId->value(),
                ),
            );
        } catch (UrssafResourceNotFound $exception) {
            return CertificateRefusedOrUnknown::of($exception->httpStatus(), $exception->errors());
        } catch (UrssafRequestRejected $exception) {
            if (!$this->certificateBadRequestAsInProgress) {
                throw $exception;
            }

            return CertificateInProgress::of($exception->httpStatus(), $exception->errors());
        }

        return $file instanceof DownloadedFile
            ? CertificateAvailable::of($file)
            : CertificateInProgress::of(204, ApiErrors::none());
    }

    private static function enrolmentPath(EnrolmentId $enrolmentId): string
    {
        return UrssafEndpoints::ENROLMENTS_PATH . '/' . $enrolmentId->value();
    }
}
