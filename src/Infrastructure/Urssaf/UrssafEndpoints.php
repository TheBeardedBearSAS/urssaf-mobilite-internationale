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

/**
 * Paths of the URSSAF API (relative to {@see Environment::baseUri()}). See docs/api-reference.md.
 */
final class UrssafEndpoints
{
    public const TOKEN_PATH = '/api/oauth/v1/token';
    public const TOKEN_SCOPE = 'ods.dlagd';
    public const API_BASE_PATH = '/tiersdecl/v1/mobilite';
}
