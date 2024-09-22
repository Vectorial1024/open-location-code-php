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
    private const int GRID_CODE_LENGTH = MAX_DIGIT_COUNT - PAIR_CODE_LENGTH;

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
    private const int LAT_MSP_VALUE = LAT_INTEGER_MULTIPLIER * ENCODING_BASE * ENCODING_BASE;

    // Value of the most significant longitude digit after it has been converted to an integer.
    private const int LNG_MSP_VALUE = LNG_INTEGER_MULTIPLIER * ENCODING_BASE * ENCODING_BASE;
}
