<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Service;

use JWeiland\Jobboard\Service\ImportRecordValidator;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class ImportRecordValidatorTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    private function buildSubject(?LoggerInterface $logger = null): ImportRecordValidator
    {
        return new ImportRecordValidator(
            $this->get(TcaSchemaFactory::class),
            $logger ?? self::createStub(LoggerInterface::class),
        );
    }

    #[Test]
    public function emptyInvalidValuesKeepsValidValues(): void
    {
        $record = [
            'title' => 'Web Developer (m/f/d)',
            'email' => 'jobs@example.org',
            'link' => 'https://example.org/jobs/4711',
            'job_type' => 1,
            'unknown_field' => 'no TCA',
        ];

        self::assertSame(
            $record,
            $this->buildSubject()->emptyInvalidValues('tx_jobboard_domain_model_job', $record, '4711'),
        );
    }

    #[Test]
    public function emptyInvalidValuesEmptiesInvalidLinkAndLogsWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(self::stringContains('Invalid value "not a url" of field "link"'));

        $record = $this->buildSubject($logger)->emptyInvalidValues(
            'tx_jobboard_domain_model_job',
            ['title' => 'Web Developer (m/f/d)', 'link' => 'not a url'],
            '4711',
        );

        self::assertSame(['title' => 'Web Developer (m/f/d)', 'link' => ''], $record);
    }

    #[Test]
    public function emptyInvalidValuesEmptiesInvalidEmail(): void
    {
        $record = $this->buildSubject()->emptyInvalidValues(
            'tt_address',
            ['name' => 'Jane Doe', 'email' => 'jane.doe@'],
            '4711',
        );

        self::assertSame(['name' => 'Jane Doe', 'email' => ''], $record);
    }
}
