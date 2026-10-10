# Glowbook UI/UX

Updated 2026-10-10. Laravel Blade, Bootstrap layout utilities, local CSS and CSP-compatible Alpine.

## Direction

An elegant salon booking experience built around a calm forest-green identity, warm neutral surfaces, generous spacing and legible Vietnamese text. Preserve the established brand and light/dark themes. UI UX Pro Max's `beauty salon booking elegant` search recommends soft surfaces, subtle depth and accessible interaction; adapt that guidance to the existing identity. Do not add invented testimonials or switch to the tool's generic pink/purple palette.

## Tokens

| Role | Light | Dark |
| --- | --- | --- |
| Text | #252b27 | #efeee5 |
| Secondary text | #626960 | #b8bdb4 |
| Primary | #365744 | #b0c9af |
| Page | #f7f5f0 | #171d19 |
| Surface | #fffefa | #222a24 |
| Secondary surface | #eceee6 | #2d392e |
| Border | #dedfd6 | #455046 |
| Focus / accent | #91563f | #e5b29b |

Body: Be Vietnam Pro, 16px, 1.7 line height. Headings: Noto Serif Variable, moderate tracking and at least 1.3 line height to keep Vietnamese accents clear. Both fonts are self-hosted with Vietnamese subsets and `font-display: swap`. Supporting labels: 12–14px. Inputs: 16px minimum. Actions: 48px minimum height. Radius: 16–24px for surfaces, pill for actions. Spacing: 8/12/16/24/32/48px.

## Discovery

Search with a visible label is in the first viewport. Three numbered steps describe the actual booking flow. Salon cards use real business/branch names and addresses. Decorations are illustrations, not purported photos. On screens up to 800px omit the large hero illustration; at 560px stack the search input and action, shorten salon illustrations and keep essential text intact. Show result count and an operable clear-search action.

## Booking and account

Use Vietnamese labels, weekday names and dd/mm/yyyy in the review. Keep native date/time inputs. Maintain the four-step booking flow and its progressive no-JavaScript fallback. Current and completed steps are distinct, with a status announcement when the step changes. Server errors retain input and reopen the relevant step. The mobile summary opens a native dialog with an explicit close button. Password visibility is an optional native button, with its state and target exposed to assistive technology.

## Quality floor

Use the shared inline SVG icon component for controls. Preserve keyboard focus, skip link, native forms, CSRF, backend authorization and server-authoritative prices/availability. Keep categories and dates operable without horizontal swipe. Respect reduced motion and safe areas. Validate light/dark themes at 320, 375, 390, 768, 1024 and 1440px, and at enlarged text/zoom. Test actual interactions alongside page rendering; mocked browser slot responses only exercise presentation, while Laravel tests cover the APIs.
