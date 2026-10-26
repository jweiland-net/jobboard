<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Functional\Controller;

use JWeiland\Jobboard\Service\EffectiveSalaryUpdaterInterface;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 *
 * The image crop ("Bildausschnitt") an editor sets on a file reference of a
 * job or benefit must be applied to the image in the frontend. The fixture
 * references one 400x200 image three times with a different crop each.
 */
final class JobboardControllerImageCropTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'typo3/cms-core',
        'typo3/cms-frontend',
    ];

    protected array $testExtensionsToLoad = [
        'bithost-gmbh/pdfviewhelpers',
        'friendsoftypo3/tt-address',
        'jweiland/jobboard',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobVisibilityBySalaryInformation.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/JobImageCrop.csv');
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_jobboard_domain_model_job')
            ->update(
                'tx_jobboard_domain_model_job',
                ['employer_logo' => 1, 'header_logo' => 1, 'benefits' => 1],
                ['uid' => 1],
            );
        $this->createImage(400, 200);

        $GLOBALS['BE_USER'] = GeneralUtility::makeInstance(CommandLineUserAuthentication::class);
        $GLOBALS['BE_USER']->backendCheckLogin();
        $this->get(EffectiveSalaryUpdaterInterface::class)->updateJobs([1]);
        unset($GLOBALS['BE_USER']);

        $this->get(SiteWriter::class)->write('main', [
            'rootPageId' => 1,
            'base' => 'https://example.com/',
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'English',
                    'navigationTitle' => 'English',
                    'base' => '/',
                    'locale' => 'en_US.UTF-8',
                    'flag' => 'global',
                ],
            ],
        ]);

        $this->setUpFrontendRootPage(
            1,
            [],
            [
                'config' => 'page = PAGE' . LF
                    . 'page.10 = CONTENT' . LF
                    . 'page.10.table = tt_content',
            ],
        );
        $this->addTypoScriptToTemplateRecord(1, '
            tt_content.jobboard_jobboard = EXTBASEPLUGIN
            tt_content.jobboard_jobboard {
                extensionName = Jobboard
                pluginName = Jobboard
            }
            plugin.tx_jobboard.view {
                templateRootPaths.0 = EXT:jobboard/Resources/Private/Templates/
                partialRootPaths.0 = EXT:jobboard/Resources/Private/Partials/
                layoutRootPaths.0 = EXT:jobboard/Resources/Private/Layouts/
            }
            plugin.tx_jobboard.persistence.storagePid = 2
        ');
    }

    private function createImage(int $width, int $height): void
    {
        $directory = $this->instancePath . '/fileadmin/jobboard';
        GeneralUtility::mkdir_deep($directory);
        $image = imagecreatetruecolor($width, $height);
        imagepng($image, $directory . '/image.png');
    }

    private function fetchDetailPageBody(int $jobUid): string
    {
        $queryString = '&id=1'
            . '&tx_jobboard_jobboard[controller]=Jobboard'
            . '&tx_jobboard_jobboard[action]=detail'
            . '&tx_jobboard_jobboard[job]=' . $jobUid;
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters($queryString);

        $request = (new InternalRequest('https://example.com/'))
            ->withPageId(1)
            ->withQueryParameter('tx_jobboard_jobboard[controller]', 'Jobboard')
            ->withQueryParameter('tx_jobboard_jobboard[action]', 'detail')
            ->withQueryParameter('tx_jobboard_jobboard[job]', $jobUid)
            ->withQueryParameter('cHash', $cHash);

        return (string)$this->executeFrontendSubRequest($request)->getBody();
    }

    /**
     * @return array<string, string>
     */
    private function getImageTagByAlt(string $body, string $alt): array
    {
        $document = new \DOMDocument();
        $previousUseErrors = libxml_use_internal_errors(true);
        $document->loadHTML($body);
        libxml_use_internal_errors($previousUseErrors);
        foreach ($document->getElementsByTagName('img') as $image) {
            if ($image->getAttribute('alt') === $alt) {
                return [
                    'src' => $image->getAttribute('src'),
                    'width' => $image->getAttribute('width'),
                    'height' => $image->getAttribute('height'),
                ];
            }
        }

        self::fail('No image with alt text "' . $alt . '" found');
    }

    #[Test]
    public function detailActionRendersEmployerLogoWithCrop(): void
    {
        $image = $this->getImageTagByAlt($this->fetchDetailPageBody(1), 'Example Employer Ltd.');

        self::assertSame(['200', '200'], [$image['width'], $image['height']]);
        self::assertStringContainsString('/_processed_/', $image['src']);
    }

    #[Test]
    public function detailActionRendersHeaderLogoWithCrop(): void
    {
        $image = $this->getImageTagByAlt($this->fetchDetailPageBody(1), 'Job with visible stepped grade');

        self::assertSame(['400', '100'], [$image['width'], $image['height']]);
        self::assertStringContainsString('/_processed_/', $image['src']);
    }

    #[Test]
    public function detailActionRendersBenefitImageWithCrop(): void
    {
        $image = $this->getImageTagByAlt($this->fetchDetailPageBody(1), 'Benefit with cropped image');

        self::assertSame(['100', '100'], [$image['width'], $image['height']]);
        self::assertStringContainsString('/_processed_/', $image['src']);
    }

    #[Test]
    public function detailActionRendersCroppedEmployerLogoInStructuredData(): void
    {
        $body = $this->fetchDetailPageBody(1);
        $image = $this->getImageTagByAlt($body, 'Example Employer Ltd.');

        // f:format.json escapes slashes, so only the unique file name is compared
        self::assertMatchesRegularExpression(
            '/"logo": "https?:[^"]+' . preg_quote(basename($image['src']), '/') . '"/',
            $body,
        );
    }
}
