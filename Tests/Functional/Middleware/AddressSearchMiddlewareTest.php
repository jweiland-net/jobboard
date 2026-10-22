<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Middleware;

use JWeiland\Jobboard\Middleware\AddressSearchMiddleware;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * Covers AddressSearchMiddleware::getAvailableCities(): the search is
 * restricted to the pages listed in the "jobboard.storagePid" Site Setting
 * (including a comma-separated list of several pages), returns an empty
 * result instead of crashing when "zipCity" is not a string, and returns
 * an empty result when the request carries no resolved site.
 */
class AddressSearchMiddlewareTest extends FunctionalTestCase
{
    protected AddressSearchMiddleware $subject;

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/AddressSearchMiddleware.csv');

        $this->subject = $this->get(AddressSearchMiddleware::class);
    }

    protected function tearDown(): void
    {
        unset(
            $this->subject,
        );

        parent::tearDown();
    }

    private function buildRequest(string $zipCity, ?string $storagePid): ServerRequest
    {
        $request = (new ServerRequest())->withParsedBody(['zipCity' => $zipCity]);

        if ($storagePid === null) {
            return $request;
        }

        $site = new Site('jobboard-test', 1, [
            'settings' => [
                'jobboard' => [
                    'storagePid' => $storagePid,
                ],
            ],
        ]);

        return $request->withAttribute('site', $site);
    }

    #[Test]
    public function getAvailableCitiesOnlyReturnsAddressesFromConfiguredStoragePid(): void
    {
        self::assertSame(
            [
                '76133 - Pforzheim',
            ],
            $this->subject->getAvailableCities($this->buildRequest('Pforzheim', '2')),
        );
    }

    #[Test]
    public function getAvailableCitiesSupportsCommaSeparatedStoragePids(): void
    {
        self::assertEqualsCanonicalizing(
            [
                '76133 - Pforzheim',
                '76135 - Pforzheim-Nord',
            ],
            $this->subject->getAvailableCities($this->buildRequest('Pforzheim', '2,34')),
        );
    }

    #[Test]
    public function getAvailableCitiesReturnsEmptyArrayWhenZipCityIsNotAString(): void
    {
        $request = (new ServerRequest())
            ->withParsedBody(['zipCity' => ['not', 'a', 'string']])
            ->withAttribute('site', new Site('jobboard-test', 1, [
                'settings' => [
                    'jobboard' => [
                        'storagePid' => '2',
                    ],
                ],
            ]));

        self::assertSame(
            [],
            $this->subject->getAvailableCities($request),
        );
    }

    #[Test]
    public function getAvailableCitiesReturnsEmptyArrayWhenSiteAttributeIsMissing(): void
    {
        self::assertSame(
            [],
            $this->subject->getAvailableCities($this->buildRequest('Pforzheim', null)),
        );
    }
}
