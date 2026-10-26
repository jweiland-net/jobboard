<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Service;

use JWeiland\Jobboard\Service\EffectiveSalaryJobFinder;
use JWeiland\Jobboard\Tests\Functional\Traits\RemoveTcaCapabilityTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * Language handling of the job finder with and without language capability
 * of the job table. Fixture JobLanguageConstraint.csv: job 1 translated (11),
 * translations 13, 14, 17, 19 (language 1), 28 (language 2), floating
 * translation 15, 6 in all languages.
 */
final class EffectiveSalaryJobFinderTest extends FunctionalTestCase
{
    use RemoveTcaCapabilityTrait;

    private const TABLE_JOB = 'tx_jobboard_domain_model_job';

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobLanguageConstraint.csv');
    }

    protected function tearDown(): void
    {
        $this->restoreTca();

        parent::tearDown();
    }

    private function getSubject(): EffectiveSalaryJobFinder
    {
        return $this->get(EffectiveSalaryJobFinder::class);
    }

    /**
     * @param int[] $uids
     * @return int[]
     */
    private function sorted(array $uids): array
    {
        sort($uids);

        return $uids;
    }

    #[Test]
    public function findAllJobUidsReturnsJobsOfDefaultLanguageAndAllLanguages(): void
    {
        self::assertSame([1, 2, 3, 4, 6, 7, 8, 9], $this->sorted($this->getSubject()->findAllJobUids()));
    }

    #[Test]
    public function findAllJobUidsWithoutLanguageCapabilityReturnsAllJobs(): void
    {
        $this->removeLanguageCapability(self::TABLE_JOB);

        self::assertSame(
            [1, 2, 3, 4, 6, 7, 8, 9, 11, 13, 14, 15, 17, 19, 28],
            $this->sorted($this->getSubject()->findAllJobUids()),
        );
    }

    #[Test]
    public function findDefaultLanguageJobUidsMapsTranslationsToDefaultJob(): void
    {
        self::assertSame([1, 2], $this->sorted($this->getSubject()->findDefaultLanguageJobUids([11, 2])));
    }

    #[Test]
    public function findDefaultLanguageJobUidsWithoutLanguageCapabilityKeepsUids(): void
    {
        $this->removeLanguageCapability(self::TABLE_JOB);

        self::assertSame([2, 11], $this->sorted($this->getSubject()->findDefaultLanguageJobUids([11, 2])));
    }

    #[Test]
    public function findStoredEffectiveSalariesIncludesTranslations(): void
    {
        $storedEffectiveSalaries = $this->getSubject()->findStoredEffectiveSalaries([1]);

        self::assertSame([1], array_keys($storedEffectiveSalaries));
        self::assertCount(2, $storedEffectiveSalaries[1]);
    }

    #[Test]
    public function findStoredEffectiveSalariesWithoutLanguageCapabilityMatchesUidOnly(): void
    {
        $this->removeLanguageCapability(self::TABLE_JOB);

        $storedEffectiveSalaries = $this->getSubject()->findStoredEffectiveSalaries([1]);

        self::assertSame([1], array_keys($storedEffectiveSalaries));
        self::assertCount(1, $storedEffectiveSalaries[1]);
    }
}
