<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Service;

use JWeiland\Jobboard\Database\Query\TcaControlFields;
use JWeiland\Jobboard\Traits\ConnectionPoolTrait;
use JWeiland\Jobboard\Traits\DataHandlerTrait;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Resolves records of simple lookup tables with a "title" column (job areas,
 * job types) by their title, as delivered by the XML API. Records are matched
 * in every language and hidden ones, too. The UID of the record in the
 * default language is returned, as relations always point to it.
 * Do not migrate content to Extbase Repository as this service will be called via Command.
 */
readonly class TitleRecordService
{
    use ConnectionPoolTrait;
    use DataHandlerTrait;

    public function __construct(
        private TcaSchemaFactory $tcaSchemaFactory,
        private LoggerInterface $logger,
    ) {}

    /**
     * Returns 0, if no record with the title exists
     */
    public function findUidByTitle(string $table, string $title): int
    {
        if ($title === '') {
            return 0;
        }

        $controlFields = TcaControlFields::fromSchema($this->tcaSchemaFactory->get($table));
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $queryBuilder
            ->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter($title)))
            ->setMaxResults(1);
        if ($controlFields->languageField !== null) {
            // Prefer the record in the default language over its translations
            $queryBuilder->orderBy($controlFields->languageField);
        }
        $queryBuilder->addOrderBy('uid');

        $record = $queryBuilder->executeQuery()->fetchAssociative();

        return is_array($record) ? $controlFields->getDefaultLanguageUid($record) : 0;
    }

    /**
     * Creates a record in the default language on the storage page, if no
     * record with the title exists. Returns 0, if it could not be created.
     */
    public function getOrCreateUidByTitle(string $table, string $title, int $storagePid): int
    {
        $uid = $this->findUidByTitle($table, $title);

        return $uid > 0 || $title === '' ? $uid : $this->createRecord($table, $title, $storagePid);
    }

    private function createRecord(string $table, string $title, int $storagePid): int
    {
        $newId = StringUtility::getUniqueId('NEW');
        $dataHandler = $this->getDataHandler();
        $dataHandler->start([$table => [$newId => ['pid' => $storagePid, 'title' => $title]]], []);
        $dataHandler->process_datamap();

        if ($dataHandler->errorLog !== []) {
            $this->logger->error(sprintf(
                'Record "%s" of table "%s" could not be created: %s',
                $title,
                $table,
                implode(', ', $dataHandler->errorLog),
            ));
            return 0;
        }

        $this->logger->info(sprintf('Record "%s" of table "%s" created on page %d', $title, $table, $storagePid));

        return (int)($dataHandler->substNEWwithIDs[$newId] ?? 0);
    }
}
