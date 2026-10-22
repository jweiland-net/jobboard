<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\ViewHelpers;

use JWeiland\Jobboard\Domain\Model\Address;
use JWeiland\Jobboard\Domain\Model\Job;
use JWeiland\Jobboard\Domain\Model\JobType;
use JWeiland\Jobboard\ViewHelpers\JobPoiCollectionViewHelper;
use JWeiland\Maps2\Domain\Model\PoiCollection;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Test case.
 */
final class JobPoiCollectionViewHelperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'friendsoftypo3/tt-address',
        'jweiland/maps2',
        'jweiland/jobboard',
    ];

    private function buildJobWithoutAddress(): Job
    {
        $job = new Job();
        $job->setTitle('Job without address');

        return $job;
    }

    private function buildJobWithAddressButWithoutPoiCollection(): Job
    {
        $job = new Job();
        $job->setTitle('Job without poi collection');
        $job->setAddress(new Address());

        return $job;
    }

    private function buildJobWithPoiCollectionOnly(): Job
    {
        $address = new Address();
        $address->setTxMaps2Uid(new PoiCollection());

        $job = new Job();
        $job->setTitle('Job without application deadline and job type');
        $job->setAddress($address);

        return $job;
    }

    private function buildJobWithPoiCollectionAndInfoWindowContent(): Job
    {
        $address = new Address();
        $address->setTxMaps2Uid(new PoiCollection());

        $jobType = new JobType();
        $jobType->setTitle('Vollzeit');

        $job = new Job();
        $job->setTitle('Job with application deadline and job type');
        $job->setAddress($address);
        $job->setApplicationDeadline(new \DateTime('2026-12-24'));
        // The employment end date must not be shown as "apply until" date
        $job->setEndingDate(new \DateTime('2027-06-30'));
        $job->setJobType($jobType);

        return $job;
    }

    #[Test]
    public function renderSkipsJobsWithoutAnAddressOrWithoutAPoiCollection(): void
    {
        $jobs = new ObjectStorage();
        $jobs->attach($this->buildJobWithoutAddress());
        $jobs->attach($this->buildJobWithAddressButWithoutPoiCollection());

        $context = $this->get(RenderingContextFactory::class)->create();
        $context->getTemplatePaths()->setTemplateSource(
            '{namespace jobboard=JWeiland\Jobboard\ViewHelpers}'
            . '{jobboard:jobPoiCollection(jobs: jobs) -> f:count()}',
        );
        $context->getVariableProvider()->add('jobs', $jobs);

        self::assertSame(0, (new TemplateView($context))->render());
    }

    #[Test]
    public function renderAttachesOnePoiCollectionPerJobWithAddressAndPoiCollection(): void
    {
        $jobs = new ObjectStorage();
        $jobs->attach($this->buildJobWithoutAddress());
        $jobs->attach($this->buildJobWithPoiCollectionOnly());
        $jobs->attach($this->buildJobWithPoiCollectionAndInfoWindowContent());

        $context = $this->get(RenderingContextFactory::class)->create();
        $context->getTemplatePaths()->setTemplateSource(
            '{namespace jobboard=JWeiland\Jobboard\ViewHelpers}'
            . '{jobboard:jobPoiCollection(jobs: jobs) -> f:count()}',
        );
        $context->getVariableProvider()->add('jobs', $jobs);

        self::assertSame(2, (new TemplateView($context))->render());
    }

    #[Test]
    public function renderSetsTitleOnEveryAttachedPoiCollection(): void
    {
        $subject = new JobPoiCollectionViewHelper();
        $subject->setArguments(['jobs' => [$this->buildJobWithPoiCollectionOnly()]]);

        $poiCollections = $subject->render();

        self::assertCount(1, $poiCollections);
        self::assertSame(
            'Job without application deadline and job type',
            $poiCollections->current()->getTitle(),
        );
    }

    #[Test]
    public function renderLeavesInfoWindowContentEmptyWithoutApplicationDeadlineAndJobType(): void
    {
        $subject = new JobPoiCollectionViewHelper();
        $subject->setArguments(['jobs' => [$this->buildJobWithPoiCollectionOnly()]]);

        $poiCollection = $subject->render()->current();

        self::assertSame('', $poiCollection->getInfoWindowContent());
    }

    #[Test]
    public function renderSetsInfoWindowContentWithApplicationDeadlineAndJobType(): void
    {
        $subject = new JobPoiCollectionViewHelper();
        $subject->setArguments(['jobs' => [$this->buildJobWithPoiCollectionAndInfoWindowContent()]]);

        $infoWindowContent = $subject->render()->current()->getInfoWindowContent();

        self::assertStringContainsString('24.12.2026', $infoWindowContent);
        self::assertStringContainsString('Vollzeit', $infoWindowContent);
        self::assertStringNotContainsString('30.06.2027', $infoWindowContent);
    }
}
