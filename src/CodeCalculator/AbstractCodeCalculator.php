<?php

namespace Vectorial1024\OpenLocationCodePhp\CodeCalculator;

use Vectorial1024\OpenLocationCodePhp\CodeArea;
use Vectorial1024\OpenLocationCodePhp\OpenLocationCode;

/**
 * An abstract class to provide a unified API for the 32-bit and 64-bit Open Location Code calculators.
 */
abstract class AbstractCodeCalculator
{
    // Value to multiple latitude degrees to convert it to an integer with the maximum encoding
    // precision. I.e. ENCODING_BASE**3 * GRID_ROWS**GRID_CODE_LENGTH
    protected const int LAT_INTEGER_MULTIPLIER = 8000 * 3125;

    // Value to multiple longitude degrees to convert it to an integer with the maximum encoding
    // precision. I.e. ENCODING_BASE**3 * GRID_COLUMNS**GRID_CODE_LENGTH
    protected const int LNG_INTEGER_MULTIPLIER = 8000 * 1024;

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
     * Assuming the given Open Location Code is valid, decodes it into a CodeArea object encapsulating latitude/longitude bounding box.
     * @param string $code The stripped Open Location Code.
     * @return CodeArea A CodeArea object.
     */
    public function decode(string $code): CodeArea
    {
        // Strip padding and separator characters out of the code.
        $clean = str_replace([OpenLocationCode::SEPARATOR, OpenLocationCode::PADDING_CHARACTER], "", $code);

        return $this->generateCodeArea($clean);
    }

    /**
     * Performs the necessary calculation to generate a CodeArea object from the given (stripped) Open Location Code.
     * @param string $strippedCode The stripped Open Location Code.
     * @return CodeArea A CodeArea object.
     */
    abstract protected function generateCodeArea(string $strippedCode): CodeArea;

    /**
     * Returns a static instance of the appropriate code calculator depending on whether the current PHP is 32-bit/64-bit.
     * 32-bit PHP will get a calculator that uses floats, while 64-bit PHP will get a calculator that uses "long" ints.
     * 
     * This is required to allow 32-bit PHP environments to correctly calculate Open Location Codes, while preserving
     * the performance advantage of 64-bit PHP by using integer operations.
     * @return CodeCalculatorFloat|CodeCalculatorInt The appropriate code calculator.
     */
    public static function getDefaultCalculator(): CodeCalculatorFloat|CodeCalculatorInt
    {
        // check PHP environment to give int calculator where possible
        // 32-bit PHP has 32-bit int, which uses 4 bytes i.e. PHP_INT_SIZE = 4
        // initializing static variables with constructors is only allowed for PHP 8.3+
        static $defaultCalculator = PHP_INT_SIZE > 4 ? new CodeCalculatorInt() : new CodeCalculatorFloat();
        return $defaultCalculator;
    }
}
