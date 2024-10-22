<?php

namespace Vectorial1024\OpenLocationCodePhp\CodeCalculator;

use Vectorial1024\OpenLocationCodePhp\CodeArea;
use Vectorial1024\OpenLocationCodePhp\OpenLocationCode;

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
    public function encode(float $latitude, float $longitude, int $codeLength): string
    {
        // Let the calculator correctly generate the reversed code
        $revCode = static::generateRevOlcCode($latitude, $longitude, $codeLength);
        // Reverse the code
        $code = strrev($revCode);

        // If we need to pad the code, replace some of the digits.
        if ($codeLength < OpenLocationCode::SEPARATOR_POSITION) {
            for ($i = $codeLength; $i < OpenLocationCode::SEPARATOR_POSITION; $i++) {
                $code[$i] = OpenLocationCode::PADDING_CHARACTER;
            }
        }
        $finalCode = substr($code, 0, max(OpenLocationCode::SEPARATOR_POSITION + 1, $codeLength + 1));
        return $finalCode;
    }

    /**
     * Performs the necessary calculation to generate a reversed (and possibly incomplete) Open Location Code from the given coordinates.
     * The exact implementation depends on whether the 32-bit/64-bit calculator is used, but the idea should be the same.
     * @param float $latitude The latitude in decimal degrees.
     * @param float $longitude The longitude in decimal degrees.
     * @param int $codeLength The desired number of digits in the code.
     * @return string The resulting (string) Open Location Code.
     */
    abstract protected function generateRevOlcCode(float $latitude, float $longitude, int $codeLength): string;

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
     * 
     * @return CodeCalculatorFloat The appropriate code calculator.
     */
    public static function getDefaultCalculator(): CodeCalculatorFloat
    {
        // always give float calculator for now
        // todo check platform status to give int calculator
        static $defaultCalculator = new CodeCalculatorFloat();
        return $defaultCalculator;
    }
}
