<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobfair2\ApiModel;

abstract class AbstractModel
{
    protected \SimpleXMLElement $xmlElement;

    public function getXmlElement(): \SimpleXMLElement
    {
        return $this->xmlElement;
    }

    public function getValueByPath(string $path, string $dataType = 'string', $default = '')
    {
        // xpath will always return an array of \SimpleXMLElement
        $elements = $this->xmlElement->xpath($path);

        // On error
        if ($elements === false) {
            return $default;
        }

        // Not found
        if ($elements === null) {
            return $default;
        }

        // \SimpleXMLElement is more a resource as an object.
        // So, it needs to be cast to the expected data type
        // See: https://www.php.net/manual/en/class.simplexmlelement.php#100811
        switch ($dataType) {
            case 'bool':
            case 'boolean':
                return (bool)current($elements);
            case 'int':
            case 'integer':
                return (int)current($elements);
            case 'float':
            case 'double':
                return (float)current($elements);
            case 'native':
            case \SimpleXMLElement::class:
                return $elements;
            case 'array':
                return (array)$elements;
            case 'string':
            default:
                return (string)current($elements);
        }
    }
}
