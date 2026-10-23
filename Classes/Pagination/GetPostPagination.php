<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Pagination;

use TYPO3\CMS\Core\Pagination\PaginationInterface;
use TYPO3\CMS\Core\Pagination\PaginatorInterface;

/**
 * Pagination, which keeps the arguments of the search form (GET and POST)
 * in the page links. Otherwise, the filter of the search would get lost
 * while navigating through the result pages.
 */
final readonly class GetPostPagination implements PaginationInterface
{
    /**
     * Internal arguments of Extbase, which must not be part of a page link
     */
    private const EXCLUDED_ARGUMENTS = ['controller', 'action', 'format', 'currentPage'];

    /**
     * @var array<string, mixed>
     */
    private array $arguments;

    /**
     * @param array<string, mixed> $arguments Plugin arguments of the current request (GET merged with POST)
     */
    public function __construct(
        private PaginatorInterface $paginator,
        array $arguments = [],
    ) {
        $this->arguments = array_filter(
            $arguments,
            static fn(string $argumentName): bool => !str_starts_with($argumentName, '__')
                && !in_array($argumentName, self::EXCLUDED_ARGUMENTS, true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function getPreviousPageNumber(): ?int
    {
        $previousPage = $this->paginator->getCurrentPageNumber() - 1;

        if ($previousPage > $this->paginator->getNumberOfPages()) {
            return null;
        }

        return $previousPage >= $this->getFirstPageNumber() ? $previousPage : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPreviousPageArguments(): array
    {
        return $this->getArgumentsForPage($this->getPreviousPageNumber());
    }

    public function getNextPageNumber(): ?int
    {
        $nextPage = $this->paginator->getCurrentPageNumber() + 1;

        return $nextPage <= $this->paginator->getNumberOfPages() ? $nextPage : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getNextPageArguments(): array
    {
        return $this->getArgumentsForPage($this->getNextPageNumber());
    }

    public function getFirstPageNumber(): int
    {
        return 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFirstPageArguments(): array
    {
        return $this->getArgumentsForPage($this->getFirstPageNumber());
    }

    public function getLastPageNumber(): int
    {
        return $this->paginator->getNumberOfPages();
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastPageArguments(): array
    {
        return $this->getArgumentsForPage($this->getLastPageNumber());
    }

    public function getStartRecordNumber(): int
    {
        if ($this->paginator->getCurrentPageNumber() > $this->paginator->getNumberOfPages()) {
            return 0;
        }

        return $this->paginator->getKeyOfFirstPaginatedItem() + 1;
    }

    public function getEndRecordNumber(): int
    {
        if ($this->paginator->getCurrentPageNumber() > $this->paginator->getNumberOfPages()) {
            return 0;
        }

        return $this->paginator->getKeyOfLastPaginatedItem() + 1;
    }

    /**
     * @return int[]
     */
    public function getAllPageNumbers(): array
    {
        return range($this->getFirstPageNumber(), $this->getLastPageNumber());
    }

    /**
     * @return array<string, mixed>
     */
    private function getArgumentsForPage(?int $pageNumber): array
    {
        $arguments = $this->arguments;
        $arguments['currentPage'] = $pageNumber;

        return $arguments;
    }
}
