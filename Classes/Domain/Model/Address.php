<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Domain\Model;

use FriendsOfTYPO3\TtAddress\Domain\Model\Address as TtAddress;
use JWeiland\Maps2\Domain\Model\PoiCollection;

/**
 * Domain model of tt_address with the relation "tx_maps2_uid" to a
 * PoiCollection of the optional EXT:maps2.
 *
 * Without EXT:maps2 the column "tx_maps2_uid" does not exist in TCA,
 * so Extbase skips this property and it always stays null.
 *
 * The parameter of the setter has no class type on purpose: Extbase
 * reflects class types of method parameters and fails if the class does
 * not exist (EXT:maps2 not installed). The typed property still ensures
 * that only a PoiCollection can be assigned.
 */
class Address extends TtAddress
{
    /**
     * Extbase reads the type of the property by PhpDocExtractor first,
     * before the ReflectionExtractor prefers the untyped setter parameter.
     * Without this annotation, the relation is not mapped (Relation::NONE)
     * and the PoiCollection is never loaded.
     *
     * @var PoiCollection|null
     */
    protected ?PoiCollection $txMaps2Uid = null;

    public function getTxMaps2Uid(): ?PoiCollection
    {
        return $this->txMaps2Uid;
    }

    public function setTxMaps2Uid(?object $txMaps2Uid): void
    {
        $this->txMaps2Uid = $txMaps2Uid;
    }
}
