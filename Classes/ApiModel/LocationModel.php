<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobfair2\ApiModel;

class LocationModel extends AbstractModel
{
    public function __construct(\SimpleXMLElement $xmlElement)
    {
        $this->xmlElement = $xmlElement;
    }

    public function isPrimary(): bool
    {
        foreach ($this->xmlElement->attributes() as $attribute => $value) {
            if ($attribute === 'primary' && (string)$value === 'true') {
                return true;
            }
        }

        return false;
    }
}
