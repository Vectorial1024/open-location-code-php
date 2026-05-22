# Change log of `open-location-code-php`

Note: you may refer to `README.md` for description of features.

## Dev (WIP)

## 1.1.4 (2026-05-22)

Fixed decoding of OLC producing out-of-bounds coordinates (https://github.com/Vectorial1024/open-location-code-php/pull/6).

## 1.1.3 (2026-04-21)

GitHub security advisory (https://github.com/advisories/GHSA-qrr6-mg7r-m243)

## 1.1.2 (2024-10-29)

Hotfix: removed `composer.lock` from distribution (ba2014fc551dbfd2456eadb4f9e31ed5f80671a6)

## 1.1.1 (2024-10-23)

Hotfix: the code calculators are now `final` (b52ff47a044a12bbe364856c7f3fbf8e0ced4aa3)

## 1.1.0 (2024-10-23)

Minor update to the library.

- Added a new way to efficiently check OLC area containment for many points (https://github.com/Vectorial1024/open-location-code-php/pull/3)
- 32-bit compatibility is now automatically applied to only 32-bit PHP runtime (https://github.com/Vectorial1024/open-location-code-php/pull/4)
- Fixed a minor PHPDoc error

## 1.0.0 (2024-10-04)

Initial release.

This is a modern PHP port of the `google/open-location-code` repository to handle Open Location Codes (aka Plus Codes).

- Uses PHP objects to handle Open Location Code
- OLC decoding returns an object for easier handling
