<?php

namespace Vectorial1024\OpenLocationCodePhp;

/**
 * Coordinates of a decoded Open Location Code.
 * 
 * The coordinates include the latitude and longitude of the lower left and upper right corners
 * and the center of the bounding box for the area the code represents.
 */
readonly class CodeArea
{
    public function __construct(
        public float $southLatitude,
        public float $westLongitude,
        public float $northLatitude,
        public float $eastLongitude,
        public int $length,
    ) {
    }

    public function getLatitudeHeight(): float
    {
        return $this->northLatitude - $this->southLatitude;
    }

    public function getLongitudeWidth(): float
    {
        return $this->eastLongitude - $this->westLongitude;
    }

    public function getCenterLatitude(): float
    {
        return ($this->northLatitude + $this->southLatitude) / 2;
    }

    public function getCenterLongitude(): float
    {
        return ($this->eastLongitude + $this->westLongitude) / 2;
    }

    /**
     * Returns whether the bounding box specified by the code area (from a decoded Open Location Code) contains the provided point.
     * 
     * @param float $latitude The provided latitude in degrees.
     * @param float $longitude The provided longitude in degrees.
     * @return bool True if tge coordinates are contained by the code area.
     */
    public function contains(float $latitude, float $longitude): bool
    {
        return $this->southLatitude <= $latitude
            && $latitude < $this->northLatitude
            && $this->westLongitude <= $longitude
            && $longitude < $this->eastLongitude;
    }
}
