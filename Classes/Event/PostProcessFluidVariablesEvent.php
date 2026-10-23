<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Event;

use TYPO3\CMS\Extbase\Mvc\Request;

/**
 * Dispatched by the list and search action, right before the variables are
 * assigned to the view. Listeners can add or replace variables, e.g. to
 * paginate the jobs. The Fluid variable "jobs" is a QueryResultInterface of
 * Job objects, which is not executed yet, so it can be paginated (limited)
 * in the database.
 */
final class PostProcessFluidVariablesEvent
{
    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $fluidVariables
     */
    public function __construct(
        private readonly Request $request,
        private readonly array $settings,
        private array $fluidVariables,
    ) {}

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getControllerName(): string
    {
        return $this->request->getControllerName();
    }

    public function getActionName(): string
    {
        return $this->request->getControllerActionName();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFluidVariables(): array
    {
        return $this->fluidVariables;
    }

    public function addFluidVariable(string $key, mixed $value): void
    {
        $this->fluidVariables[$key] = $value;
    }
}
