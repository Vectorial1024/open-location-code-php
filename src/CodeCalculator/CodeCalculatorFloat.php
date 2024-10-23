<?php

namespace Vectorial1024\OpenLocationCodePhp\CodeCalculator;

use Vectorial1024\OpenLocationCodePhp\CodeArea;
use Vectorial1024\OpenLocationCodePhp\OpenLocationCode;

/**
 * A Open Location Code calculator that uses float.
 * As such, this is usable on any PHP version. but may have unforeseen inaccuracy.
 */
class CodeCalculatorFloat extends AbstractCodeCalculator
{
    // Value of the most significant latitude digit after it has been converted to an integer.
    // Note: to ensure 32bit PHP compatibility, this is now a precisely-represented float.
    public const float LAT_MSP_VALUE = self::LAT_INTEGER_MULTIPLIER * OpenLocationCode::ENCODING_BASE * OpenLocationCode::ENCODING_BASE;

    // Value of the most significant longitude digit after it has been converted to an integer.
    // Note: to ensure 32bit PHP compatibility, this is now a precisely-represented float.
    public const float LNG_MSP_VALUE = self::LNG_INTEGER_MULTIPLIER * OpenLocationCode::ENCODING_BASE * OpenLocationCode::ENCODING_BASE;

    protected function generateRevOlcCode(float $latitude, float $longitude, int $codeLength): string
    {
        // PHP has native support for string concatenation, and string reversal is quite fast.
        $revCode = "";

        // Compute the code.
        // The idea of this approach is to convert each value to an integer 
        // after multiplying it by the final precision. 
        // This allows us to use only integer operations, so
        // avoiding any accumulation of floating point representation errors.
        // However, it must also be noted that the calculation may poduce 10-digit integers
        // that begins with 6, which overflows the 32-bit PHP int type.
        // The good news is, this relatively small bignum can be precisely represented
        // by the double-precision float type. We just need to be careful when calculating.

        // Multiply values by their precision and convert to positive.
        // Rounding avoids/minimizes errors due to floating-point precision.
        // Since the numbers are positive, floor() is equivalent to intval().
        $latVal = floor(round(($latitude + OpenLocationCode::LATITUDE_MAX) * self::LAT_INTEGER_MULTIPLIER * 1e6) / 1e6);
        $lngVal = floor(round(($longitude + OpenLocationCode::LONGITUDE_MAX) * self::LNG_INTEGER_MULTIPLIER * 1e6) / 1e6);

        // Compute the grid part of the code if necessary.
        if ($codeLength > OpenLocationCode::PAIR_CODE_LENGTH) {
            for ($i = 0; $i < OpenLocationCode::GRID_CODE_LENGTH; $i++) {
                $latDigit = (int) fmod($latVal, OpenLocationCode::GRID_ROWS);
                $lngDigit = (int) fmod($lngVal, OpenLocationCode::GRID_COLUMNS);
                $ndx = $latDigit * OpenLocationCode::GRID_COLUMNS + $lngDigit;
                $revCode .= OpenLocationCode::CODE_ALPHABET[$ndx];
                $latVal = floor($latVal / OpenLocationCode::GRID_ROWS);
                $lngVal = floor($lngVal / OpenLocationCode::GRID_COLUMNS);
            }
            unset($i, $latDigit, $lngDigit, $ndx);
        } else {
            $latVal = floor($latVal / pow(OpenLocationCode::GRID_ROWS, OpenLocationCode::GRID_CODE_LENGTH));
            $lngVal = floor($lngVal / pow(OpenLocationCode::GRID_COLUMNS, OpenLocationCode::GRID_CODE_LENGTH));
        }

        // Compute the pair section of the code.
        for ($i = 0; $i < intdiv(OpenLocationCode::PAIR_CODE_LENGTH, 2); $i++) {
            $revCode .= OpenLocationCode::CODE_ALPHABET[(int) fmod($lngVal, OpenLocationCode::ENCODING_BASE)];
            $revCode .= OpenLocationCode::CODE_ALPHABET[(int) fmod($latVal, OpenLocationCode::ENCODING_BASE)];
            $latVal = floor($latVal / OpenLocationCode::ENCODING_BASE);
            $lngVal = floor($lngVal / OpenLocationCode::ENCODING_BASE);
            // If we are at the separator position, add the separator
            if ($i == 0) {
                $revCode .= OpenLocationCode::SEPARATOR;
            }
        }

        return $revCode;
    }

    protected function generateCodeArea(string $strippedCode): CodeArea
    {
        // Initialize the values. 
        // We will assume these values are floats to ensure 32bit PHP compatibility.
        // See relevant comments in encode() above.
        $latVal = -OpenLocationCode::LATITUDE_MAX * self::LAT_INTEGER_MULTIPLIER;
        $lngVal = -OpenLocationCode::LONGITUDE_MAX * self::LNG_INTEGER_MULTIPLIER;
        // Define the place value for the digits. We'll divide this down as we work through the code.
        $latPlaceVal = self::LAT_MSP_VALUE;
        $lngPlaceVal = self::LNG_MSP_VALUE;
        for ($i = OpenLocationCode::PAIR_CODE_LENGTH; $i < min(strlen($strippedCode), OpenLocationCode::MAX_DIGIT_COUNT); $i += 2) {
            $latPlaceVal = floor($latPlaceVal / OpenLocationCode::ENCODING_BASE);
            $lngPlaceVal = floor($lngPlaceVal / OpenLocationCode::ENCODING_BASE);
            $latVal += strpos(OpenLocationCode::CODE_ALPHABET, $strippedCode[$i]) * $latPlaceVal;
            $lngVal += strpos(OpenLocationCode::CODE_ALPHABET, $strippedCode[$i + 1]) * $lngPlaceVal;
        }
        unset($i);
        for ($i = OpenLocationCode::PAIR_CODE_LENGTH; $i < min(strlen($strippedCode), OpenLocationCode::MAX_DIGIT_COUNT); $i++) {
            $latPlaceVal = floor($latPlaceVal / OpenLocationCode::GRID_ROWS);
            $lngPlaceVal = floor($lngPlaceVal / OpenLocationCode::GRID_COLUMNS);
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
