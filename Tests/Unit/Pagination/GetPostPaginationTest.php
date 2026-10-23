<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Pagination;

use JWeiland\Jobboard\Pagination\GetPostPagination;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class GetPostPaginationTest extends UnitTestCase
{
    private function getPagination(int $currentPage, array $arguments = []): GetPostPagination
    {
        return new GetPostPagination(new ArrayPaginator(range(1, 5), $currentPage, 2), $arguments);
    }

    #[Test]
    public function getPreviousPageNumberOnFirstPageReturnsNull(): void
    {
        self::assertNull($this->getPagination(1)->getPreviousPageNumber());
    }

    #[Test]
    public function getPreviousPageNumberReturnsPreviousPage(): void
    {
        self::assertSame(1, $this->getPagination(2)->getPreviousPageNumber());
    }

    #[Test]
    public function getNextPageNumberOnLastPageReturnsNull(): void
    {
        self::assertNull($this->getPagination(3)->getNextPageNumber());
    }

    #[Test]
    public function getNextPageNumberReturnsNextPage(): void
    {
        self::assertSame(3, $this->getPagination(2)->getNextPageNumber());
    }

    #[Test]
    public function getLastPageNumberReturnsNumberOfPages(): void
    {
        self::assertSame(3, $this->getPagination(1)->getLastPageNumber());
    }

    #[Test]
    public function getStartAndEndRecordNumberReturnsRangeOfCurrentPage(): void
    {
        $pagination = $this->getPagination(2);

        self::assertSame(3, $pagination->getStartRecordNumber());
        self::assertSame(4, $pagination->getEndRecordNumber());
    }

    #[Test]
    public function getAllPageNumbersReturnsAllPages(): void
    {
        self::assertSame([1, 2, 3], $this->getPagination(1)->getAllPageNumbers());
    }

    #[Test]
    public function getNextPageArgumentsKeepsSearchArguments(): void
    {
        $pagination = $this->getPagination(1, ['jobArea' => '4', 'searchWord' => 'IT']);

        self::assertSame(
            ['jobArea' => '4', 'searchWord' => 'IT', 'currentPage' => 2],
            $pagination->getNextPageArguments(),
        );
    }

    #[Test]
    public function getFirstPageArgumentsRemovesInternalArguments(): void
    {
        $pagination = $this->getPagination(2, [
            '__referrer' => ['@action' => 'list'],
            '__trustedProperties' => 'abc',
            'controller' => 'Jobboard',
            'action' => 'search',
            'currentPage' => '2',
            'jobType' => '1',
        ]);

        self::assertSame(['jobType' => '1', 'currentPage' => 1], $pagination->getFirstPageArguments());
    }
}
