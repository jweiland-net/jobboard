<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobboard.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobboard\ApiModel;

abstract class AbstractModel
{
    protected \SimpleXMLElement $xmlElement;

    public function getXmlElement(): \SimpleXMLElement
    {
        return $this->xmlElement;
    }

    public function hasPath(string $path): bool
    {
        $elements = $this->xmlElement->xpath($path);

        return is_array($elements) && $elements !== [];
    }

    /**
     * Returns $default, if the path does not exist in the XML. Without a
     * $default, a missing path returns the empty value of $dataType, e.g.
     * '' for "string" or 0 for "int". An existing, but empty element is not
     * missing and returns its (empty) value.
     */
    public function getValueByPath(string $path, string $dataType = 'string', mixed $default = null): mixed
    {
        // xpath will always return an array of \SimpleXMLElement, an empty
        // one if the path was not found, false (or null) on error
        $elements = $this->xmlElement->xpath($path);

        if (!is_array($elements) || $elements === []) {
            return $default ?? $this->castElements([], $dataType);
        }

        return $this->castElements($elements, $dataType);
    }

    /**
     * \SimpleXMLElement is more a resource as an object.
     * So, it needs to be cast to the expected data type
     * See: https://www.php.net/manual/en/class.simplexmlelement.php#100811
     *
     * @param \SimpleXMLElement[] $elements
     */
    private function castElements(array $elements, string $dataType): mixed
    {
        return match ($dataType) {
            'bool', 'boolean' => (bool)current($elements),
            'int', 'integer' => (int)current($elements),
            'float', 'double' => (float)current($elements),
            'native', \SimpleXMLElement::class => $elements,
            'array' => (array)$elements,
            default => (string)current($elements),
        };
    }
}
