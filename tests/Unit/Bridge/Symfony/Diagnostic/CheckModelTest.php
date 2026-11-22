<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Bridge\Symfony\Diagnostic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\CheckContext;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\CheckResult;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\CheckStatus;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\CredentialsCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\Diagnostic\SimulatedGatewayCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\StubCheck;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;

#[CoversClass(CheckContext::class)]
#[CoversClass(CheckResult::class)]
#[CoversClass(CheckStatus::class)]
#[CoversClass(CredentialsCheck::class)]
#[CoversClass(SimulatedGatewayCheck::class)]
final class CheckModelTest extends TestCase
{
    public function testAContextThatAllowsRemoteCalls(): void
    {
        $tenant = TenantId::fromString('tenant-1');

        $context = CheckContext::withRemoteCalls([$tenant]);

        static::assertSame([$tenant], $context->tenants);
        static::assertTrue($context->remoteCallsAllowed);
    }

    public function testAContextThatForbidsRemoteCalls(): void
    {
        $tenant = TenantId::fromString('tenant-1');

        $context = CheckContext::withoutRemoteCalls([$tenant]);

        static::assertSame([$tenant], $context->tenants);
        static::assertFalse($context->remoteCallsAllowed);
    }

    public function testTheStatusValues(): void
    {
        static::assertSame(
            ['ok', 'warning', 'failure', 'skipped'],
            array_map(static fn(CheckStatus $status): string => $status->value, CheckStatus::cases()),
        );
    }

    public function testACheckReturnsResults(): void
    {
        $results = (new StubCheck())->run(CheckContext::withRemoteCalls([TenantId::fromString('tenant-1')]));

        static::assertCount(1, $results);
        static::assertSame('stub', $results[0]->check);
        static::assertSame('', $results[0]->subject);
        static::assertSame(CheckStatus::Ok, $results[0]->status);
        static::assertSame('fine', $results[0]->detail);
    }

    public function testEveryTenantHasItsCredentialsChecked(): void
    {
        $check = new CredentialsCheck(new StaticCredentialsProvider());
        $context = CheckContext::withRemoteCalls([TenantId::fromString('tenant-1'), TenantId::fromString('tenant-2')]);

        static::assertEquals(
            [
                new CheckResult('credentials', 'tenant-1', CheckStatus::Ok, 'Credentials found.'),
                new CheckResult('credentials', 'tenant-2', CheckStatus::Ok, 'Credentials found.'),
            ],
            $check->run($context),
        );
    }

    public function testATenantWithoutCredentialsFailsWithAMessageThatNamesNoSecret(): void
    {
        $results = (new CredentialsCheck(new InMemoryCredentialsProvider([])))->run(CheckContext::withoutRemoteCalls([TenantId::fromString('tenant-9')]));

        static::assertEquals(
            [new CheckResult('credentials', 'tenant-9', CheckStatus::Failure, 'No credentials are configured for tenant "tenant-9".')],
            $results,
        );
    }

    public function testTheSimulatedGatewayIsAWarning(): void
    {
        $results = (new SimulatedGatewayCheck())->run(CheckContext::withRemoteCalls([TenantId::fromString('tenant-1')]));

        static::assertEquals(
            [new CheckResult('gateway', '', CheckStatus::Warning, 'Simulated gateway ("gateway.simulated"): nothing is sent to URSSAF.')],
            $results,
        );
    }
}
