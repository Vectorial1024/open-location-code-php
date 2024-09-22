<?php

namespace Vectorial1024\OpenLocationCodePhp;

/**
 * An object representing an Open Location Code (OLC).
 * 
 * Create OLC objects first, then use the instance methods for other calculations.
 */
final class OpenLocationCode
{
    // Provides a normal precision code, approximately 14x14 meters.
    public const int CODE_PRECISION_NORMAL = 10;

    // The character set used to encode the values.
    public const string CODE_ALPHABET = "23456789CFGHJMPQRVWX";

    // A separator used to break the code into two parts to aid memorability.
    public const string SEPARATOR = '+';

    // The character used to pad codes.
    public const string PADDING_CHARACTER = '0';

    // The number of characters to place before the separator.
    private const int SEPARATOR_POSITION = 8;

    // The max number of digits to process in a plus code.
    public const int MAX_DIGIT_COUNT = 15;

    // Maximum code length using just lat/lng pair encoding.
    private const int PAIR_CODE_LENGTH = 10;

    // Number of digits in the grid coding section.
    private const int GRID_CODE_LENGTH = self::MAX_DIGIT_COUNT - self::PAIR_CODE_LENGTH;

    // The base to use to convert numbers to/from.
    // Note: since PHP cannot initialize constants just like in Java, we cannot easily ensure this number is correct.
    private const int ENCODING_BASE = 20;

    // The maximum value for latitude in degrees.
    private const int LATITUDE_MAX = 90;

    // The maximum value for longitude in degrees.
    private const int LONGITUDE_MAX = 180;

    // Number of columns in the grid refinement method.
    private const int GRID_COLUMNS = 4;

    // Number of rows in the grid refinement method.
    private const int GRID_ROWS = 5;

    // Value to multiple latitude degrees to convert it to an integer with the maximum encoding
    // precision. I.e. ENCODING_BASE**3 * GRID_ROWS**GRID_CODE_LENGTH
    private const int LAT_INTEGER_MULTIPLIER = 8000 * 3125;

    // Value to multiple longitude degrees to convert it to an integer with the maximum encoding
    // precision. I.e. ENCODING_BASE**3 * GRID_COLUMNS**GRID_CODE_LENGTH
    private const int LNG_INTEGER_MULTIPLIER = 8000 * 1024;

    // Value of the most significant latitude digit after it has been converted to an integer.
    private const int LAT_MSP_VALUE = self::LAT_INTEGER_MULTIPLIER * self::ENCODING_BASE * self::ENCODING_BASE;

    // Value of the most significant longitude digit after it has been converted to an integer.
    private const int LNG_MSP_VALUE = self::LNG_INTEGER_MULTIPLIER * self::ENCODING_BASE * self::ENCODING_BASE;

    /**
     * Constructor of OLC objects; for internal use only.
     * @param string $code The string representation of the Open Location code.
     */
    private function __construct(
        public readonly string $code
    ) {
    }

    // wip factory constructors here

    // ---

    /**
     * Returns whether the provided string is a valid Open Location code.
     * 
     * @param string $code The code to check.
     * @return bool True if it is a valid full or short code.
     */
    public static function isValidCode(?string $code): bool
    {
        if ($code === null || strlen($code) < 2) {
            return false;
        }
        $code = strtoupper($code);

        // There must be exactly one separator.
        $separatorPosition = strpos($code, self::SEPARATOR);
        if ($separatorPosition === false) {
            return false;
        }
        if ($separatorPosition != strrpos($code, self::SEPARATOR)) {
            return false;
        }
        // There must be an even number of at most 8 characters before the separator.
        if ($separatorPosition % 2 != 0 || $separatorPosition > self::SEPARATOR_POSITION) {
            return false;
        }

        // Check first two characters: only some values from the alphabet are permitted.
        if ($separatorPosition == self::SEPARATOR_POSITION) {
            // First latitude character can only have first 9 values.
            if (strpos(self::CODE_ALPHABET, $code[0]) > 8) {
                return false;
            }
  
            // First longitude character can only have first 18 values.
            if (strpos(self::CODE_ALPHABET, $code[1]) > 17) {
                return false;
            }
        }

        // Check the characters before the separator.
        $paddingStarted = false;
        for ($i = 0; $i < $separatorPosition; $i++) {
            $currentChar = $code[$i];
            if (!str_contains(self::CODE_ALPHABET, $currentChar) && $currentChar != self::PADDING_CHARACTER) {
                // Invalid character
                return false;
            }
            if ($paddingStarted) {
                // Once padding starts, there must not be anything but padding.
                if ($currentChar != self::PADDING_CHARACTER) {
                    return false;
                }
            } elseif ($currentChar == self::PADDING_CHARACTER) {
                $paddingStarted = true;
                // Short codes cannot have padding
                if ($separatorPosition < self::SEPARATOR_POSITION) {
                    return false;
                }
                // Padding can start on even character: 2, 4 or 6.
                if ($i != 2 && $i != 4 && $i != 6) {
                    return false;
                }
            }
            unset($currentChar);
        }
        unset($i);

        // Check the characters after the separator.
        $codeLength = strlen($code);
        if ($codeLength > $separatorPosition + 1) {
            if ($paddingStarted) {
                return false;
            }
            // Only one character after separator is forbidden.
            if ($codeLength == $separatorPosition + 2) {
                return false;
            }
            for ($i = $separatorPosition + 1; $i < $codeLength; $i++) {
                if (!str_contains(self::CODE_ALPHABET, $code[$i])) {
                    return false;
                }
            }
            unset($i);
        }

        return true;
    }

    /**
     * Returns whether this Open Location code is valid.
     * @return bool True if it is a valid full or short code.
     */
    public function isValid(): bool
    {
        return self::isValidCode($this->code);
    }
}
