<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Application\Exception;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\UrssafException;

/**
 * Marker for transient failures: the same call was not processed, or is safe to repeat, and may succeed when retried
 * later (ADR 0004). The package never retries by itself; the caller decides. An unsafe write whose outcome is unknown
 * is never retryable ({@see \TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\OutcomeUnknown}).
 */
interface RetryableException extends UrssafException {}
