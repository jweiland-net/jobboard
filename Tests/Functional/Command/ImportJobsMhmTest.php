<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Command;

use JWeiland\Jobboard\Command\ImportJobsMhm;
use JWeiland\Jobboard\Configuration\ImportConfiguration;
use JWeiland\Jobboard\Service\ImportService;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class ImportJobsMhmTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    #[Test]
    public function executeCallsImportServiceWithConfiguredStorageAndReturnsSuccess(): void
    {
        $GLOBALS['BE_USER'] = GeneralUtility::makeInstance(CommandLineUserAuthentication::class);

        $importService = $this->createMock(ImportService::class);
        $importService->expects($this->once())
            ->method('import')
            ->with(self::callback(static fn(ImportConfiguration $configuration): bool => $configuration->getStorage() === 5))
            ->willReturn(true);

        $commandTester = new CommandTester(new ImportJobsMhm($importService));
        $exitCode = $commandTester->execute(['storage' => '5']);

        self::assertSame(0, $exitCode);
    }
}
