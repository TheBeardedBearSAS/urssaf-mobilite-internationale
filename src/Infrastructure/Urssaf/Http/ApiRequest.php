<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Http;

use SensitiveParameter;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\PersonalDataNotSerializable;

/**
 * Description of one API call, independent from the HTTP library (ADR 0002). The path is relative to
 * {@see \TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\ApiConfiguration::baseUri()} and only
 * carries UUIDs validated by the domain; the query is flat (`offset`, `limit`, `sort`).
 *
 * @internal
 */
final readonly class ApiRequest
{
    /**
     * @param array<string, int|string> $query
     */
    private function __construct(
        private Operation $operation,
        private string $method,
        private string $path,
        private array $query,
        private ?string $jsonBody,
        private bool $download,
    ) {}

    /**
     * @param array<string, int|string> $query
     */
    public static function get(Operation $operation, string $path, array $query = []): self
    {
        return new self($operation, 'GET', $path, $query, null, false);
    }

    public static function post(Operation $operation, string $path, #[SensitiveParameter] string $jsonBody): self
    {
        return new self($operation, 'POST', $path, [], $jsonBody, false);
    }

    public static function delete(Operation $operation, string $path): self
    {
        return new self($operation, 'DELETE', $path, [], null, false);
    }

    /**
     * A GET whose 200 answer is a binary file (the certificate): never decoded, never buffered.
     */
    public static function download(Operation $operation, string $path): self
    {
        return new self($operation, 'GET', $path, [], null, true);
    }

    /**
     * The JSON body of a POST is a declaration (NIR, names, addresses): masked in debug output (ADR 0006).
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'operation' => $this->operation,
            'method' => $this->method,
            'path' => $this->path,
            'query' => $this->query,
            'jsonBody' => $this->jsonBody === null ? null : '***',
            'download' => $this->download,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw PersonalDataNotSerializable::create();
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw PersonalDataNotSerializable::create();
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, int|string>
     */
    public function query(): array
    {
        return $this->query;
    }

    public function jsonBody(): ?string
    {
        return $this->jsonBody;
    }

    public function isDownload(): bool
    {
        return $this->download;
    }

    /**
     * Whether $status is a success status declared by the contract for this operation (api-reference §2).
     */
    public function declares(int $status): bool
    {
        return match ($this->operation) {
            Operation::RequestEnrolment => $status === 201,
            Operation::ListEnrolments,
            Operation::GetEnrolment,
            Operation::DeleteEnrolment,
            Operation::SubmitPosting => $status === 200,
            Operation::GetCertificate => $status === 200 || $status === 204,
        };
    }
}
