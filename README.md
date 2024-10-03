# open-location-code-php
![Packagist License](https://img.shields.io/packagist/l/vectorial1024/open-location-code-php?style=plastic)
![Packagist Version](https://img.shields.io/packagist/v/vectorial1024/open-location-code-php?style=plastic)
![Packagist Downloads](https://img.shields.io/packagist/dm/vectorial1024/open-location-code-php?style=plastic)
![PHP Dependency Version](https://img.shields.io/packagist/dependency-v/vectorial1024/open-location-code-php/php?style=plastic&label=PHP)

This is a modern PHP port of the `google/open-location-code` repository. Please visit https://github.com/google/open-location-code for discussion and up-to-date information about **Open Location Code ("OLC", aka "Plus Code")** itself. This repository only concerns the PHP implementation.

But still, a short quote from said repository to introduce Open Location Code (OLC):

> Open Location Code is a technology that gives a way of encoding location into a form that is easier to use than latitude and longitude. The codes generated are called plus codes, as their distinguishing attribute is that they include a "+" character.

An [external demo](https://plus.codes/map) is available to showcase the functionality of Open Lcation Code.

## Implementation notes
This PHP implementation is adapted from the [Java implementation](https://github.com/google/open-location-code/tree/main/java) of `google/open-location-code`.

This implementation tries to use latest PHP features where possible.

Licence follows `google/open-location-code`.

## Installation
via Composer:

```sh
composer require vectorial1024/open-location-code-php
```

**Special Notice**: to ensure 32-bit PHP compatibility, this library uses `float` variables instead of `int` variables to calculate Open Location Codes.
This may cause inaccuracies in some unforseen edge cases, but generally speaking, there should be no problems.

## Example code

### Checking and creating OLC codes

```php
use Vectorial1024\OpenLocationCodePhp\OpenLocationCode;

// This code snippet will center around King's Cross station in London, UK.
// Its main entrance at (51.530812, -0.123767) has the Open Location Code of "9C3XGVJG+8F".

$invalidCode = "11iL!Illi1!!!";

$kingsCrossLatitude = 51.530812;
$kingsCrossLongitude = -0.123767;
$kingsCrossCode = "9C3XGVJG+8F";

// test validity of the code
$validity = OpenLocationCode::isValidCode($invalidCode); // invalid code; returns false
$validity = OpenLocationCode::isValidCode($kingsCrossCode); // valid code; returns true

// create object from code
$invalidObject = OpenLocationCode::createFromCode($invalidCode); // invalid code; throws InvalidArgumentException
$validObject = OpenLocationCode::createFromCode($kingsCrossCode); // returns OpenLocationCode instance
// alternatively, create object from coordinates
$anotherValidObject = OpenLocationCode::createFromCoordinates($kingsCrossLatitude, $kingsCrossLongitude); // returns OpenLocationCode instance

// you may check the code is valid
$validity = $validObject->isValid(); // returns true
$validity = $anotherValidObject->isValid(); // also returns true
// you may also read the code...
assert($kingsCrossCode == $validObject->code); // passes
$code = (string) $validObject; // also, can be explicitly casted to string
assert($kingsCrossCode == $code); // passes
// ...to know that both methods result in the same code
assert($validObject->code == $anotherValidObject->code); // also passes
// but you may not modify the code (create a new instance instead!)
$validObject->code = "something else"; // PHP runtime error: $code is read-only
```

### Other references

A quick reference of available classes/methods; please see the PHPDoc for details.

**OpenLocationCode**:

```php
/* Vectorial1024\OpenLocationCodePhp\OpenLocationCode */

class OpenLocationCode implememts Stringable
{
    public readonly string $code;
    // The explicit string cast gives $this->code;

    public const int CODE_PRECISION_NORMAL = 10;
    
    public static function createFromCode(string $code): self;
    public static function createFromCoordinates(float $latitude, float $longitude, int $codeLength = self::CODE_PRECISION_NORMAL): self;
    public static function encode(float $latitude, float $longitude, int $codeLength = self::CODE_PRECISION_NORMAL): string;
    public function decode(): Vectorial1024\OpenLocationCodePhp\CodeArea;

    public function shorten(float $referenceLatitude, float $referenceLongitude): self;
    public function recover(float $referenceLatitude, float $referenceLongitude): self;

    public function contains(float $latitude, float $longitude): bool;

    public static function isValidCode(string $code): bool;
    public function isValid(): bool;
    public function isFull(): bool;
    public function isShort(): bool;
    public function isPadded(): bool;
}
```

**CodeArea**:

```php
/* Vectorial1024\OpenLocationCodePhp\CodeArea */

class CodeArea
{
    public readonly float $southLatitude;
    public readonly float $westLongitude;
    public readonly float $northLatitude;
    public readonly float $eastLongitude;
    public readonly int $length;

    public function getLatitudeHeight(): float;
    public function getLongitudeWidth(): float;

    public function getCenterLatitude(): float;
    public function getCenterLongitude(): float;
}
```

## Testing
via PHPUnit; first ensure PHPUnit has been set up correctly:

```sh
composer update
```

Then:

```sh
composer run-script test
```
