<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\SpecVersion;

final class SpecVersionTest extends TestCase
{
    public function testItExposesTheImplementedSpecificationVersion(): void
    {
        static::assertMatchesRegularExpression('/^1\.0\.0$/', SpecVersion::CURRENT);
    }
}
