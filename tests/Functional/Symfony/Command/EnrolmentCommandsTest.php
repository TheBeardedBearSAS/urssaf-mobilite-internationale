<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-mobilite-internationale.
 *
 * Copyright (c) TheBeardedBear and the urssaf-mobilite-internationale contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Operation;
use TheBeardedBear\UrssafMobiliteInternationale\Testing\SimulatedMobilityGateway;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafMobiliteInternationale\Tests\Unit\Infrastructure\Urssaf\Support\Calls;

/**
 * The enrolment commands: output, exit codes (0 success, 1 failure, 2 invalid usage) and what is never printed.
 *
 * @mago-expect lint:too-many-methods One test per command outcome.
 */
final class EnrolmentCommandsTest extends TestCase
{
    private const FIRST_SIMULATED_ID = '00000000-0000-4000-8000-000000000001';

    public function testTheCommandsAreDefined(): void
    {
        $harness = Harness::boot();
        $application = new Application($harness->kernel);

        $list = $application->find('urssaf:mi:enrolment:list');
        static::assertSame('Lists the enrolments of the URSSAF international mobility API', $list->getDescription());
        static::assertSame('0', $list->getDefinition()->getOption('offset')->getDefault());
        static::assertSame('20', $list->getDefinition()->getOption('limit')->getDefault());
        static::assertTrue($list->getDefinition()->getOption('sort')->isValueRequired());
        static::assertFalse($list->getDefinition()->getOption('show-urssaf-errors')->isNegatable());

        $request = $application->find('urssaf:mi:enrolment:request');
        static::assertSame('Requests the enrolment of a company with the URSSAF international mobility API', $request->getDescription());
        static::assertTrue($request->getDefinition()->getArgument('siren')->isRequired());
        static::assertTrue($request->getDefinition()->getOption('nic')->isValueRequired());
        static::assertTrue($request->getDefinition()->getOption('category')->isValueRequired());
        static::assertSame(
            'Category of the enrolment: AA, AE, RG, ACT, RGACT, TIAC, TIPL, TIAE, TIPM, AUTRE',
            $request->getDefinition()->getOption('category')->getDescription(),
        );

        static::assertSame('Shows one enrolment of the URSSAF international mobility API', $application->find('urssaf:mi:enrolment:show')->getDescription());
        static::assertSame('Deletes an enrolment of the URSSAF international mobility API', $application->find('urssaf:mi:enrolment:delete')->getDescription());
        static::assertFalse($application->find('urssaf:mi:enrolment:delete')->getDefinition()->getOption('force')->acceptValue());
    }

    public function testNoOtherNamespaceIsUsed(): void
    {
        $harness = Harness::boot();
        $names = array_keys((new Application($harness->kernel))->all());

        $own = array_values(array_filter($names, static fn(int|string $name): bool => str_starts_with((string) $name, 'urssaf')));
        sort($own);

        static::assertSame(
            ['urssaf:mi:certificate', 'urssaf:mi:check', 'urssaf:mi:enrolment:delete', 'urssaf:mi:enrolment:list', 'urssaf:mi:enrolment:request', 'urssaf:mi:enrolment:show'],
            $own,
        );
    }

    public function testRequestingAnEnrolmentPrintsItsIdAndStatus(): void
    {
        $harness = Harness::boot(['gateway' => ['simulated' => true]]);
        $command = $harness->command('urssaf:mi:enrolment:request');

        static::assertSame(0, $command->execute(['siren' => '000000000', '--nic' => '00001', '--category' => 'RG']));

        $rows = Output::rows($command->getDisplay());
        static::assertContains(['id', self::FIRST_SIMULATED_ID], $rows);
        static::assertContains(['siren', '000000000'], $rows);
        static::assertContains(['nic', '00001'], $rows);
        static::assertContains(['category', 'RG'], $rows);
        static::assertContains(['status', 'ENCOURS'], $rows);
        static::assertContains(['requested at', '2026-01-01T00:00:00Z'], $rows);
        static::assertStringContainsString('Enrolment requested.', $command->getDisplay());
        static::assertSame(
            [Operation::RequestEnrolment],
            array_map(static fn(array $call): Operation => $call['operation'], $harness->get(SimulatedMobilityGateway::class)->calls()),
        );
    }

    public function testTheNicIsOptionalAndTheTenantCanBeGiven(): void
    {
        $harness = Harness::boot(['gateway' => ['simulated' => true]]);
        $command = $harness->command('urssaf:mi:enrolment:request');

        static::assertSame(0, $command->execute(['siren' => '000000000', '--category' => 'AE', '--tenant' => 'tenant-1']));

        static::assertContains(['nic', '-'], Output::rows($command->getDisplay()));
    }

    public function testTheEnrolmentRequestIsSentToUrssaf(): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:mi:enrolment:request');

        static::assertSame(0, $command->execute(['siren' => '000000000', '--nic' => '00001', '--category' => 'RG']));

        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_ENROLMENT_REQUEST));
        $body = (string) self::lastRequest($harness)->getBody();
        static::assertStringContainsString('000000000', $body);
        static::assertStringContainsString('RG', $body);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidRequests(): iterable
    {
        yield 'invalid siren' => [['siren' => '12', '--category' => 'RG'], 'The SIREN must be exactly 9 characters long.'];
        yield 'invalid nic' => [['siren' => '000000000', '--nic' => '1', '--category' => 'RG'], 'The NIC must be exactly 5 characters long.'];
        yield 'missing category' => [['siren' => '000000000'], 'The --category option is required: AA, AE, RG, ACT, RGACT, TIAC, TIPL, TIAE, TIPM, AUTRE.'];
        yield 'unknown category' => [['siren' => '000000000', '--category' => 'XX'], 'The --category option is required'];
        yield 'invalid tenant' => [['siren' => '000000000', '--category' => 'RG', '--tenant' => ' '], 'The tenant id is required.'];
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('invalidRequests')]
    public function testAnInvalidRequestIsAnInvalidUsageAndNothingIsSent(array $input, string $message): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:mi:enrolment:request');

        static::assertSame(2, $command->execute($input));

        static::assertStringContainsString($message, $command->getDisplay());
        static::assertSame([], $harness->client->getRequests());
    }

    public function testSeveralConfiguredTenantsNeedTheTenantOption(): void
    {
        // @mago-expect lint:no-literal-password The secret is an environment placeholder, set by the test kernel.
        $harness = Harness::boot(['credentials' => ['tenant-2' => ['client_id' => 'client-2', 'client_secret' => '%env(URSSAF_TEST_SECRET)%']]]);
        $command = $harness->command('urssaf:mi:enrolment:list');

        static::assertSame(2, $command->execute([]));

        static::assertStringContainsString('The --tenant option is required unless exactly one tenant is configured ("urssaf_mi.tenants").', $command->getDisplay());
        static::assertSame([], $harness->client->getRequests());
    }

    public function testListingPrintsTheFirstPageByDefault(): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:mi:enrolment:list');

        static::assertSame(0, $command->execute([]));

        static::assertSame('offset=0&limit=20', self::lastRequest($harness)->getUri()->getQuery());
        static::assertSame(
            [
                ['Id', 'SIREN', 'NIC', 'Category', 'Status', 'Requested at', 'Updated at'],
                [Calls::ENROLMENT_ID, '000000000', '00001', 'RG', 'ENCOURS', '2026-01-02T03:04:05Z', '2026-01-03T03:04:05Z'],
            ],
            Output::rows($command->getDisplay()),
        );
        static::assertStringContainsString('Last page.', $command->getDisplay());
    }

    public function testListingHonoursTheOffsetTheLimitAndTheSortAndPointsToTheNextPage(): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:mi:enrolment:list');

        static::assertSame(0, $command->execute(['--offset' => '5', '--limit' => '1', '--sort' => 'siren']));

        static::assertSame('offset=5&limit=1&sort=siren', self::lastRequest($harness)->getUri()->getQuery());
        static::assertStringContainsString('Next page: --offset=6', $command->getDisplay());
        static::assertStringNotContainsString('Last page.', $command->getDisplay());
    }

    public function testAnEmptyPageIsTheLastPage(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_ENROLMENT_LIST, 200, '[]');
        $command = $harness->command('urssaf:mi:enrolment:list');

        static::assertSame(0, $command->execute([]));

        static::assertCount(1, Output::rows($command->getDisplay()));
        static::assertStringContainsString('Last page.', $command->getDisplay());
    }

    public function testAnAbsentValueIsADashAndUnknownCodesAreKeptRaw(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_ENROLMENT_LIST, 200, '[{"idEnrolement":"' . Calls::ENROLMENT_ID . '","categorie":"NEW","status":"WAITING"}]');
        $command = $harness->command('urssaf:mi:enrolment:list');

        static::assertSame(0, $command->execute([]));

        static::assertContains([Calls::ENROLMENT_ID, '-', '-', 'NEW', 'WAITING', '-', '-'], Output::rows($command->getDisplay()));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidPages(): iterable
    {
        yield 'negative offset' => [['--offset' => '-1'], 'The --offset option must be a non-negative integer.'];
        yield 'text limit' => [['--limit' => 'abc'], 'The --limit option must be a non-negative integer.'];
        yield 'decimal limit' => [['--limit' => '1.5'], 'The --limit option must be a non-negative integer.'];
        yield 'trailing newline' => [['--limit' => "5\n"], 'The --limit option must be a non-negative integer.'];
        yield 'leading text' => [['--offset' => 'a5'], 'The --offset option must be a non-negative integer.'];
        yield 'too long' => [['--offset' => '1234567890'], 'The --offset option must be a non-negative integer.'];
        yield 'zero limit' => [['--limit' => '0'], 'The limit must be at least 1.'];
        yield 'empty sort' => [['--sort' => ' '], 'The sort expression must not be empty.'];
    }

    /**
     * @param array<string, string> $input
     */
    #[DataProvider('invalidPages')]
    public function testAnInvalidPageIsAnInvalidUsageAndNothingIsSent(array $input, string $message): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:mi:enrolment:list');

        static::assertSame(2, $command->execute($input));

        static::assertStringContainsString($message, $command->getDisplay());
        static::assertSame([], $harness->client->getRequests());
    }

    public function testTheLargestOffsetIsAccepted(): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:mi:enrolment:list');

        static::assertSame(0, $command->execute(['--offset' => '123456789']));

        static::assertStringContainsString('offset=123456789', self::lastRequest($harness)->getUri()->getQuery());
    }

    public function testShowingAnEnrolmentPrintsItsFields(): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:mi:enrolment:show');

        static::assertSame(0, $command->execute(['id' => Calls::ENROLMENT_ID]));

        static::assertSame([
            ['Field', 'Value'],
            ['id', Calls::ENROLMENT_ID],
            ['siren', '000000000'],
            ['nic', '00001'],
            ['category', 'RG'],
            ['status', 'ENCOURS'],
            ['requested at', '2026-01-02T03:04:05Z'],
            ['updated at', '2026-01-03T03:04:05Z'],
        ], Output::rows($command->getDisplay()));
        static::assertSame('/tiersdecl/v1/mobilite/enrolements/' . Calls::ENROLMENT_ID, self::lastRequest($harness)->getUri()->getPath());
    }

    public function testAnInvalidEnrolmentIdIsAnInvalidUsage(): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:mi:enrolment:show');

        static::assertSame(2, $command->execute(['id' => 'not-a-uuid']));

        static::assertStringContainsString('The enrolment identifier does not match the expected format (UUID).', $command->getDisplay());
        static::assertStringNotContainsString('not-a-uuid', $command->getDisplay());
        static::assertSame([], $harness->client->getRequests());
    }

    public function testAnUnknownEnrolmentIsAFailureWithoutTheUrssafDetail(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_ENROLMENT_GET, 404, '{"code":"E404","message":"gone","description":"details of the dossier"}');
        $command = $harness->command('urssaf:mi:enrolment:show');

        static::assertSame(1, $command->execute(['id' => Calls::ENROLMENT_ID]));

        static::assertStringContainsString('get-enrolement', $command->getDisplay());
        static::assertStringNotContainsString('details of the dossier', $command->getDisplay());
        static::assertStringNotContainsString('E404', $command->getDisplay());
    }

    public function testTheUrssafDetailIsPrintedOnlyOnRequest(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_ENROLMENT_GET, 404, '{"code":"E404","message":"gone <b>","description":"details"}');
        $command = $harness->command('urssaf:mi:enrolment:show');

        static::assertSame(1, $command->execute(['id' => Calls::ENROLMENT_ID, '--show-urssaf-errors' => true]));

        static::assertContains(['URSSAF code', 'URSSAF message', 'URSSAF description'], Output::rows($command->getDisplay()));
        static::assertContains(['E404', 'gone <b>', 'details'], Output::rows($command->getDisplay()));
    }

    public function testAnEmptyUrssafDetailIsSaidSo(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_ENROLMENT_GET, 400, '');
        $command = $harness->command('urssaf:mi:enrolment:show');

        static::assertSame(1, $command->execute(['id' => Calls::ENROLMENT_ID, '--show-urssaf-errors' => true]));

        static::assertStringContainsString('URSSAF returned no error detail.', $command->getDisplay());
    }

    public function testAnUnauthorisedCallIsAFailureWhoseMessageNamesNoSecret(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{"error":"invalid_client"}');
        $command = $harness->command('urssaf:mi:enrolment:list');

        static::assertSame(1, $command->execute([]));

        static::assertStringNotContainsString('top-secret', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_ENROLMENT_LIST));
    }

    public function testAnUncertainEnrolmentRequestIsAFailure(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_ENROLMENT_REQUEST, 500, '');
        $command = $harness->command('urssaf:mi:enrolment:request');

        static::assertSame(1, $command->execute(['siren' => '000000000', '--category' => 'RG']));

        static::assertStringContainsString('post-enrolement', $command->getDisplay());
    }

    public function testDeletingWithForceDoesNotAsk(): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:mi:enrolment:delete');

        static::assertSame(0, $command->execute(['id' => Calls::ENROLMENT_ID, '--force' => true], ['interactive' => false]));

        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_ENROLMENT_DELETE));
        static::assertSame('DELETE', self::lastRequest($harness)->getMethod());
        static::assertStringContainsString('Enrolment ' . Calls::ENROLMENT_ID . ' deleted.', $command->getDisplay());
    }

    public function testDeletingAsksForAConfirmation(): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:mi:enrolment:delete');
        $command->setInputs(['yes']);

        static::assertSame(0, $command->execute(['id' => Calls::ENROLMENT_ID]));

        static::assertStringContainsString('Delete the enrolment ' . Calls::ENROLMENT_ID . '? [y/N]', $command->getDisplay());
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_ENROLMENT_DELETE));
    }

    public function testRefusingTheConfirmationDeletesNothing(): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:mi:enrolment:delete');
        $command->setInputs(['no']);

        static::assertSame(1, $command->execute(['id' => Calls::ENROLMENT_ID]));

        static::assertStringContainsString('Nothing deleted.', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_ENROLMENT_DELETE));
        static::assertSame([], $harness->client->getRequests());
    }

    public function testAnEmptyAnswerToTheConfirmationDeletesNothing(): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:mi:enrolment:delete');
        $command->setInputs(['']);

        static::assertSame(1, $command->execute(['id' => Calls::ENROLMENT_ID]));

        static::assertStringContainsString('Nothing deleted.', $command->getDisplay());
        static::assertSame([], $harness->client->getRequests());
    }

    public function testDeletingWithoutATerminalNeedsForce(): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:mi:enrolment:delete');

        static::assertSame(2, $command->execute(['id' => Calls::ENROLMENT_ID], ['interactive' => false]));

        static::assertStringContainsString('The deletion needs a confirmation: run the command interactively or use --force.', $command->getDisplay());
        static::assertSame([], $harness->client->getRequests());
    }

    public function testDeletingAnUnknownEnrolmentIsAFailure(): void
    {
        $harness = Harness::boot(['gateway' => ['simulated' => true]]);
        $command = $harness->command('urssaf:mi:enrolment:delete');

        static::assertSame(1, $command->execute(['id' => Calls::ENROLMENT_ID, '--force' => true]));

        static::assertStringContainsString('delete-enrolement', $command->getDisplay());
    }

    public function testAMissingTenantIsReportedInTheLanguageOfTheApplication(): void
    {
        $harness = Harness::boot();
        $translator = $harness->privateService('translator', LocaleAwareInterface::class);
        $translator->setLocale('fr');
        $command = $harness->command('urssaf:mi:enrolment:list');

        static::assertSame(1, $command->execute(['--tenant' => 'tenant-9']));

        static::assertStringContainsString("Aucun identifiant n'est configuré pour ce tenant.", $command->getDisplay());
    }

    public function testTheMessageOfAValueTheUserTypedIsTranslatedToo(): void
    {
        $harness = Harness::boot();
        $harness->privateService('translator', LocaleAwareInterface::class)->setLocale('fr');
        $command = $harness->command('urssaf:mi:enrolment:list');

        static::assertSame(2, $command->execute(['--tenant' => str_repeat('t', 65)]));

        static::assertStringContainsString("L'identifiant du tenant ne doit pas dépasser 64 caractères.", $command->getDisplay());
    }

    public function testNoCommandPrintsPersonalDataOrTheRequestOfAPosting(): void
    {
        $harness = Harness::boot(['gateway' => ['simulated' => true]]);
        $command = $harness->command('urssaf:mi:enrolment:request');
        $command->execute(['siren' => '000000000', '--category' => 'RG']);

        // The only identifiers printed are the public ones of the enrolment.
        static::assertSame(
            ['id', 'siren', 'nic', 'category', 'status', 'requested at', 'updated at'],
            array_column(\array_slice(Output::rows($command->getDisplay()), 1), 0),
        );
    }

    private static function lastRequest(Harness $harness): RequestInterface
    {
        $requests = $harness->client->getRequests();
        $last = end($requests);
        static::assertInstanceOf(RequestInterface::class, $last);

        return $last;
    }
}
