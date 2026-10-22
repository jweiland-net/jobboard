<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\ApiModel;

class JobModel extends AbstractModel
{
    public function __construct(\SimpleXMLElement $xmlElement)
    {
        $this->xmlElement = $xmlElement;
    }

    /**
     * The ID of the job in the source. It is not cast to int, as a source
     * may deliver non-numeric IDs. Empty, if the source delivers none.
     */
    public function getVacancyId(): string
    {
        return trim($this->getValueByPath('vacancy_id'));
    }

    /**
     * @return LocationModel[]
     */
    public function getLocations(): array
    {
        $locations = $this->getValueByPath('locations/location', \SimpleXMLElement::class, []);

        return array_map(fn($location) => new LocationModel($location), $locations);
    }

    public function getPrimaryLocation(): LocationModel
    {
        foreach ($this->getLocations() as $location) {
            if ($location->isPrimary()) {
                return $location;
            }
        }

        throw new \RuntimeException(
            sprintf(
                'No primary location found for vacancy ID: %s',
                $this->getVacancyId(),
            ),
            1751531883,
        );
    }
}
