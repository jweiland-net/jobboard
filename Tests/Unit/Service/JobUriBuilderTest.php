<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\Service;

use JWeiland\Jobboard\Service\JobUriBuilder;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 *
 * Only the cases without a site router, the URIs themselves are covered by
 * RenderJobLocationInfoWindowContentEventListenerTest (functional).
 */
final class JobUriBuilderTest extends UnitTestCase
{
    #[Test]
    public function buildDetailUriWithoutSiteReturnsEmptyString(): void
    {
        $request = (new ServerRequest('https://example.com/'))
            ->withAttribute('routing', new PageArguments(5, '0', []));

        self::assertSame('', (new JobUriBuilder())->buildDetailUri(1, $request));
    }

    #[Test]
    public function buildDetailUriWithNullSiteReturnsEmptyString(): void
    {
        $request = (new ServerRequest('https://example.com/'))
            ->withAttribute('site', new NullSite())
            ->withAttribute('routing', new PageArguments(5, '0', []));

        self::assertSame('', (new JobUriBuilder())->buildDetailUri(1, $request));
    }
}
