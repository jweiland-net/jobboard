<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Client;

use JWeiland\Jobboard\Client\XmlClient;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
final class XmlClientTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    #[Test]
    public function sendRequestWithInvalidUrlReturnsNullWithoutCallingRequestFactory(): void
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects($this->never())->method('request');

        $subject = new XmlClient($requestFactory);

        self::assertNull($subject->sendRequest('not-a-url'));
    }

    #[Test]
    public function sendRequestWithValidUrlReturnsResponseFromRequestFactory(): void
    {
        $response = new Response();

        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects($this->once())
            ->method('request')
            ->with('https://example.org/api/jobs.xml')
            ->willReturn($response);

        $subject = new XmlClient($requestFactory);

        self::assertSame($response, $subject->sendRequest('https://example.org/api/jobs.xml'));
    }
}
