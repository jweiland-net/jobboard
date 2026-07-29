<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobfair2\Command;

use JWeiland\Jobfair2\Configuration\ImportConfiguration;
use JWeiland\Jobfair2\Service\ImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Bootstrap;

/**
 * Import jobs from XML API point of www.mhm-hr.com
 * This command can be executed multiple times.
 * It will check for existing tt_address and job records and insert/update them accordingly.
 * It uses the TYPO3 DataHandler to store the records. That way it's not a problem to hook into DataHandler
 * to update solr records or anything else.
 */
#[AsCommand(
    name: 'jobfair:import:jobs:mhm',
    description: 'Import jobs from XML API point of www.mhm-hr.com',
)] class ImportJobsMhm extends Command
{
    public function __construct(
        private readonly ImportService $importService,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->setDescription('Import jobs from XML API endpoint of MHM HR');
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

        $this->importService->import($importConfiguration);

        return 0;
    }
}
