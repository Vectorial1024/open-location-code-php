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
            $this->assertNotNull($theObject);
        } else {
            $this->expectException(InvalidArgumentException::class);
            $theObject = OpenLocationCode::createFromCode($testCode);
        }
    }

    public static function codeValidityProvider(): array
    {
        return [
            "Null code" => [null, false],
            "Empty code" => ["", false],
            "Code too short" => ["B", false],

            "London King's Cross, London" => ["9C3XGVJG+8FH", true],
        ];
    }
}
