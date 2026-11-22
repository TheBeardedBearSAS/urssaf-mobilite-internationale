<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Wire;

use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\UnreadablePayload;

final class JsonDecoderTest extends TestCase
{
    public function testItDecodesAnObjectAndAList(): void
    {
        static::assertSame('v', JsonDecoder::decode('{"k": "v"}')->string('k'));
        static::assertTrue(JsonDecoder::decode('[{"k": "v"}]')->isList());
    }

    public function testItKeepsHugeIntegersAsStringsInsteadOfLosingPrecision(): void
    {
        $payload = JsonDecoder::decode('{"k": "9999999999999999999999"}');

        static::assertSame('9999999999999999999999', $payload->string('k'));
    }

    #[DataProvider('invalidBodies')]
    public function testItRefusesWhatIsNotAnObjectNorAnArray(string $body, string $reason): void
    {
        try {
            JsonDecoder::decode($body);
            static::fail('Expected an UnreadablePayload.');
        } catch (UnreadablePayload $exception) {
            static::assertSame($reason, $exception->reason());
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'empty' => ['', 'body is not valid JSON'];
        yield 'html' => ['<html>Bad gateway</html>', 'body is not valid JSON'];
        yield 'truncated' => ['{"k": ', 'body is not valid JSON'];
        yield 'scalar' => ['"text"', 'body is neither a JSON object nor a JSON array'];
        yield 'number' => ['12', 'body is neither a JSON object nor a JSON array'];
        yield 'null' => ['null', 'body is neither a JSON object nor a JSON array'];
    }

    public function testItKeepsTheJsonErrorAsPreviousExceptionWithoutTheBody(): void
    {
        try {
            JsonDecoder::decode('{broken-secret');
            static::fail('Expected an UnreadablePayload.');
        } catch (UnreadablePayload $exception) {
            static::assertInstanceOf(JsonException::class, $exception->getPrevious());
            static::assertStringNotContainsString('secret', $exception->getMessage());
        }
    }

    public function testItAcceptsTheMaximumDepthAndRefusesOneMore(): void
    {
        static::assertTrue(JsonDecoder::decode(str_repeat('[', 511) . str_repeat(']', 511))->isList());

        $this->expectException(UnreadablePayload::class);
        JsonDecoder::decode(str_repeat('[', 512) . str_repeat(']', 512));

    }

    public function testItRefusesTooDeepDocuments(): void
    {
        $this->expectException(UnreadablePayload::class);
        JsonDecoder::decode(str_repeat('[', 513) . str_repeat(']', 513));
    }
}
