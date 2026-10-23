<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\EventListener;

use JWeiland\Jobboard\Event\PostProcessFluidVariablesEvent;
use JWeiland\Jobboard\Pagination\GetPostPagination;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\PaginationInterface;
use TYPO3\CMS\Core\Pagination\PaginatorInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Paginates the jobs of the list and search action. Adds the Fluid variables
 * "paginator", "pagination", "numberOfJobs" and "actionName" and replaces
 * "jobs" with the jobs of the current page.
 */
#[AsEventListener('jobboard/addPaginator')]
final readonly class AddPaginatorEventListener
{
    private const ITEMS_PER_PAGE = 15;

    /**
     * Fluid variable name of the jobs to paginate
     */
    private const FLUID_VARIABLE_NAME = 'jobs';

    private const FALLBACK_CLASS = GetPostPagination::class;

    private const ALLOWED_CONTROLLER_ACTIONS = [
        'Jobboard' => [
            'list',
            'search',
        ],
    ];

    public function __invoke(PostProcessFluidVariablesEvent $event): void
    {
        $jobs = $event->getFluidVariables()[self::FLUID_VARIABLE_NAME] ?? null;
        if (!$this->isValidRequest($event) || !is_array($jobs) || $jobs === []) {
            return;
        }

        $paginator = new ArrayPaginator($jobs, $this->getCurrentPage($event), $this->getItemsPerPage($event));

        $event->addFluidVariable('actionName', $event->getActionName());
        $event->addFluidVariable('numberOfJobs', count($jobs));
        $event->addFluidVariable('paginator', $paginator);
        $event->addFluidVariable(self::FLUID_VARIABLE_NAME, $paginator->getPaginatedItems());
        $event->addFluidVariable('pagination', $this->getPagination($event, $paginator));
    }

    private function isValidRequest(PostProcessFluidVariablesEvent $event): bool
    {
        return in_array(
            $event->getActionName(),
            self::ALLOWED_CONTROLLER_ACTIONS[$event->getControllerName()] ?? [],
            true,
        );
    }

    private function getCurrentPage(PostProcessFluidVariablesEvent $event): int
    {
        if (!$event->getRequest()->hasArgument('currentPage')) {
            return 1;
        }

        // $currentPage has to be greater than 0, see AbstractPaginator::setCurrentPageNumber()
        return MathUtility::forceIntegerInRange((int)$event->getRequest()->getArgument('currentPage'), 1);
    }

    private function getItemsPerPage(PostProcessFluidVariablesEvent $event): int
    {
        $itemsPerPage = (int)($event->getSettings()['pageBrowser']['itemsPerPage'] ?? self::ITEMS_PER_PAGE);

        return $itemsPerPage > 0 ? $itemsPerPage : self::ITEMS_PER_PAGE;
    }

    private function getPagination(
        PostProcessFluidVariablesEvent $event,
        PaginatorInterface $paginator,
    ): PaginationInterface {
        $paginationClass = $event->getSettings()['pageBrowser']['class'] ?? self::FALLBACK_CLASS;

        if (!is_string($paginationClass) || !is_subclass_of($paginationClass, PaginationInterface::class)) {
            $paginationClass = self::FALLBACK_CLASS;
        }

        return GeneralUtility::makeInstance($paginationClass, $paginator, $event->getRequest()->getArguments());
    }
}
