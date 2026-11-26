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
        $this->confirmCoordinatesValidity($resultCodeArea->northLatitude, $resultCodeArea->eastLongitude);
        $this->confirmCoordinatesValidity($resultCodeArea->southLatitude, $resultCodeArea->westLongitude);
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

    #[DataProvider('decodingProvider')]
    public function testCorrectCodeAreaFromCode(string $code, int $expectedLength, float $latLo, float $lngLo, float $latHi, float $lngHi): void
    {
        $calculators = [new CodeCalculatorFloat()];
        if (PHP_INT_SIZE >= 8) {
            $calculators[] = new CodeCalculatorInt();
        }
        foreach ($calculators as $calculator) {
            $area = $calculator->decode($code);
            $message = "Decoding $code with " . $calculator::class . " is incorrect.";
            $this->assertEquals($expectedLength, $area->length, $message);
            $this->assertEqualsWithDelta($latLo, $area->southLatitude, 1e-10, $message);
            $this->assertEqualsWithDelta($lngLo, $area->westLongitude, 1e-10, $message);
            $this->assertEqualsWithDelta($latHi, $area->northLatitude, 1e-10, $message);
            $this->assertEqualsWithDelta($lngHi, $area->eastLongitude, 1e-10, $message);
        }
    }

    private function confirmCoordinatesValidity(float $latitude, float $longitude): void
    {
        // check latitude
        $this->assertGreaterThanOrEqual(-90, $latitude);
        $this->assertLessThanOrEqual(90, $latitude);
        // check longitude
        $this->assertGreaterThanOrEqual(-180, $longitude);
        $this->assertLessThanOrEqual(180, $longitude);
    }

    public static function codeValidityProvider(): array
    {
        return [
            "Null code" => [null, false],
            "Empty code" => ["", false],

            "Code strlen=1" => ["5", false],
            "Code strlen=2" => ["55", false],
            "Code strlen=3" => ["555", false],
            "Code strlen=4" => ["5555", false],
            "Code strlen=5" => ["55555", false],
            "Code strlen=6" => ["555555", false],
            "Code strlen=7" => ["5555555", false],
            "Code strlen=8 (no plus)" => ["55555555", false],
            "London King's Cross, London (strlen=8)" => ["9C3XGVJG+", true],
            "Code strlen=9" => ["9C3XGVJG+8", false],
            "London King's Cross, London" => ["9C3XGVJG+8F", true],

            "Code strlen=11" => ["88888888+888", true],
            "Code strlen=12" => ["88888888+8888", true],
            "Code strlen=13" => ["88888888+88888", true],
            "Code strlen=14" => ["88888888+888888", true],
            "Code strlen=15" => ["88888888+8888888", true],
            "Code strlen=16" => ["88888888+88888888", false],
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
            "New Chitose Airport, Chitose" => [42.786062, 141.680937, "8RJ3QMPJ+C9"],
            "Berling Strait" => [65.759937, -169.149437, "92QGQV52+X6"],
            "Null point" => [0, 0, "6FG22222+22"],
        ];
    }

    public static function decodingProvider(): array
    {
        // code, length, latitude low, longitude low, latitude high, longitude high
        // (from test_data/decoding.csv of the reference implementation)
        return [
            "10 digits" => ["7FG49QCJ+2V", 10, 20.37, 2.782125, 20.370125, 2.78225],
            "Padded" => ["7FG49Q00+", 6, 20.35, 2.75, 20.4, 2.8],
            "11 digits" => ["7FG49QCJ+2VX", 11, 20.3701, 2.78221875, 20.370125, 2.78225],
            "13 digits" => ["7FG49QCJ+2VXGJ", 13, 20.370113, 2.782234375, 20.370114, 2.78223632813],
            "11 digits near null point" => ["6FH32222+222", 11, 1, 1, 1.000025, 1.00003125],
            "14 digits" => ["8J7GRCCX+FMJWRQ", 14, 35.8212246, 70.449142089844, 35.8212248, 70.449142578125],
            "15 digits" => ["849VGJQF+VX7QR3J", 15, 37.5396691200, -122.3750698242, 37.5396691600, -122.3750697021],
            "More than 15 digits" => ["849VGJQF+VX7QR3J7QR3J", 15, 37.5396691200, -122.3750698242, 37.5396691600, -122.3750697021],
        ];
    }
}
