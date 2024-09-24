<?php

namespace Vectorial1024\OpenLocationCodePhp\Test;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vectorial1024\OpenLocationCodePhp\OpenLocationCode;

class OpenLocationCodeTest extends TestCase
{
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

    public function testCorrectCodeFromCoordinates()
    {
        // test King's Cross for now; later may expand to see more test cases
        $kingsCrossLat = 51.530812;
        $kingsCrossLng = -0.123767;
        $kingsCrossCode = "9C3XGVJG+8F";

        $codeObject = OpenLocationCode::createFromCoordinates($kingsCrossLat, $kingsCrossLng);
        $this->assertEquals($kingsCrossCode, $codeObject->code);
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
}
