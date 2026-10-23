<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Command;

use JWeiland\Jobboard\Service\EffectiveSalaryJobFinder;
use JWeiland\Jobboard\Service\EffectiveSalaryUpdaterInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Bootstrap;

/**
 * Recalculates the effective salary of jobs, whose salary grades or steps
 * start or expire (expiry with a look-ahead of 24 hours). Meant to run once
 * a day, e.g. nightly via the TYPO3 Scheduler. Writes through DataHandler,
 * so the page caches are cleared ("TCEMAIN.clearCacheCmd").
 *
 * "--all" recalculates all jobs, e.g. once after installing this version.
 */
#[AsCommand(
    name: 'jobboard:salary:update',
    description: 'Recalculate the effective salary of jobs whose salary grades or steps start or expire'
        . ' within the next 24 hours. Run it once a day, e.g. nightly.',
)]
final class UpdateEffectiveSalaryCommand extends Command
{
    public function __construct(
        private readonly EffectiveSalaryUpdaterInterface $effectiveSalaryUpdater,
        private readonly EffectiveSalaryJobFinder $effectiveSalaryJobFinder,
        private readonly Context $context,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'all',
            null,
            InputOption::VALUE_NONE,
            'Recalculate all jobs, not only the due ones (initial/full recalculation)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // DataHandler needs a backend user: load the "_cli_" user
        Bootstrap::initializeBackendAuthentication();

        $jobUids = $input->getOption('all')
            ? $this->effectiveSalaryJobFinder->findAllJobUids()
            : $this->effectiveSalaryJobFinder->findDueJobUids(
                (int)$this->context->getPropertyFromAspect('date', 'timestamp'),
            );

        $numberOfChangedJobs = $this->effectiveSalaryUpdater->updateJobs($jobUids);

        $output->writeln(sprintf(
            'Checked %d jobs, updated the effective salary of %d jobs.',
            count($jobUids),
            $numberOfChangedJobs,
        ));

        return Command::SUCCESS;
    }
}
