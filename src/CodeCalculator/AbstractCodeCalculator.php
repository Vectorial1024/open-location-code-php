<?php

namespace Vectorial1024\OpenLocationCodePhp\CodeCalculator;

use \Vectorial1024\OpenLocationCodePhp\CodeArea;

/**
 * An abstract class to provide a unified API for the 32-bit and 64-bit Open Location Code calculators.
 */
abstract class AbstractCodeCalculator
{
    /**
     * Assuming the given latitude and longitude are valid, encode these coordinates into an Open Location Code.
     * @param float $latitude The latitude in decimal degrees.
     * @param float $longitude The longitude in decimal degrees.
     * @param int $codeLength The desired number of digits in the code.
     * @return string The resulting (string) Open Location Code.
     */
    abstract public function encode(float $latitude, float $longitude, int $codeLength): string;

    /**
     * Decodes the given (string) Open Location Code into a CodeArea object encapsulating latitude/longitude bounding box.
     * @param string $code The Open Location Code in string format
     * @return CodeArea A CodeArea object.
     */
    abstract public function decode(string $code): CodeArea;

    /**
     * Returns a static instance of the appropriate code calculator depending on whether the current PHP is 32-bit/64-bit.
     * 
     * 32-bit PHP will get a calculator that uses floats, while 64-bit PHP will get a calculator that uses "long" ints.
     * 
     * This is required to allow 32-bit PHP environments to correctly calculate Open Location Codes.
     */
    public static function getDefaultCalculator()
    {
        // todo
        return null;
    }
}
