# MoMo setup and operating notes

MoMo is implemented but disabled by default. No merchant credentials have been saved and no real provider transaction has been sent during development.

## Configuration

1. Apply the new `2026_10_08_000001_create_momo_payment_attempts` migration with the normal deployment procedure. Local MySQL was unavailable; development verification used isolated SQLite migrations.
2. Put merchant values in the deployment secret store or untracked `.env`: `MOMO_PARTNER_CODE`, `MOMO_ACCESS_KEY`, and `MOMO_SECRET_KEY`. Do not commit them or paste them into a chat.
3. Set `MOMO_BUSINESS_ID` to the business owned by that merchant account. This implementation enables one configured business. It must not collect unrelated tenants' money into the same merchant account.
4. Set `APP_URL` to the public HTTPS application origin. MoMo must reach `/payments/momo/ipn`; loopback cannot receive provider callbacks.
5. Start with `MOMO_ENVIRONMENT=sandbox` and `MOMO_ENABLED=true`. Complete sandbox acceptance: callback delivery, success/failure, timeouts, and reconciliation. Production uses `MOMO_ENVIRONMENT=production` with that environment's merchant credentials. Rebuild Laravel configuration cache when settings change.

## Payment behavior

Customers pay the remaining balance after salon confirmation. The server computes the amount, enforcing whole-VND amounts and provider limits. No deposit percentage has been invented. Browser return parameters do not mark an order paid.

Each attempt reserves a PENDING payment before contacting MoMo. Repeated checkout resumes that attempt. Manual collection is blocked while an unresolved payment exists. Timeouts stay pending until a verified callback or explicit reconciliation resolves them.

The IPN endpoint validates the original JSON signature and matches partner, request, order, amount, and order information. Only this route is excluded from CSRF. Received/refunded transactions cannot be downgraded by late failures. Money received after appointment cancellation is recorded for refund review; the appointment is not reopened.

Customers and authorized salon staff can use **Cập nhật trạng thái MoMo** on pending transactions. Status queries use authenticated requests to the configured provider environment with response binding. Unknown/nonfinal codes stay pending and never trigger another collection automatically. Operational reconciliation is needed if the provider cannot establish a definitive result.

MoMo refunds are not automated. The salon must refund in its merchant portal, then record the confirmed reference through the existing authorized review flow. A reference is required; recording it does not send money.

## Verification and references

`tests/Feature/MomoTest.php` covers server-calculated amounts, repeated checkout, signature/amount binding, replay and downgrade protection, timeout reservations, manual recollection blocking, reconciliation, late success after cancellation, tenant/ownership checks, and redirect allowlisting. Provider responses are simulated. Live sandbox acceptance remains required.

Official contracts: [one-time wallet payment](https://developers.momo.vn/v3/docs/payment/api/wallet/onetime/), [payment notification](https://developers.momo.vn/v3/docs/payment/api/result-handling/notification/), [transaction query](https://developers.momo.vn/v3/docs/payment/api/payment-api/query/), and [result codes](https://developers.momo.vn/v3/docs/payment/api/result-handling/resultcode/).
