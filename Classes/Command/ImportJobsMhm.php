<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Command;

use JWeiland\Jobboard\Configuration\ImportConfiguration;
use JWeiland\Jobboard\Service\ImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;

/**
 * Import jobs from the XML API endpoints of all registered API models
 * (services tagged with "api.model").
 * This command can be executed multiple times.
 * It will check for existing tt_address and job records and insert/update them accordingly.
 * It uses the TYPO3 DataHandler to store the records. That way it's not a problem to hook into DataHandler
 * to update solr records or anything else.
 */
#[AsCommand(
    name: 'jobboard:import:jobs:mhm',
    description: 'Import jobs from the XML API endpoints of all registered API models',
)] class ImportJobsMhm extends Command
{
    public function __construct(
        private readonly ImportService $importService,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addArgument(
            'storage',
            InputArgument::REQUIRED,
            'Storage folder (pid) to store the imported jobs',
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // Make sure the _cli_ user is loaded
        Bootstrap::initializeBackendAuthentication();

        $importConfiguration = new ImportConfiguration(
            (int)$input->getArgument('storage'),
        );

        $importResult = $this->importService->import($importConfiguration);
        $symfonyStyle = new SymfonyStyle($input, $output);
        if ($importResult->getNumberOfSkippedJobs() > 0) {
            $symfonyStyle->warning(sprintf(
                '%d job(s) skipped because of invalid data. See the log of jobboard.',
                $importResult->getNumberOfSkippedJobs(),
            ));
        }

        if (!$importResult->hasErrors()) {
            return Command::SUCCESS;
        }

        $symfonyStyle->error(sprintf(
            '%d job(s) could not be saved and %d API model(s) could not be fetched. See the log of jobboard.',
            $importResult->getNumberOfFailedJobs(),
            $importResult->getNumberOfFailedApiModels(),
        ));

        return Command::FAILURE;
    }
}
