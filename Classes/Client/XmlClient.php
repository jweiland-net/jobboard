<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobfair2\Client;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class XmlClient
{
    public function __construct(protected RequestFactory $requestFactory) {}

    public function sendRequest(string $apiEndpoint): ?ResponseInterface
    {
        if (!GeneralUtility::isValidUrl($apiEndpoint)) {
            return null;
        }

        return $this->requestFactory->request($apiEndpoint);
    }
}
