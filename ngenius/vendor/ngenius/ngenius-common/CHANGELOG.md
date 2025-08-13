# Changelog

All notable changes to this project will be documented in this file.

## [[v1.3.0]](https://github.com/network-international/ngenius-common/releases/tag/v1.3.0)

- Added invoice API compatibility.

## [[v1.2.1]](https://github.com/network-international/ngenius-common/releases/tag/v1.2.1)

- Improved error handling for order status queries with missing values.

## [[v1.2.0]](https://github.com/network-international/ngenius-common/releases/tag/v1.2.0)

- Upgraded the request handler to GuzzleHTTP.

## [[v1.1.0]](https://github.com/network-international/ngenius-common/releases/tag/v1.1.0)

- Allow customisable order statuses.

## [[v1.0.12]](https://github.com/network-international/ngenius-common/releases/tag/v1.0.12)

- Fix Value Formatter amount conversions with comma separators.

## [[v1.0.11]](https://github.com/network-international/ngenius-common/releases/tag/v1.0.11)

- Add Transaction Processor class.

## [[v1.0.10]](https://github.com/network-international/ngenius-common/releases/tag/v1.0.10)

- Introduce new floating-point number formatting.
- Fix computational errors involved in floating-point arithmetic.
- Add `isPaymentAbandoned` method for `ApiProcessor` class.

## [[v1.0.9]](https://github.com/network-international/ngenius-common/releases/tag/v1.0.9)

- Add `isPaymentConfirmed` method for `ApiProcessor` class.
- Improve speed of `placeRequest` method for `NgeniusHTTPCommon` class.

## [[v1.0.8]](https://github.com/network-international/ngenius-common/releases/tag/v1.0.8)

- Add `processPaymentAction` method to the `ApiProcessor` class, which sets a SALE action as a PURCHASE action for CUP
  payments.

## [[v1.0.7]](https://github.com/network-international/ngenius-common/releases/tag/v1.0.7)

- Add the ability to get a country telephone prefix using a country code.

## [[v1.0.6]](https://github.com/network-international/ngenius-common/releases/tag/v1.0.6)

- Add amount formatter for currency decimal places.
- Improve refund processor support.

## [[v1.0.5]](https://github.com/network-international/ngenius-common/releases/tag/v1.0.5)

- Add `Array` to the last transaction return type.

## [[v1.0.4]](https://github.com/network-international/ngenius-common/releases/tag/v1.0.4)

- Add API and Refund processor.

## [[v1.0.3]](https://github.com/network-international/ngenius-common/releases/tag/v1.0.3)

- Add `formatOrderStatusAmount` and `formatCurrencyAmount`.

## [[v1.0.2]](https://github.com/network-international/ngenius-common/releases/tag/v1.0.2)

- Add `__construct` method.

## [[v1.0.1]](https://github.com/network-international/ngenius-common/releases/tag/v1.0.1)

- Amend `build` method.

## [[v1.0.0]](https://github.com/network-international/ngenius-common/releases/tag/v1.0.0)

- Initial commit.
