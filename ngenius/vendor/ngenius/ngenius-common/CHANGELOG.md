# Changelog

All notable changes to this project will be documented in this file.

## [v1.0.12] - 2024-08-26

- Fix Value Formatter amount conversions with comma separators.

## [v1.0.11] - 2024-08-05

- Add Transaction Processor class. 

## [v1.0.10] - 2024-07-01

- Introduce new floating-point number formatting.
- Fix computational errors involved in floating-point arithmetic.
- Add `isPaymentAbandoned` method for `ApiProcessor` class.

## [v1.0.9] - 2024-05-16

- Add `isPaymentConfirmed` method for `ApiProcessor` class.
- Improve speed of `placeRequest` method for `NgeniusHTTPCommon` class.

## [v1.0.8] - 2024-03-18

- Add `processPaymentAction` method to the `ApiProcessor` class, which sets a SALE action as a PURCHASE action for CUP
  payments.

## [v1.0.7] - 2024-01-16

- Add the ability to get a country telephone prefix using a country code.

## [v1.0.6] - 2023-11-27

- Add amount formatter for currency decimal places.
- Improve refund processor support.

## [v1.0.5] - 2023-11-17

- Add `Array` to the last transaction return type.

## [v1.0.4] - 2023-11-16

- Add API and Refund processor.

## [v1.0.3] - 2023-11-01

- Add `formatOrderStatusAmount` and `formatCurrencyAmount`.

## [v1.0.2] - 2023-06-26

- Add `__construct` method.

## [v1.0.1] - 2023-06-13

- Amend `build` method.

## [v1.0.0] - 2023-04-12

- Initial commit.
