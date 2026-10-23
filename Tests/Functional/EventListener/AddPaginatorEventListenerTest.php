<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\EventListener;

use JWeiland\Jobboard\Domain\Model\Job;
use JWeiland\Jobboard\Domain\Repository\JobRepository;
use JWeiland\Jobboard\Event\PostProcessFluidVariablesEvent;
use JWeiland\Jobboard\EventListener\AddPaginatorEventListener;
use JWeiland\Jobboard\Pagination\GetPostPagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * Paginates a real JobRepository query result. Listed jobs of the fixture in
 * default order: 1, 2, 3, 4, 7, 8, 9, 10, 11 and 13.
 */
final class AddPaginatorEventListenerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    private AddPaginatorEventListener $subject;

    private JobRepository $jobRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobSearchResults.csv');

        $querySettings = $this->get(QuerySettingsInterface::class);
        $querySettings->setStoragePageIds([2]);
        $this->jobRepository = $this->get(JobRepository::class);
        $this->jobRepository->setDefaultQuerySettings($querySettings);

        $this->subject = new AddPaginatorEventListener();
    }

    protected function tearDown(): void
    {
        unset(
            $this->subject,
            $this->jobRepository,
        );

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $fluidVariables
     * @param array<string, mixed> $arguments
     */
    private function dispatch(
        array $settings,
        array $fluidVariables,
        array $arguments = [],
        string $actionName = 'list',
    ): PostProcessFluidVariablesEvent {
        $extbaseRequestParameters = new ExtbaseRequestParameters();
        $extbaseRequestParameters->setControllerName('Jobboard');
        $extbaseRequestParameters->setControllerActionName($actionName);
        $extbaseRequestParameters->setArguments($arguments);
        $request = new Request((new ServerRequest())->withAttribute('extbase', $extbaseRequestParameters));

        $event = new PostProcessFluidVariablesEvent($request, $settings, $fluidVariables);
        ($this->subject)($event);

        return $event;
    }

    /**
     * @return int[]
     */
    private function getJobUids(mixed $jobs): array
    {
        self::assertIsIterable($jobs);

        $uids = [];
        foreach ($jobs as $job) {
            self::assertInstanceOf(Job::class, $job);
            $uids[] = (int)$job->getUid();
        }

        return $uids;
    }

    /**
     * @return array<string, array{int, int[]}>
     */
    public static function currentPageDataProvider(): array
    {
        return [
            'first page' => [1, [1, 2, 3, 4]],
            'second page' => [2, [7, 8, 9, 10]],
            'last page with remaining jobs' => [3, [11, 13]],
        ];
    }

    /**
     * @param int[] $expectedJobUids
     */
    #[Test]
    #[DataProvider('currentPageDataProvider')]
    public function invokePaginatesQueryResultInDatabase(int $currentPage, array $expectedJobUids): void
    {
        $event = $this->dispatch(
            ['pageBrowser' => ['itemsPerPage' => '4']],
            ['jobs' => $this->jobRepository->findBySettings([])],
            ['currentPage' => (string)$currentPage],
        );
        $fluidVariables = $event->getFluidVariables();

        self::assertSame($expectedJobUids, $this->getJobUids($fluidVariables['jobs']));
        self::assertInstanceOf(QueryResultPaginator::class, $fluidVariables['paginator']);
        self::assertSame($currentPage, $fluidVariables['paginator']->getCurrentPageNumber());
        self::assertSame(3, $fluidVariables['paginator']->getNumberOfPages());
        self::assertInstanceOf(GetPostPagination::class, $fluidVariables['pagination']);
        self::assertSame(10, $fluidVariables['numberOfJobs']);
        self::assertSame('list', $fluidVariables['actionName']);
    }

    #[Test]
    public function invokeUsesFirstPageWithoutCurrentPageArgument(): void
    {
        $event = $this->dispatch(
            ['pageBrowser' => ['itemsPerPage' => '4']],
            ['jobs' => $this->jobRepository->findBySettings([])],
        );

        self::assertSame([1, 2, 3, 4], $this->getJobUids($event->getFluidVariables()['jobs']));
    }

    #[Test]
    public function invokeUsesFirstPageForInvalidCurrentPage(): void
    {
        $event = $this->dispatch(
            ['pageBrowser' => ['itemsPerPage' => '4']],
            ['jobs' => $this->jobRepository->findBySettings([])],
            ['currentPage' => '-3'],
        );

        self::assertSame([1, 2, 3, 4], $this->getJobUids($event->getFluidVariables()['jobs']));
    }

    #[Test]
    public function invokeUsesDefaultItemsPerPageForInvalidSetting(): void
    {
        $event = $this->dispatch(
            ['pageBrowser' => ['itemsPerPage' => '0']],
            ['jobs' => $this->jobRepository->findBySettings([])],
        );

        // 15 jobs per page: all 10 jobs on one page
        self::assertSame(1, $event->getFluidVariables()['paginator']->getNumberOfPages());
        self::assertCount(10, $this->getJobUids($event->getFluidVariables()['jobs']));
    }

    #[Test]
    public function invokePaginatesSearchAction(): void
    {
        $event = $this->dispatch(
            ['pageBrowser' => ['itemsPerPage' => '4']],
            ['jobs' => $this->jobRepository->findBySettings([])],
            actionName: 'search',
        );

        self::assertSame('search', $event->getFluidVariables()['actionName']);
        self::assertArrayHasKey('paginator', $event->getFluidVariables());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function notPaginatedDataProvider(): array
    {
        return [
            'limited list (maxEntries)' => [['maxEntries' => '2', 'pageBrowser' => ['itemsPerPage' => '1']], 'list'],
            'page browser disabled by site setting' => [['pageBrowser' => ['enable' => '0', 'itemsPerPage' => '1']], 'list'],
            'page browser hidden by content element' => [['pageBrowser' => ['enable' => '-1', 'itemsPerPage' => '1']], 'list'],
            'other action' => [['pageBrowser' => ['itemsPerPage' => '1']], 'rememberedJobs'],
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    #[Test]
    #[DataProvider('notPaginatedDataProvider')]
    public function invokeDoesNotPaginate(array $settings, string $actionName): void
    {
        $jobs = $this->jobRepository->findBySettings([], (int)($settings['maxEntries'] ?? 0));

        $event = $this->dispatch($settings, ['jobs' => $jobs], actionName: $actionName);

        self::assertSame(['jobs' => $jobs], $event->getFluidVariables());
    }

    #[Test]
    public function invokeDoesNotPaginateEmptyQueryResult(): void
    {
        $jobs = $this->jobRepository->findByUids([]);

        $event = $this->dispatch([], ['jobs' => $jobs]);

        self::assertSame(['jobs' => $jobs], $event->getFluidVariables());
    }

    #[Test]
    public function invokeIgnoresJobsWhichAreNoQueryResult(): void
    {
        $jobs = iterator_to_array($this->jobRepository->findBySettings([]));

        $event = $this->dispatch(['pageBrowser' => ['itemsPerPage' => '1']], ['jobs' => $jobs]);

        self::assertSame(['jobs' => $jobs], $event->getFluidVariables());
    }

    #[Test]
    public function invokeKeepsLimitOfQueryResult(): void
    {
        $jobs = $this->jobRepository->findBySettings([]);

        $this->dispatch(['pageBrowser' => ['itemsPerPage' => '4']], ['jobs' => $jobs], ['currentPage' => '2']);

        // The search options are built from the original query afterwards
        self::assertInstanceOf(QueryResultInterface::class, $jobs);
        self::assertNull($jobs->getQuery()->getLimit());
        self::assertSame(10, $jobs->count());
    }
}
