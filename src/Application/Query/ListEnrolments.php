<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Application\Query;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;

/**
 * Lists one page of enrolments ("GET /enrolements", ADR 0009). The defaults (offset 0, limit 20) are a package choice,
 * not a server rule; no maximum is enforced and "sort" is sent verbatim (syntax not published).
 */
final readonly class ListEnrolments
{
    public const DEFAULT_OFFSET = 0;
    public const DEFAULT_LIMIT = 20;

    /**
     * The first page with the default size, in the server order.
     */
    public static function firstPage(TenantId $tenantId): self
    {
        return new self($tenantId, self::DEFAULT_OFFSET, self::DEFAULT_LIMIT, null);
    }

    /**
     * @throws InvalidValue "offset.negative", "limit.not_positive" or "sort.empty"
     */
    public function __construct(public TenantId $tenantId, public int $offset, public int $limit, public ?string $sort)
    {
        if ($offset < 0) {
            throw InvalidValue::negative('offset');
        }
        if ($limit < 1) {
            throw InvalidValue::notPositive('limit');
        }
        if ($sort !== null && trim($sort) === '') {
            throw InvalidValue::empty('sort');
        }
    }
}
