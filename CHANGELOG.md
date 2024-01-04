## 1.5.0

- Add HPOS compatibility

## 1.4.7

- fix: make sure phone and email billing fields can be updated

## 1.4.6

- fix: change to woocommerce_process_shop_order_meta hook
- chore: remove redundant hack that prevented multiple requests from being sent
- fix: pick order meta data from request instead of database to make sure we get the values that are to be updated.

## 1.4.5

- Fix: Make sure WordPress is installed with fixed version in Docker setup
- Chore: Remove order meta data validation (moved to checkout plugin)

## 1.4.4

- Fix: Change to use exclusive vat mode

## 1.4.3

- Fix: Don't update woo state on cancel failures

## 1.4.2

- Fix: wp review fixes - sanitize_text_field on user input texts
- Fix: configurable error status mapping

## 1.4.1

- Fix: prefixed public functions with `lom_`

## 1.4.0

- Feat: Validation of customer billing + shipping address
- Feat: Make custom address fields editable

## 1.3.0

- Feat: Partial refund support
- Fix: Adjusted order edit
- Fix: Coupon and giftcard adjustments

## 1.2.0

- Fixes for update order / capture

## 1.1.0

- Support for update order

## 1.0.0

- Initial project setup
