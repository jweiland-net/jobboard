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
use JWeiland\Jobboard\ViewHelpers\JobPoiCollectionViewHelper;
use JWeiland\Maps2\Domain\Model\PoiCollection;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
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
        'bithost-gmbh/pdfviewhelpers',
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

    private function buildJobWithAddress(Address $address, string $title): Job
    {
        $job = new Job();
        $job->setTitle($title);
        $job->setAddress($address);

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
    public function renderAttachesOnePoiCollectionPerAddressWithPoiCollection(): void
    {
        $jobs = new ObjectStorage();
        $jobs->attach($this->buildJobWithoutAddress());
        $jobs->attach($this->buildJobWithPoiCollectionOnly());
        $jobs->attach($this->buildJobWithPoiCollectionOnly());

        $context = $this->get(RenderingContextFactory::class)->create();
        $context->getTemplatePaths()->setTemplateSource(
            '{namespace jobboard=JWeiland\Jobboard\ViewHelpers}'
            . '{jobboard:jobPoiCollection(jobs: jobs) -> f:count()}',
        );
        $context->getVariableProvider()->add('jobs', $jobs);

        self::assertSame(2, (new TemplateView($context))->render());
    }

    #[Test]
    public function renderAttachesAPoiCollectionSharedBySeveralJobsOnlyOnce(): void
    {
        $address = new Address();
        $address->setTxMaps2Uid(new PoiCollection());

        $subject = new JobPoiCollectionViewHelper();
        $subject->setArguments(['jobs' => [
            $this->buildJobWithAddress($address, 'First job'),
            $this->buildJobWithAddress($address, 'Second job'),
        ]]);

        self::assertCount(1, $subject->render());
    }

    #[Test]
    public function renderAttachesPoiCollectionsWithTheSameUidOnlyOnce(): void
    {
        $firstPoiCollection = new PoiCollection();
        $firstPoiCollection->_setProperty('uid', 12);
        $firstAddress = new Address();
        $firstAddress->setTxMaps2Uid($firstPoiCollection);

        $secondPoiCollection = new PoiCollection();
        $secondPoiCollection->_setProperty('uid', 12);
        $secondAddress = new Address();
        $secondAddress->setTxMaps2Uid($secondPoiCollection);

        $subject = new JobPoiCollectionViewHelper();
        $subject->setArguments(['jobs' => [
            $this->buildJobWithAddress($firstAddress, 'First job'),
            $this->buildJobWithAddress($secondAddress, 'Second job'),
        ]]);

        $poiCollections = $subject->render();

        self::assertCount(1, $poiCollections);
        self::assertSame($firstPoiCollection, $poiCollections->current());
    }

    #[Test]
    public function renderDoesNotChangeTitleAndInfoWindowContentOfPoiCollection(): void
    {
        $poiCollection = new PoiCollection();
        $poiCollection->setTitle('Stuttgart City Hall');
        $address = new Address();
        $address->setTxMaps2Uid($poiCollection);

        $subject = new JobPoiCollectionViewHelper();
        $subject->setArguments(['jobs' => [$this->buildJobWithAddress($address, 'Job title')]]);

        $subject->render();

        self::assertSame('Stuttgart City Hall', $poiCollection->getTitle());
        self::assertSame('', $poiCollection->getInfoWindowContent());
    }

    /**
     * Default address 1 references PoiCollection 5, its translation 2 has
     * no relation yet (EXT:maps2 writes it into the default address only).
     */
    private function insertAddressWithPoiCollection(): void
    {
        $connectionPool = $this->get(ConnectionPool::class);
        $connectionPool->getConnectionForTable('tx_maps2_domain_model_poicollection')->insert(
            'tx_maps2_domain_model_poicollection',
            ['uid' => 5, 'pid' => 2, 'title' => 'Example Employer Ltd.', 'collection_type' => 'Point'],
        );
        $addressConnection = $connectionPool->getConnectionForTable('tt_address');
        $addressConnection->insert('tt_address', [
            'uid' => 1,
            'pid' => 2,
            'company' => 'Example Employer Ltd.',
            'tx_maps2_uid' => 5,
        ]);
        $addressConnection->insert('tt_address', [
            'uid' => 2,
            'pid' => 2,
            'sys_language_uid' => 1,
            'l10n_parent' => 1,
            'company' => 'Beispiel Arbeitgeber GmbH',
            'tx_maps2_uid' => 0,
        ]);
    }

    /**
     * Like Extbase maps a translated address: UID of the default language,
     * localized UID of the translation.
     */
    private function buildAddress(int $uid, int $localizedUid, ?PoiCollection $poiCollection = null): Address
    {
        $address = new Address();
        $address->_setProperty('uid', $uid);
        $address->_setProperty(AbstractDomainObject::PROPERTY_LOCALIZED_UID, $localizedUid);
        if ($poiCollection !== null) {
            $address->setTxMaps2Uid($poiCollection);
        }

        return $address;
    }

    #[Test]
    public function renderFallsBackToPoiCollectionOfDefaultAddressForTranslatedAddressWithoutRelation(): void
    {
        $this->insertAddressWithPoiCollection();

        $subject = new JobPoiCollectionViewHelper();
        $subject->setArguments(['jobs' => [$this->buildJobWithAddress($this->buildAddress(1, 2), 'Translated job')]]);

        $poiCollections = $subject->render();

        self::assertCount(1, $poiCollections);
        $poiCollections->rewind();
        self::assertInstanceOf(PoiCollection::class, $poiCollections->current());
        self::assertSame(5, $poiCollections->current()->getUid());
    }

    #[Test]
    public function renderAttachesFallbackPoiCollectionOnlyOnceForSeveralTranslatedJobs(): void
    {
        $this->insertAddressWithPoiCollection();

        $subject = new JobPoiCollectionViewHelper();
        $subject->setArguments(['jobs' => [
            $this->buildJobWithAddress($this->buildAddress(1, 2), 'First translated job'),
            $this->buildJobWithAddress($this->buildAddress(1, 2), 'Second translated job'),
        ]]);

        self::assertCount(1, $subject->render());
    }

    #[Test]
    public function renderAttachesFallbackPoiCollectionAndPoiCollectionOfDefaultAddressOnlyOnce(): void
    {
        $this->insertAddressWithPoiCollection();
        $poiCollection = new PoiCollection();
        $poiCollection->_setProperty('uid', 5);

        $subject = new JobPoiCollectionViewHelper();
        $subject->setArguments(['jobs' => [
            $this->buildJobWithAddress($this->buildAddress(1, 1, $poiCollection), 'Default job'),
            $this->buildJobWithAddress($this->buildAddress(1, 2), 'Translated job'),
        ]]);

        $poiCollections = $subject->render();

        self::assertCount(1, $poiCollections);
        $poiCollections->rewind();
        self::assertSame($poiCollection, $poiCollections->current());
    }

    #[Test]
    public function renderPrefersPoiCollectionOfTranslatedAddress(): void
    {
        $this->insertAddressWithPoiCollection();
        $poiCollection = new PoiCollection();
        $poiCollection->_setProperty('uid', 9);

        $subject = new JobPoiCollectionViewHelper();
        $subject->setArguments(['jobs' => [
            $this->buildJobWithAddress($this->buildAddress(1, 2, $poiCollection), 'Translated job'),
        ]]);

        $poiCollections = $subject->render();
        $poiCollections->rewind();

        self::assertSame($poiCollection, $poiCollections->current());
    }

    #[Test]
    public function renderDoesNotLookUpPoiCollectionForDefaultAddressWithoutRelation(): void
    {
        $this->insertAddressWithPoiCollection();

        $subject = new JobPoiCollectionViewHelper();
        // Default address (localized UID = UID) without loaded relation
        $subject->setArguments(['jobs' => [$this->buildJobWithAddress($this->buildAddress(1, 1), 'Default job')]]);

        self::assertCount(0, $subject->render());
    }

    #[Test]
    public function renderSkipsTranslatedAddressIfDefaultAddressHasNoPoiCollection(): void
    {
        $this->insertAddressWithPoiCollection();
        $this->get(ConnectionPool::class)->getConnectionForTable('tt_address')
            ->update('tt_address', ['tx_maps2_uid' => 0], ['uid' => 1]);

        $subject = new JobPoiCollectionViewHelper();
        $subject->setArguments(['jobs' => [$this->buildJobWithAddress($this->buildAddress(1, 2), 'Translated job')]]);

        self::assertCount(0, $subject->render());
    }

    #[Test]
    public function renderDoesNotChangeTitleOfFallbackPoiCollection(): void
    {
        $this->insertAddressWithPoiCollection();

        $subject = new JobPoiCollectionViewHelper();
        $subject->setArguments(['jobs' => [$this->buildJobWithAddress($this->buildAddress(1, 2), 'Translated job')]]);

        $poiCollections = $subject->render();
        $poiCollections->rewind();
        $poiCollection = $poiCollections->current();

        self::assertInstanceOf(PoiCollection::class, $poiCollection);
        self::assertSame('Example Employer Ltd.', $poiCollection->getTitle());
        self::assertSame('', $poiCollection->getInfoWindowContent());
    }

    #[Test]
    public function extensionLoadedViewHelperReturnsTrueForMaps2(): void
    {
        $context = $this->get(RenderingContextFactory::class)->create();
        $context->getTemplatePaths()->setTemplateSource(
            '{namespace jobboard=JWeiland\Jobboard\ViewHelpers}'
            . '<f:if condition="{jobboard:extensionLoaded(extensionKey: \'maps2\')}" then="loaded" else="not loaded"/>',
        );

        self::assertSame('loaded', (string)(new TemplateView($context))->render());
    }
}
