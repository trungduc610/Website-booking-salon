# GlowBook interface redesign

## Visual system

The interface uses warm ivory, forest/sage actions, terracotta editorial accents, and softly outlined surfaces. `tailwind.config.js` exposes the requested palette, typography, shadows, and radius tokens. The invalid five-digit ivory value in the brief is normalized to `#FAFAF9`.

Noto Serif Variable and Be Vietnam Pro are served locally with Vietnamese and Latin subsets. This avoids broken Vietnamese diacritics and keeps the application independent of font CDNs. Brand lettering retains Georgia. Font licenses are copied with the assets.

`public/css/app.css` defines semantic light/dark surface variables and the shared components used throughout the existing Blade application. Tailwind 3 utilities and the CSP-compatible Alpine 3 build are compiled/copied locally. Theme initialization uses an external script, and timeline positioning uses validated DOM style properties, preserving the existing no-inline-script/no-eval policy. Bootstrap remains as a compatibility layer for the established management forms; this avoids breaking their grids while the shared components establish the new visual language. The installed Laravel 13 application is preserved; it was not downgraded to Laravel 11.

## Implemented journeys

- Public editorial landing page, branch cards, and server-side search across name, address, and business, including pagination and empty results.
- Four-stage booking wizard: service selection; specialist and time; personal details/voucher; confirmation. Service category pills, text search, multi-selection, live price/duration totals, seven-day date choices, server-validated half-hour slot suggestions, and availability loading/error states.
- Sticky desktop order summary and mobile bottom action bar with a native summary dialog. The mobile drawer supports its close button, Escape, and a downward swipe from its handle.
- Account portal with the next active upcoming appointment; appointment tickets, status labels, directions, cancellation confirmation, actual server-expiry countdown, and existing payment/refund workflows.
- Customer rescheduling retains the original specialist, services, prices, voucher reservation, and hold expiry. The server checks the branch's rescheduling deadline, staff capacity, opening hours, leave, and buffer rules.
- Operations navigation with a collapsible sidebar, appointment date/status filters, valid status transitions, a staff timeline, visible buffer regions, and drag-to-reschedule confirmation. A keyboard/touch-accessible Change Appointment button provides the same workflow.
- Operational counts, seven-day booking sparkline, gross money received today, and no-show percentage among completed/no-show appointments. Platform totals use actual database records. No fabricated statistics or growth claims.
- All existing account, authentication, catalog, staff, scheduling, voucher administration, and payment screens inherit the shared surfaces, type, controls, focus indicators, and dark mode.

## Interaction and trust decisions

1. Progressive disclosure keeps the booking task focused on one decision group at a time. A visible progress indicator reduces uncertainty about the remaining steps.
2. Persistent totals externalize the calculation, reducing memory demands as services are selected. Voucher deductions are explicitly pending until the server validates them.
3. One primary action per stage establishes a clear visual hierarchy. Back navigation preserves form state; native inputs and server validation remain available.
4. Statuses pair text with color so color is never the sole signal. Controls have visible focus treatments; primary controls and interactive service labels provide generous touch targets. Motion respects reduced-motion preferences.
5. A slot suggestion is a snapshot, not a reservation. Only a successful booking creates a real hold. The countdown uses that hold's actual expiry; it is not an artificial ten-minute urgency timer.
6. Rescheduling locks the branch and booking in the same order as booking creation, rejects stale original appointment details, and writes an audit note. The existing appointment remains unchanged when a proposed time conflicts.
7. The staff timeline is a page-load snapshot, refreshed by navigation/filtering. It does not claim to be a push-based real-time feed.

## Integration boundaries

The project records cash/bank-transfer payments through salon staff. A configuration-gated MoMo checkout now supports the remaining balance after salon confirmation, signed callbacks, and status reconciliation. It stays disabled until merchant credentials, business scope, public HTTPS, and sandbox acceptance are configured. See `docs/MOMO_SETUP.md`. No deposit percentage has been invented.

The following brief items are not implemented: a loyalty tier/rewards engine, a customer voucher discovery hub, Apple/Google Wallet passes, QR check-in, deposit-percentage rules, staff ratings/portfolio galleries, demand-ranking slot labels, and a capacity-utilization KPI. They require business rules, publication policy/data, or provider integration beyond the current application's models. No fake rewards, ratings, photos, or availability labels have been added. Loyalty rules await follow-up; MoMo is the selected provider. Voucher entry and the existing owner voucher administration remain functional.

## Build and verification

Run `npm ci` and `npm run build` to reproduce the local CSS, Alpine bundle, and font assets. `npm run check` validates the application JavaScript syntax.

Run the Laravel test suite with the configured PHP runtime and extensions. `tests/Feature/DesignExperienceTest.php` covers search, authenticated slots, expired holds, operational filters, platform authorization, and rendering. `tests/Feature/RescheduleTest.php` covers customer/staff moves, collisions, stale requests, expiry, authorization, snapshot preservation, and audit records.

For browser checks, set `GLOWBOOK_EXPORT_UI=1` while running `DesignExperienceTest`. This exports isolated SQLite test fixtures into the ignored `storage/app/ui-preview` directory. Then run `node scripts/check-ui.cjs`. This uses installed Microsoft Edge with Playwright, checks desktop/mobile widths and interactions, and saves screenshots under `storage/app/ui-preview/screenshots`. Slot/availability responses are mocked only for browser interaction checks; the real endpoint logic is tested in Laravel. Automated accessibility checks supplement, but do not replace, a full manual WCAG audit.

The normal local application currently cannot connect to MySQL at `127.0.0.1:3306`. No application data or database configuration was changed to work around that. The test database is isolated SQLite in memory.


Verified results: 81 Laravel tests (399 assertions), 20 desktop/mobile screen checks, and 10 automated accessibility scans across light and dark modes. Browser interactions include booking progression, summary drawer, theme persistence, and drag-and-drop rescheduling; no JavaScript or content-security-policy errors were reported.
