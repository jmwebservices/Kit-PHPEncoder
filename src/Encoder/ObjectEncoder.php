<?php

namespace Riimu\Kit\PHPEncoder\Encoder;

/**
 * Encoder for generic object values.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2014, Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class ObjectEncoder implements Encoder
{
    /** @var array Default values for options in the encoder */
    private static $defaultOptions = [
        'object.method' => true,
        'object.format' => 'vars',
        'object.cast' => true,
    ];

    public function getDefaultOptions()
    {
        return self::$defaultOptions;
    }

    public function supports($value)
    {
        return is_object($value);
    }

    public function encode($value, $depth, array $options, callable $encode)
    {
        if ($options['object.method']) {
            if (method_exists($value, 'toPHP')) {
                return (string) $value->toPHP();
            } elseif (method_exists($value, 'toPHPValue')) {
                return $encode($value->toPHPValue());
            }
        }

        return $this->encodeObject($value, $options, $encode);
    }

    /**
     * Encodes the object as string according to encoding options.
     * @param object $object Object to convert to code
     * @param array $options List of encoder options
     * @param callable $encode Callback used to encode values
     * @return string The object encoded as string
     */
    public function encodeObject($object, array $options, callable $encode)
    {
        if ($options['object.format'] === 'string') {
            return $encode((string) $object);
        } elseif ($options['object.format'] === 'serialize') {
            return sprintf('unserialize(%s)', $encode(serialize($object)));
        } elseif ($options['object.format'] === 'export') {
            return sprintf('\\%s::__set_state(%s)', get_class($object), $encode($this->getObjectState($object)));
        }

        $output = $encode($this->getObjectArray($object, $options['object.format']));

        if ($options['object.cast']) {
            $output = '(object)' . ($options['whitespace'] ? ' ' : '') . $output;
        }

        return $output;
    }

    /**
     * Converts the object into array that can be encoded.
     * @param object $object Object to convert to an array
     * @param string $format Object conversion format
     * @return array The object converted into an array
     * @throws \RuntimeException If object conversion format is invalid
     */
    private function getObjectArray($object, $format)
    {
        if ($format === 'array') {
            return (array) $object;
        } elseif ($format === 'vars') {
            return get_object_vars($object);
        } elseif ($format === 'iterate') {
            $array = [];
            foreach ($object as $key => $value) {
                $array[$key] = $value;
            }
            return $array;
        }

        throw new \RuntimeException('Invalid object encoding format: ' . $format);
    }

    /**
     * Returns an array of object properties as would be generated by var_export.
     * @param object $object Object to turn into array
     * @return array Properties of the object as passed to var_export
     */
    private function getObjectState($object)
    {
        $class = new \ReflectionClass($object);
        $visibility = \ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED;
        $values = [];

        do {
            foreach ($class->getProperties($visibility) as $property) {
                $property->setAccessible(true);
                $values[$property->getName()] = $property->getValue($object);
            }

            $class = $class->getParentClass();
            $visibility = \ReflectionProperty::IS_PRIVATE;
        } while ($class);

        return get_object_vars($object) + $values;
    }
}