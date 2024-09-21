<?php

namespace Vectorial1024\OpenLocationCodePhp\Test;

use PHPUnit\Framework\TestCase;
use Vectorial1024\OpenLocationCodePhp\CodeArea;

class CodeAreaTest extends TestCase
{
    /**
     * A dummy CodeArea for testing; specifying the rectangular area of (0, 3) to (4, 8).
     * @var CodeArea
     */
    private CodeArea $theArea;

    public function setUp(): void
    {
        $this->theArea = new CodeArea(0, 3, 4, 8, 5);
    }

    public function testCorrectLatitudeHeight(): void
    {
        $this->assertEquals(4, $this->theArea->getLatitudeHeight());
    }

    public function testCorrectLongitudeWidth(): void
    {
        $this->assertEquals(5, $this->theArea->getLongitudeWidth());
    }

    public function testCorrectCenterCoordinates(): void
    {
        $this->assertEquals(2, $this->theArea->getCenterLatitude());
        $this->assertEquals(5.5, $this->theArea->getCenterLongitude());
    }
}
