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
 * Paths of the URSSAF API. {@see self::TOKEN_PATH} and {@see self::API_BASE_PATH} are relative to
 * {@see Environment::baseUri()}; the other paths are relative to the API base URI ({@see ApiConfiguration::baseUri()}).
 * See docs/api-reference.md.
 */
final class UrssafEndpoints
{
    public const TOKEN_PATH = '/api/oauth/v1/token';
    public const TOKEN_SCOPE = 'ods.dlagd';
    public const API_BASE_PATH = '/tiersdecl/v1/mobilite';
    public const ENROLMENTS_PATH = '/enrolements';
    public const POSTING_PATH = '/declaration/detachement/UE';
    public const CERTIFICATE_PATH = '/certificat';
}
