<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\Tests\Unit\ApiModel;

use JWeiland\Jobboard\ApiModel\JobModel;
use JWeiland\Jobboard\ApiModel\LocationModel;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class JobModelTest extends UnitTestCase
{
    #[Test]
    public function getVacancyIdReturnsNonNumericVacancyIdAsString(): void
    {
        $subject = new JobModel(new \SimpleXMLElement('<job><vacancy_id> AB-0815 </vacancy_id></job>'));

        self::assertSame('AB-0815', $subject->getVacancyId());
    }

    #[Test]
    public function getVacancyIdWithoutVacancyIdReturnsEmptyString(): void
    {
        $subject = new JobModel(new \SimpleXMLElement('<job><title>Web Developer (m/f/d)</title></job>'));

        self::assertSame('', $subject->getVacancyId());
    }

    #[Test]
    public function getLocationsReturnsOneLocationModelPerLocation(): void
    {
        $subject = new JobModel(new \SimpleXMLElement(
            '<job>
                <locations>
                    <location primary="false"><technical_name>loc1</technical_name></location>
                    <location primary="true"><technical_name>loc2</technical_name></location>
                </locations>
            </job>',
        ));

        $locations = $subject->getLocations();

        self::assertCount(2, $locations);
        self::assertContainsOnlyInstancesOf(LocationModel::class, $locations);
    }

    #[Test]
    public function getPrimaryLocationReturnsLocationMarkedAsPrimary(): void
    {
        $subject = new JobModel(new \SimpleXMLElement(
            '<job>
                <locations>
                    <location primary="false"><technical_name>loc1</technical_name></location>
                    <location primary="true"><technical_name>loc2</technical_name></location>
                </locations>
            </job>',
        ));

        $primaryLocation = $subject->getPrimaryLocation();

        self::assertSame('loc2', $primaryLocation->getValueByPath('technical_name'));
    }

    #[Test]
    public function getPrimaryLocationWithoutAnyPrimaryLocationThrowsException(): void
    {
        $subject = new JobModel(new \SimpleXMLElement(
            '<job>
                <vacancy_id>AB-0815</vacancy_id>
                <locations>
                    <location primary="false"><technical_name>loc1</technical_name></location>
                </locations>
            </job>',
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1751531883);
        $this->expectExceptionMessage('No primary location found for vacancy ID: AB-0815');

        $subject->getPrimaryLocation();
    }
}
