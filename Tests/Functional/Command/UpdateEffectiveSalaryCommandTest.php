<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Command;

use JWeiland\Jobboard\Command\UpdateEffectiveSalaryCommand;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * "now" is fixed to 2026-01-01 00:00:00 UTC (1767225600) by the date aspect
 * of the Context. All stored values of the fixture are outdated (1.00), so
 * every recalculated job changes.
 */
final class UpdateEffectiveSalaryCommandTest extends FunctionalTestCase
{
    private const NOW = 1767225600;

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/UpdateEffectiveSalaryCommand.csv');
        $this->get(Context::class)->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('@' . self::NOW)));

        // Set by the TYPO3 CLI application, the command only authenticates it
        $GLOBALS['BE_USER'] = GeneralUtility::makeInstance(CommandLineUserAuthentication::class);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $input
     */
    private function executeCommand(array $input = []): CommandTester
    {
        $commandTester = new CommandTester($this->get(UpdateEffectiveSalaryCommand::class));

        self::assertSame(Command::SUCCESS, $commandTester->execute($input));

        return $commandTester;
    }

    /**
     * @return int[]
     */
    private function findUpdatedJobUids(): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_jobboard_domain_model_job');
        $queryBuilder->getRestrictions()->removeAll();
        $uids = $queryBuilder
            ->select('uid')
            ->from('tx_jobboard_domain_model_job')
            ->where($queryBuilder->expr()->eq('effective_salary_min', 2500))
            ->orderBy('uid')
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map(intval(...), $uids);
    }

    #[Test]
    public function commandIsRegisteredWithName(): void
    {
        self::assertSame('jobboard:salary:update', $this->get(UpdateEffectiveSalaryCommand::class)->getName());
    }

    #[Test]
    public function executeRecalculatesDueJobsOnly(): void
    {
        // Due: change until the end of the look-ahead (2), passed change (4)
        // and hidden job (7). The translation (5) is updated with its job.
        $commandTester = $this->executeCommand();

        self::assertSame([2, 4, 5, 7], $this->findUpdatedJobUids());
        self::assertStringContainsString(
            'Checked 3 jobs, updated the effective salary of 3 jobs.',
            $commandTester->getDisplay(),
        );
    }

    #[Test]
    public function executeWithAllOptionRecalculatesAllJobs(): void
    {
        $commandTester = $this->executeCommand(['--all' => true]);

        self::assertSame([1, 2, 3, 4, 5, 7], $this->findUpdatedJobUids());
        self::assertStringContainsString(
            'Checked 5 jobs, updated the effective salary of 5 jobs.',
            $commandTester->getDisplay(),
        );
    }

    #[Test]
    public function executeTwiceUpdatesNothingTheSecondTime(): void
    {
        $this->executeCommand(['--all' => true]);

        $commandTester = $this->executeCommand(['--all' => true]);

        self::assertStringContainsString(
            'Checked 5 jobs, updated the effective salary of 0 jobs.',
            $commandTester->getDisplay(),
        );
    }

    #[Test]
    public function executeResetsValidUntilOfRecalculatedJobs(): void
    {
        $this->executeCommand();

        // Free entry salaries never change by time: no further due run
        $commandTester = $this->executeCommand();

        self::assertStringContainsString('Checked 0 jobs', $commandTester->getDisplay());
    }
}
