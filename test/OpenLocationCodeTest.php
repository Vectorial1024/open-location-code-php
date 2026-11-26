<?php

namespace Vectorial1024\OpenLocationCodePhp\Test;

use InvalidArgumentException;
use LogicException;
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
            unset($theObject);
        }
    }

    #[DataProvider('encodingProvider')]
    public function testCorrectCodeFromCoordinates(float $latitude, float $longitude, string $expectedCode)
    {
        $codeObject = OpenLocationCode::createFromCoordinates($latitude, $longitude);
        $this->assertEquals($expectedCode, $codeObject->code);
        // while OLC represents an area via lossful encoding, at least the area should contain the original point
        $this->assertTrue($codeObject->contains($latitude, $longitude));
        // these codes are full
        $this->assertTrue($codeObject->isFull());

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

    #[DataProvider('paddingProvider')]
    public function testCorrectIsPadded(string $code, bool $expectedPadded): void
    {
        $olcObject = OpenLocationCode::createFromCode($code);
        $this->assertEquals($expectedPadded, $olcObject->isPadded());
    }

    #[DataProvider('encodingProvider')]
    public function testCorrectShortenAndRecover(float $latitude, float $longitude, string $expectedCode): void
    {
        // basically, shortening/recovering works by discovering that the reference point is in the same grid as the query point.
        $codeObject = OpenLocationCode::createFromCoordinates($latitude, $longitude);
        $this->assertEquals($expectedCode, $codeObject->code);

        // recovering from a full code is no-op
        $uselessRecovery = $codeObject->recover(0, 0);
        $this->assertEquals($expectedCode, $uselessRecovery);

        // we definitely cannot shorten with a faraway point
        $this->expectException(InvalidArgumentException::class);
        $codeObject->shorten(-90, -180);

        // self is always in the same grid as self
        $shortenedObject = $codeObject->shorten($latitude, $longitude);
        $this->assertTrue($shortenedObject->isShort());
        $this->assertFalse($shortenedObject->isPadded());
        $this->assertFalse($shortenedObject->isFull());
        // short codes cannot be decoded; use recover instead
        $this->expectException(LogicException::class);
        $shortenedObject->decode();
        // because of such closeness, we strip all 4 levels of code away
        $shortenedCode = (string) $shortenedObject;
        $this->assertTrue(str_contains($expectedCode, $shortenedCode));
        $this->assertTrue(strlen($shortenedCode) == 3);
        $this->assertTrue(str_starts_with($shortenedCode, '+'));
        // shortened codes cannot be shortened again
        $this->expectException(LogicException::class);
        $shortenedObject->shorten($latitude, $longitude);

        // self is always in the same grid as self
        $recoveredObject = $codeObject->recover($latitude, $longitude);
        $recoveredCode = (string) $recoveredObject;
        $this->assertEquals($expectedCode, $recoveredCode);

        // using hardcoded precision values (see OLC private static method), test partial shortening
        $precisions = [0.05, 1, 20];
        foreach ($precisions as $index => $precision) {
            // shorten it
            $lenLoss = ($index + 1) * 2;
            $flooredLatitude = floor($latitude / $precision) * $precision;
            $flooredLongitude = floor($longitude / $precision) * $precision;
            $innerShortenedObject = $codeObject->shorten($flooredLatitude, $flooredLongitude);
            $this->assertTrue($innerShortenedObject->isShort());
            $innerShortenedCode = (string) $innerShortenedObject;
            $this->assertTrue(str_contains($expectedCode, $innerShortenedCode));
            $this->assertTrue(strlen($innerShortenedCode) == strlen($expectedCode) - $lenLoss);

            // recover it
            $innerRecoveredObject = $codeObject->recover($flooredLatitude, $flooredLongitude);
            $innerRecoveredCode = (string) $innerRecoveredObject;
            $this->assertEquals($expectedCode, $innerRecoveredCode);
        }
    }

    #[DataProvider('outOfBoundsRecoveryProvider')]
    public function testCorrectRecoverWithOutOfBoundsReference(string $shortCode, float $latitude, float $longitude, string $expectedCode): void
    {
        $recoveredCode = (string) OpenLocationCode::createFromCode($shortCode)->recover($latitude, $longitude);
        $this->assertEquals($expectedCode, $recoveredCode);
    }

    #[DataProvider('outOfBoundsEncodingProvider')]
    public function testCorrectCodeFromOutOfBoundsLongitude(float $latitude, float $longitude, string $expectedCode): void
    {
        $this->assertEquals($expectedCode, OpenLocationCode::encode($latitude, $longitude));
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
            "London King's Cross, London (plus, len=8)" => ["9C3XGVJG+", true],
            "Code strlen=9" => ["9C3XGVJG8", false],
            "Code plus, len=9" => ["9C3XGVJG+8", false],
            "Code strlen=10" => ["9C3XGVJG8F", false],
            "London King's Cross, London" => ["9C3XGVJG+8F", true],

            "Code len=11" => ["88888888+888", true],
            "Code len=12" => ["88888888+8888", true],
            "Code len=13" => ["88888888+88888", true],
            "Code len=14" => ["88888888+888888", true],
            "Code len=15" => ["88888888+8888888", true],
            "Code len=16" => ["88888888+88888888", false],

            "Padded code len=0" => ["00000000+", false],
            "Padded code len=1" => ["40000000+", false],
            "Padded code len=2" => ["44000000+", true],
            "Padded code len=3" => ["44400000+", false],
            "Padded code len=4" => ["44440000+", true],
            "Padded code len=5" => ["44444000+", false],
            "Padded code len=6" => ["44444400+", true],
            "Padded code len=7" => ["44444440+", false],
            "Padded code len=8" => ["44444444+", true],

            "Illegal alphabet" => ["A1A1A1A1+A1", false],
            "Illegal character" => ["9C3XGVJ1+8F", false],
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

    public static function outOfBoundsRecoveryProvider(): array
    {
        // short code, reference latitude, reference longitude, expected full code
        // (checked against the Python reference implementation)
        return [
            "Reference longitude 180" => ["+8MFH", 68.927696, 180, "9VWXWXHX+8MFH"],
            "Reference longitude -180" => ["5MQ3HF+6CW7", -26.223277, -180, "5V5MQ3HF+6CW7"],
            "Nearest match across the antimeridian (west)" => ["MJ65HR+RQ43", -56.770703, -179.99, "3VMJ65HR+RQ43"],
            "Nearest match across the antimeridian (east)" => ["CX+22", 0, 179.99, "6VGX2XCX+22"],
            "Reference latitude 91, longitude -200" => ["2C+22", 91, -200, "CVX2X22C+22"],
            "Reference longitude -360" => ["C2+22", -45, -360, "4F7222C2+22"],
            "Reference latitude 90, longitude 540" => ["XQ+XX", 90, 540, "CVXXXXXQ+XX"],
            "Reference latitude 135, longitude -540.5" => ["XQ+XX", 135, -540.5, "CVXXXFXQ+XX"],
            "Reference latitude -120, longitude -180.0001" => ["22+22", -120, -180.0001, "22222222+22"],
        ];
    }

    public static function outOfBoundsEncodingProvider(): array
    {
        // latitude, longitude, expected code (checked against the Python reference implementation)
        return [
            "Longitude -360" => [0, -360, "6FG22222+22"],
            "Longitude -200" => [0, -200, "6VG22222+22"],
            "Longitude -540.5" => [0, -540.5, "6VGX2G22+22"],
            "Longitude -180.0001" => [10, -180.0001, "7V2X2X2X+2X"],
            "Longitude 540" => [0, 540, "62G22222+22"],
        ];
    }

    public static function paddingProvider(): array
    {
        // code, isPadded
        // (skips invalid codes; invalid codes are guarded and cannot be instantiated)
        return [
            "Padded len=2" => ["77000000+", true],
            "Padded len=4" => ["77770000+", true],
            "Padded len=6" => ["77777700+", true],
            "Padded len=8" => ["77777777+", false],
            "Padded len=10" => ["77777777+77", false],
            "Padded len=11" => ["77777777+777", false],
            "Padded len=12" => ["77777777+7777", false],
            "Padded len=13" => ["77777777+77777", false],
            "Padded len=14" => ["77777777+777777", false],
            "Padded len=15" => ["77777777+7777777", false],
        ];
    }
}
