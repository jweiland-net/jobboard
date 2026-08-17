<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Domain\Model;

/**
 * Add tx_maps2_uid for EXT:maps to a domain model of tt_address
 */
class Address extends \FriendsOfTYPO3\TtAddress\Domain\Model\Address
{
    protected ?\JWeiland\Maps2\Domain\Model\PoiCollection $txMaps2Uid = null;

    public function getTxMaps2Uid(): ?\JWeiland\Maps2\Domain\Model\PoiCollection
    {
        return $this->txMaps2Uid;
    }

    public function setTxMaps2Uid(\JWeiland\Maps2\Domain\Model\PoiCollection $txMaps2Uid): void
    {
        $this->txMaps2Uid = $txMaps2Uid;
    }
}
