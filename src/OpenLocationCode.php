<?php

namespace Vectorial1024\OpenLocationCodePhp;

use InvalidArgumentException;
use LogicException;
use Stringable;
use Vectorial1024\OpenLocationCodePhp\CodeCalculator\AbstractCodeCalculator;

/**
 * An object representing an Open Location Code (OLC).
 * 
 * Create OLC objects first, then use the instance methods for other calculations.
 */
final class OpenLocationCode implements Stringable
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
    public const int SEPARATOR_POSITION = 8;

    // The max number of digits to process in a plus code.
    public const int MAX_DIGIT_COUNT = 15;

    // Maximum code length using just lat/lng pair encoding.
    public const int PAIR_CODE_LENGTH = 10;

    // Number of digits in the grid coding section.
    public const int GRID_CODE_LENGTH = self::MAX_DIGIT_COUNT - self::PAIR_CODE_LENGTH;

    // The base to use to convert numbers to/from.
    // Note: since PHP cannot initialize constants just like in Java, we cannot easily ensure this number is correct.
    public const int ENCODING_BASE = 20;

    // The maximum value for latitude in degrees.
    public const int LATITUDE_MAX = 90;

    // The maximum value for longitude in degrees.
    public const int LONGITUDE_MAX = 180;

    // Number of columns in the grid refinement method.
    public const int GRID_COLUMNS = 4;

    // Number of rows in the grid refinement method.
    public const int GRID_ROWS = 5;

    // Note: constants that are only for encoding/decoding are moved to AbstractCodeCalculator.

    // The 360 degree circle information to normalize longitudes.
    private const int CIRCLE_DEG = 2 * self::LONGITUDE_MAX;

    /**
     * Constructor of OLC objects; for internal use only.
     * @param string $code The string representation of the Open Location code.
     * 
     * @see self::createFromCode()
     */
    private function __construct(
        public readonly string $code
    ) {
    }

    // wip factory constructors here

    /**
     * Creates Open Location Code object for the provided code.
     * 
     * @param string $code A valid OLC code; can be a full code or a short code.
     * @return self The created OLC object.
     * @throws InvalidArgumentException when the passed code is not valid.
     */
    public static function createFromCode(?string $code): self
    {
        if (!self::isValidCode($code)) {
            throw new InvalidArgumentException("The provided code " . ($code ?? "(null)") . " is not a valid Open Location Code.");
        }
        return new self(strtoupper($code));
    }

    /**
     * Creates Open Location Code with default/custom precision length.
     * 
     * @param float $latitude The latitude in decimal degrees.
     * @param float $longitude The longitude in decimal degrees.
     * @param int $codeLength The desired number of digits in the code; leave blank for a default value.
     * @return self The created OLC object.
     * @throws InvalidArgumentException when the code length is invalid.
     */
    public static function createFromCoordinates(float $latitude, float $longitude, int $codeLength = self::CODE_PRECISION_NORMAL): self
    {
        // Limit the maximum number of digits in the code.
        $codeLength = min($codeLength, self::MAX_DIGIT_COUNT);
        // Check that the code length requested is valid.
        if ($codeLength < self::PAIR_CODE_LENGTH && $codeLength % 2 == 1 || $codeLength < 4) {
            throw new InvalidArgumentException("Illegal code length $codeLength");
        }
        // Ensure that latitude and longitude are valid.
        $latitude = self::clipLatitude($latitude);
        $longitude = self::normalizeLongitude($longitude);

        // Latitude 90 needs to be adjusted to be just less, so the returned code can also be decoded.
        if ($latitude == self::LATITUDE_MAX) {
            $latitude = $latitude - 0.9 * self::computeLatitudePrecision($codeLength);
        }

        // Delegate to the correct Code Calculator to generate the code for 32-bit/64-bit platforms
        $calculator = AbstractCodeCalculator::getDefaultCalculator();
        return new self($calculator->encode($latitude, $longitude, $codeLength));
    }

    // ---

    /**
     * Encodes latitude/longitude into Open Location Code of provided length (default is 10 digit).
     * This method is equivalent to creating the OpenLocationCode object and getting the code from it.
     * 
     * @param float $latitude The latitude in decimal degrees.
     * @param float $longitude The longitude in decimal degrees.
     * @param int $codeLength The number of digits in the returned code (omit for default length).
     * @return string The code.
     */
    public static function encode(float $latitude, float $longitude, int $codeLength = self::CODE_PRECISION_NORMAL): string
    {
        return self::createFromCoordinates($latitude, $longitude, $codeLength)->code;
    }

    /**
     * Decodes this object into a CodeArea object encapsulating latitude/longitude bounding box.
     * @return CodeArea A CodeArea object.
     */
    public function decode(): CodeArea
    {
        if (!$this->isFull()) {
            throw new LogicException("Method decode() may only be called on valid full codes, but code was {$this->code}.");
        }
        // Strip padding and separator characters out of the code.
        $clean = str_replace([self::SEPARATOR, self::PADDING_CHARACTER], "", $this->code);

        // Delegate to the correct Code Calculator to decode the OLC code for 32-bit/64-bit platforms
        $calculator = AbstractCodeCalculator::getDefaultCalculator();
        return $calculator->decode($clean);
    }

    public function __toString(): string
    {
        return $this->code;
    }

    /**
     * Returns short Open Location Code from the full Open Location Code created by removing four or six digits,
     * depending on the provided reference poi9nt. It removes as many digits as possible.
     * 
     * @param float $referenceLatitude The reference latitude in degrees.
     * @param float $referenceLongitude The reference longitude in degrees.
     * @return self A short code if possible.
     */
    public function shorten(float $referenceLatitude, float $referenceLongitude): self
    {
        if (!$this->isFull()) {
            throw new LogicException("shorten() method may only be called on a full code.");
        }
        if ($this->isPadded()) {
            throw new LogicException("shorten() method may not be called on a padded code.");
        }

        $codeArea = $this->decode();
        $range = max(
            abs($referenceLatitude - $codeArea->getCenterLatitude()),
            abs($referenceLongitude - $codeArea->getCenterLongitude())
        );
        // We are going to check to see if we can remove three pairs, two pairs or just one pair of
        // digits from the code.
        for ($i = 4; $i >= 1; $i--) {
            // Check if we're close enough to shorten. The range must be less than 1/2
            // the precision to shorten at all, and we want to allow some safety, so
            // use 0.3 instead of 0.5 as a multiplier.
            if ($range < (self::computeLatitudePrecision($i * 2) * 0.3)) {
                // We're done.
                return new self(substr($this->code, $i * 2));
            }
        }
        unset($i);
        throw new InvalidArgumentException("Reference location is too far from the Open Location Code center.");
    }

    /**
     * Returns an Open Location Code object representing a full Open Location Code from this
     * (short) Open Location Code, given the reference location.
     *
     * @param float $referenceLatitude The reference latitude in degrees.
     * @param float $referenceLongitude The reference longitude in degrees.
     * @return self The nearest matching full code.
     */
    public function recover(float $referenceLatitude, float $referenceLongitude): self
    {
        if ($this->isFull()) {
            // Note: each code is either full xor short, no other option.
            return $this;
        }
        $referenceLatitude = self::clipLatitude($referenceLatitude);
        $referenceLongitude = self::normalizeLongitude($referenceLongitude);

        $digitsToRecover = self::SEPARATOR_POSITION - strpos($this->code, self::SEPARATOR);
        // The precision (height and width) of the missing prefix in degrees.
        $prefixPrecision = pow(self::ENCODING_BASE, 2 - intdiv($digitsToRecover, 2));

        // Use the reference location to generate the prefix.
        $recoveredPrefix = substr(self::createFromCoordinates($referenceLatitude, $referenceLongitude)->code, 0, $digitsToRecover);
        // Combine the prefix with the short code and decode it.
        $recovered = new self($recoveredPrefix . $this->code);
        $recoveredCodeArea = $recovered->decode();
        // Work out whether the new code area is too far from the reference location. If it is, we
        // move it. It can only be out by a single precision step.
        $recoveredLatitude = $recoveredCodeArea->getCenterLatitude();
        $recoveredLongitude = $recoveredCodeArea->getCenterLongitude();

        // Move the recovered latitude by one precision up or down if it is too far from the reference,
        // unless doing so would lead to an invalid latitude.
        $latitudeDiff = $recoveredLatitude - $referenceLatitude;
        if ($latitudeDiff > $prefixPrecision / 2 && $recoveredLatitude - $prefixPrecision > -self::LATITUDE_MAX) {
            $recoveredLatitude -= $prefixPrecision;
        } elseif ($latitudeDiff < -$prefixPrecision / 2 && $recoveredLatitude + $prefixPrecision < self::LATITUDE_MAX) {
            $recoveredLatitude += $prefixPrecision;
        }

        // Move the recovered longitude by one precision up or down if it is too far from the reference.
        $longitudeDiff = $recoveredCodeArea->getCenterLongitude() - $referenceLongitude;
        if ($longitudeDiff > $prefixPrecision / 2) {
            $recoveredLongitude -= $prefixPrecision;
        } elseif ($longitudeDiff < -$prefixPrecision / 2) {
            $recoveredLongitude += $prefixPrecision;
        }

        return self::createFromCoordinates($recoveredLatitude, $recoveredLongitude, strlen($recovered->code) - 1);
    }

    /**
     * Returns whether the bounding box specified by the Open Location Code contains the provided point.
     * 
     * @see CodeArea::contains() for the underlying implementation.
     * 
     * @param float $latitude The provided latitude in degrees.
     * @param float $longitude The provided longitude in degrees.
     * @return bool True if the coordinates are contained by the code.
     */
    public function contains(float $latitude, float $longitude): bool
    {
        $codeArea = $this->decode();
        return $codeArea->contains($latitude, $longitude);
    }

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

    /**
     * Returns whether this object is a full Open Lcation Code.
     * @return bool True if it is a full code.
     */
    public function isFull(): bool
    {
        return strpos($this->code, self::SEPARATOR) == self::SEPARATOR_POSITION;
    }

    /**
     * Returns whether this bject is a short Open Location Code.
     * @return bool True if it is a short code.
     */
    public function isShort(): bool
    {
        $separatorPosition = strpos($this->code, self::SEPARATOR);
        return $separatorPosition !== false && $separatorPosition < self::SEPARATOR_POSITION;
    }

    /**
     * Returns whether this object is a padded Open Location Coe, meaning that
     * it conntains less than 8 valid digits.
     * @return bool True if this code is padded.
     */
    public function isPadded(): bool
    {
        return str_contains($this->code, self::PADDING_CHARACTER);
    }

    // ---

    // internal static methods

    private static function clipLatitude(float $latitude): float
    {
        return min(max($latitude, -self::LATITUDE_MAX), self::LATITUDE_MAX);
    }

    private static function normalizeLongitude(float $longitude): float
    {
        if ($longitude >= -self::LONGITUDE_MAX && $longitude < self::LONGITUDE_MAX) {
            return $longitude;
        }

        // fmod() in PHP uses floored division where the remainder is always positive
        // to indicate the positive offset from the dividend.
        // Therefore, we can normalize any input longitude according to these steps:
        // 1. Shift periodical [-180, 180) to become periodical [0, 360)
        // 2. Apply fmod(?, 360) to remove the periodicity
        // 3. Shift [0, 360) to [-180, 180)
        // 4. Normalization complete
        return fmod($longitude + self::LONGITUDE_MAX, self::CIRCLE_DEG) - self::LONGITUDE_MAX;
    }

    /**
     * Compute the latitude precision value for a given code length. Lengths <= 10 have the same
     * precision for latitude and longitude, but lengths > 10 have different precisions due to the
     * grid method having fewer columns than rows. Copied from the JS implementation.
     */
    private static function computeLatitudePrecision(int $codeLength): float
    {
        if ($codeLength <= self::CODE_PRECISION_NORMAL) {
            return pow(self::ENCODING_BASE, intdiv($codeLength, -2) + 2);
        }
        return pow(self::ENCODING_BASE, -3) / pow(self::GRID_ROWS, $codeLength - self::PAIR_CODE_LENGTH);
    }
}
