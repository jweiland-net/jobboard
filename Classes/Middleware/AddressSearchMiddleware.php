<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobfair2\Middleware;

use Doctrine\DBAL\Driver\Exception;
use JWeiland\Jobfair2\Traits\ConnectionPoolTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;

class AddressSearchMiddleware implements MiddlewareInterface
{
    use ConnectionPoolTrait;

    private const HEADER_NAME = 'jobfair2-address-search';

    private const TABLE = 'tt_address';

    private const LIMIT = 5;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->hasHeader(self::HEADER_NAME)) {
            return new JsonResponse($this->getAvailableCities($request));
        }

        return $handler->handle($request);
    }

    public function getAvailableCities(ServerRequestInterface $request): array
    {
        $queryBuilder = $this->getQueryBuilderForTable(self::TABLE);

        $queryResult = $queryBuilder
            ->select('zip', 'city')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->like(
                    'zip',
                    $queryBuilder->createNamedParameter(
                        '%' . $queryBuilder->escapeLikeWildcards($request->getParsedBody()['zipCity']) . '%',
                    ),
                ),
            )
            ->orWhere(
                $queryBuilder->expr()->like(
                    'city',
                    $queryBuilder->createNamedParameter(
                        '%' . $queryBuilder->escapeLikeWildcards($request->getParsedBody()['zipCity']) . '%',
                    ),
                ),
            )
            ->groupBy('zip', 'city')
            ->orderBy('city', 'ASC')->setMaxResults(self::LIMIT)->executeQuery();

        $availableCities = [];
        try {
            while ($ttAddressRecord = $queryResult->fetchAssociative()) {
                $availableCities[] = $ttAddressRecord['zip'] . ' - ' . $ttAddressRecord['city'];
            }
        } catch (Exception) {
        }

        return $availableCities;
    }
}
