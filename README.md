# open-location-code-php
This is a modern PHP port of the `google/open-location-code` repository. Please visit https://github.com/google/open-location-code for discussion and up-to-date information about Open Location Code itself. This repository only concerns the PHP implementation.

But still, a short quote from said repository to introduce Open Location Code (OLC):

> Open Location Code is a technology that gives a way of encoding location into a form that is easier to use than latitude and longitude. The codes generated are called plus codes, as their distinguishing attribute is that they include a "+" character.

An [external demo](https://plus.codes/map) is available to showcase the functionality of Open Lcation Code.

## Implementation notes
This PHP implementation is adapted from the [Java implementation](https://github.com/google/open-location-code/tree/main/java) of `google/open-location-code`.

This implementation tries to use latest PHP features where possible.

Licence follows `google/open-location-code`.

## Installation
via Composer:

(WIP)

Special notice: this library requires a 64-bit PHP runtime because some Open Location Code calculation may exceed the 32-bit integer limit. (Subject to review.)

## Example code
(WIP)

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
```

### Other references

## Testing
via PHPUnit; first ensure PHPUnit has been set up correctly:

```sh
composer install
```

Then:

```sh
./vendor/bin/phpunit test
```
