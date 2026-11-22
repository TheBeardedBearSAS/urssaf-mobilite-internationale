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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Wire\ErrorPayloadParser;

final class ErrorPayloadParserTest extends TestCase
{
    public function testItParsesASingleErrorObjectWithRawFields(): void
    {
        $errors = (new ErrorPayloadParser())->parse('{"code": "CLASSE", "message": " libellé ", "description": "E123"}');

        static::assertCount(1, $errors);
        static::assertSame('CLASSE', $errors->all()[0]->code());
        static::assertSame(' libellé ', $errors->all()[0]->message());
        static::assertSame('E123', $errors->all()[0]->description());
    }

    public function testItParsesAListOfErrorObjectsInOrder(): void
    {
        $errors = (new ErrorPayloadParser())->parse(
            '[{"code": "A", "message": "m1", "description": "d1"}, {"code": "B", "message": "m2", "description": "d2"}]',
        );

        static::assertSame(['A', 'B'], $errors->codes());
        static::assertSame('m2', $errors->all()[1]->message());
        static::assertSame('d2', $errors->all()[1]->description());
    }

    public function testItKeepsAbsentAndMistypedFieldsAsNull(): void
    {
        $errors = (new ErrorPayloadParser())->parse('[{"message": "only a message"}, {"code": 42, "description": "only a description"}, {"code": "only"}]');

        static::assertCount(3, $errors);
        static::assertNull($errors->all()[0]->code());
        static::assertSame('only a message', $errors->all()[0]->message());
        static::assertNull($errors->all()[0]->description());
        static::assertNull($errors->all()[1]->code());
        static::assertNull($errors->all()[1]->message());
        static::assertSame('only a description', $errors->all()[1]->description());
        static::assertSame('only', $errors->all()[2]->code());
        static::assertNull($errors->all()[2]->message());
        static::assertNull($errors->all()[2]->description());
    }

    #[DataProvider('bodiesWithoutErrors')]
    public function testAnUnreadableOrEmptyBodyYieldsNoError(string $body): void
    {
        $errors = (new ErrorPayloadParser())->parse($body);

        static::assertTrue($errors->isEmpty());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function bodiesWithoutErrors(): iterable
    {
        yield 'empty body' => [''];
        yield 'html' => ['<html>Bad gateway</html>'];
        yield 'invalid json' => ['{"code": '];
        yield 'scalar' => ['"oops"'];
        yield 'null' => ['null'];
        yield 'empty object' => ['{}'];
        yield 'empty list' => ['[]'];
        yield 'object without the three fields' => ['{"errors": [{"code": "A"}]}'];
        yield 'list with a scalar' => ['[{"code": "A"}, "oops"]'];
        yield 'list of lists' => ['[["x"]]'];
    }
}
