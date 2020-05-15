# Change log

## 1.5.0-beta - 2020-05-15
### Fixed
* Fix message level (debug, info, warning, error) translating to sentry.
* Fix message scope. For now every message has own scope and not affect to other.
* Fix adding additional data (extra context, user data) for exception messages.
### Changed
* Sentry init will be invoking at application start, and not before log export started.
### Added
* Log user ID and IP, if available.
* Added ability to add own context data for messages scope.

## 1.4.2 - 2020-01-21
### Fixed
* Array export fix if text not contains message key.

## 1.4.1 - 2019-11-06
### Fixed
* Fix passing client options to sentry.
* Fix error with undefined index.
* Fix bug with sets extra values.

## 1.4.0 - 2019-09-27
### Changed
Used sentry/sdk 2.0 package.

### Fixed
Fixed error with `extraCallback` property.

## 1.3.0 - 2017-08-24
### Changed
* Exception handling refactoring.
* Unsupported HHMV.

## 1.2.1 - 2017-01-28
### Added
* Unit tests.
* Change log.

## 1.2.0 - 2016-11-30
### Added
* Added supporting tags in messages.

## 1.1.2 - 2016-10-03
### Fixed
* Checking context on instance of `\Throwable`.
* New URL of the Sentry website.

## 1.1.1 - 2016-09-05
### Changed
* New name of the Sentry composer package.

## 1.1.0 - 2016-04-08
### Added
* `extraCallback` property in configuration for modify extra's data.

## 1.0.0 - 2016-01-04
Hello, World!
