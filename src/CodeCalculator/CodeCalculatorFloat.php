<?php

namespace Vectorial1024\OpenLocationCodePhp\CodeCalculator;

use Vectorial1024\OpenLocationCodePhp\CodeArea;
use Vectorial1024\OpenLocationCodePhp\OpenLocationCode;

/**
 * A Open Location Code calculator that uses float.
 * As such, this is usable on any PHP version.
 */
class CodeCalculatorFloat extends AbstractCodeCalculator
{
    protected function generateRevOlcCode(float $latitude, float $longitude, int $codeLength): string
    {
        // todo
        return "";
    }

    public function decode(string $code): CodeArea
    {
        // todo
        return new CodeArea(0, 0, 0, 0, 0);
    }
}
