<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafMobiliteInternationale\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\DossierId;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingReceipt;

/**
 * Answer of the declaration: an array of "EnteteDeclaration", `[{ "id": "<uuid>" }]` (api-reference §2.5).
 */
final class PostingReceiptMapper
{
    /**
     * @throws UnreadablePayload for an empty array or an element without a valid "id": the call may have been recorded,
     *                           the gateway reports an unknown outcome
     */
    public function fromPayload(Payload $payload): PostingReceipt
    {
        $ids = [];
        foreach ($payload->entries() as $entry) {
            $ids[] = $entry->requireString('id');
        }

        try {
            return PostingReceipt::of(...array_map(DossierId::fromString(...), $ids));
        } catch (InvalidValue $exception) {
            throw UnreadablePayload::causedBy('an "id" of the receipt is malformed or the receipt is empty', $exception);
        }
    }
}
