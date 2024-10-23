<?php

namespace Vectorial1024\OpenLocationCodePhp\Test;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vectorial1024\OpenLocationCodePhp\CodeCalculator\CodeCalculatorFloat;
use Vectorial1024\OpenLocationCodePhp\CodeCalculator\CodeCalculatorInt;
use Vectorial1024\OpenLocationCodePhp\OpenLocationCode;

class OpenLocationCodeTest extends TestCase
{
    public function testCorrectConstants(): void
    {
        $this->assertEquals(OpenLocationCode::ENCODING_BASE, strlen(OpenLocationCode::CODE_ALPHABET));
    }

    #[DataProvider('codeValidityProvider')]
    public function testCorrectCodeValidity(?string $testCode, bool $expectedValidity): void
    {
        $message = "The validity of the code " . ($testCode ?? "(null)") . " is incorrect.";
        $isValid = OpenLocationCode::isValidCode($testCode);
        $this->assertEquals($expectedValidity, $isValid, $message);

        // also tests the behavior of code creation because it is highly relevant.
        if ($isValid) {
            $theObject = OpenLocationCode::createFromCode($testCode);
            $this->assertTrue($theObject->isValid());
        } else {
            $this->expectException(InvalidArgumentException::class);
            $theObject = OpenLocationCode::createFromCode($testCode);
        }
    }

    #[DataProvider('encodingProvider')]
    public function testCorrectCodeFromCoordinates(float $latitude, float $longitude, string $expectedCode)
    {
        $codeObject = OpenLocationCode::createFromCoordinates($latitude, $longitude);
        $this->assertEquals($expectedCode, $codeObject->code);
        // while OLC represents an area via lossful encoding, at least the area should contain the original point
        $this->assertTrue($codeObject->contains($latitude, $longitude));

        // for convenience, also test the code calculators here
        $floatCal = new CodeCalculatorFloat();
        $resultCode = $floatCal->encode($latitude, $longitude, OpenLocationCode::CODE_PRECISION_NORMAL);
        $this->assertEquals($resultCode, $expectedCode);
        $resultCodeArea = $floatCal->decode($expectedCode);
        $this->assertTrue($resultCodeArea->contains($latitude, $longitude));
        if (PHP_INT_SIZE >= 8) {
            // at least 64-bit, which means we can use "long" ints here
            $intCal = new CodeCalculatorInt();
            $resultCode = $intCal->encode($latitude, $longitude, OpenLocationCode::CODE_PRECISION_NORMAL);
            $this->assertEquals($resultCode, $expectedCode);
            $resultCodeArea = $intCal->decode($expectedCode);
            $this->assertTrue($resultCodeArea->contains($latitude, $longitude));
        }
    }

    public static function codeValidityProvider(): array
    {
        return [
            "Null code" => [null, false],
            "Empty code" => ["", false],
            "Code too short" => ["B", false],

            "London King's Cross, London" => ["9C3XGVJG+8F", true],
        ];
    }

    public static function encodingProvider(): array
    {
        // latitude, longitude, expected code (check the external demo)
        return [
            "London King's Cross, London" => [51.530812, -0.123767, "9C3XGVJG+8F"],
            "Changi Airport, Singapore" => [1.357063, 103.988563, "6PH59X4Q+RC"],
            "International Antarctic Centre, Christchurch" => [-43.489063, 172.547188, "4V8JGG6W+9V"],
            "Christo Redentor, Rio de Janeiro" => [-22.951937, -43.210437, "589R2QXQ+6R"],
            "New Chitose Airport, Chitose" => [42.786062,141.680937, "8RJ3QMPJ+C9"],
            "Berling Strait" => [65.759937, -169.149437, "92QGQV52+X6"],
            "Null point" => [0, 0, "6FG22222+22"],
        ];
    }
}
