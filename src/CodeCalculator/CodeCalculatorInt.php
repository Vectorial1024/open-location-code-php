<?php

namespace Vectorial1024\OpenLocationCodePhp\CodeCalculator;

use RuntimeException;
use Vectorial1024\OpenLocationCodePhp\CodeArea;
use Vectorial1024\OpenLocationCodePhp\OpenLocationCode;

/**
 * A Open Location Code calculator that uses "long" int.
 * As such, this has ensured accuracy, but cannot be used in 32-bit PHP.
 */
class CodeCalculatorInt extends AbstractCodeCalculator
{
    // Value of the most significant latitude digit after it has been converted to an integer.
    // Note: since we are using 64-bit PHP, this can be an int.
    public const int LAT_MSP_VALUE = self::LAT_INTEGER_MULTIPLIER * OpenLocationCode::ENCODING_BASE * OpenLocationCode::ENCODING_BASE;

    // Value of the most significant longitude digit after it has been converted to an integer.
    // Note: since we are using 64-bit PHP, this can be an int.
    public const int LNG_MSP_VALUE = self::LNG_INTEGER_MULTIPLIER * OpenLocationCode::ENCODING_BASE * OpenLocationCode::ENCODING_BASE;

    public function __construct()
    {
        if (PHP_INT_SIZE < 8) {
            // 32-bit PHP has 32-bit int, which uses 4 bytes i.e. PHP_INT_SIZE = 4
            throw new RuntimeException("CodeCalculatorInt cannot be used due to bad PHP_INT_MAX value. Use CodeCalculatorFloat instead.");
        }
    }

    protected function generateRevOlcCode(float $latitude, float $longitude, int $codeLength): string
    {
        // PHP has native support for string concatenation, and string reversal is quite fast.
        $revCode = "";

        // Compute the code.
        // The idea of this approach is to convert each value to an integer 
        // after multiplying it by the final precision. 
        // This allows us to use only integer operations, so
        // avoiding any accumulation of floating point representation errors.

        // Multiply values by their precision and convert to positive.
        // Rounding avoids/minimises errors due to floating point precision.
        $latVal = intdiv((int) round(($latitude + OpenLocationCode::LATITUDE_MAX) * self::LAT_INTEGER_MULTIPLIER * 1e6), 1e6);
        $lngVal = intdiv((int) round(($longitude + OpenLocationCode::LONGITUDE_MAX) * self::LNG_INTEGER_MULTIPLIER * 1e6), 1e6);

        // Compute the grid part of the code if necessary.
        if ($codeLength > OpenLocationCode::PAIR_CODE_LENGTH) {
            for ($i = 0; $i < OpenLocationCode::GRID_CODE_LENGTH; $i++) {
                $latDigit = $latVal % OpenLocationCode::GRID_ROWS;
                $lngDigit = $lngVal % OpenLocationCode::GRID_COLUMNS;
                $ndx = $latDigit * OpenLocationCode::GRID_COLUMNS + $lngDigit;
                $revCode .= OpenLocationCode::CODE_ALPHABET[$ndx];
                $latVal = intdiv($latVal, OpenLocationCode::GRID_ROWS);
                $lngVal = intdiv($lngVal, OpenLocationCode::GRID_COLUMNS);
            }
            unset($i, $latDigit, $lngDigit, $ndx);
        } else {
            $latVal = (int) ($latVal / pow(OpenLocationCode::GRID_ROWS, OpenLocationCode::GRID_CODE_LENGTH));
            $lngVal = (int) ($lngVal / pow(OpenLocationCode::GRID_COLUMNS, OpenLocationCode::GRID_CODE_LENGTH));
        }

        // Compute the pair section of the code.
        for ($i = 0; $i < intdiv(OpenLocationCode::PAIR_CODE_LENGTH, 2); $i++) {
            $revCode .= OpenLocationCode::CODE_ALPHABET[$lngVal % OpenLocationCode::ENCODING_BASE];
            $revCode .= OpenLocationCode::CODE_ALPHABET[$latVal % OpenLocationCode::ENCODING_BASE];
            $latVal = intdiv($latVal, OpenLocationCode::ENCODING_BASE);
            $lngVal = intdiv($lngVal, OpenLocationCode::ENCODING_BASE);
            // If we are at the separator position, add the separator
            if ($i == 0) {
                $revCode .= OpenLocationCode::SEPARATOR;
            }
        }

        return $revCode;
    }

    public function decode(string $strippedCode): CodeArea
    {
        // Initialize the values. 
        $latVal = -OpenLocationCode::LATITUDE_MAX * self::LAT_INTEGER_MULTIPLIER;
        $lngVal = -OpenLocationCode::LONGITUDE_MAX * self::LNG_INTEGER_MULTIPLIER;
        // Define the place value for the digits. We'll divide this down as we work through the code.
        $latPlaceVal = self::LAT_MSP_VALUE;
        $lngPlaceVal = self::LNG_MSP_VALUE;
        for ($i = OpenLocationCode::PAIR_CODE_LENGTH; $i < min(strlen($strippedCode), OpenLocationCode::MAX_DIGIT_COUNT); $i += 2) {
            $latPlaceVal = intdiv($latPlaceVal, OpenLocationCode::ENCODING_BASE);
            $lngPlaceVal = intdiv($lngPlaceVal, OpenLocationCode::ENCODING_BASE);
            $latVal += strpos(OpenLocationCode::CODE_ALPHABET, $strippedCode[$i]) * $latPlaceVal;
            $lngVal = strpos(OpenLocationCode::CODE_ALPHABET, $strippedCode[$i + 1]) * $lngPlaceVal;
        }
        unset($i);
        for ($i = OpenLocationCode::PAIR_CODE_LENGTH; $i < min(strlen($strippedCode), OpenLocationCode::MAX_DIGIT_COUNT); $i++) {
            $latPlaceVal = intdiv($latPlaceVal, OpenLocationCode::GRID_ROWS);
            $lngPlaceVal = intdiv($lngPlaceVal, OpenLocationCode::GRID_COLUMNS);
            $digit = strpos(OpenLocationCode::CODE_ALPHABET, $strippedCode[$i]);
            $row = intdiv($digit, OpenLocationCode::GRID_COLUMNS);
            $col = $digit % OpenLocationCode::GRID_COLUMNS;
            $latVal += $row * $latPlaceVal;
            $lngVal += $col * $lngPlaceVal;
            unset($digit);
        }
        unset($i);
        $latitudeLo = $latVal / self::LAT_INTEGER_MULTIPLIER;
        $longitudeLo = $lngVal / self::LNG_INTEGER_MULTIPLIER;
        $latitudeHi = ($latVal + $latPlaceVal) / self::LAT_INTEGER_MULTIPLIER;
        $longitudeHi = ($lngVal + $lngPlaceVal) / self::LNG_INTEGER_MULTIPLIER;
        return new CodeArea(
            $latitudeLo,
            $longitudeLo,
            $latitudeHi,
            $longitudeHi,
            min(strlen($strippedCode), OpenLocationCode::MAX_DIGIT_COUNT),
        );
    }
}
