# TaxVault locale URLs

The frontend uses a locale prefix in the URL:

- `/en` → English (`en-AU`)
- `/kr` → Korean (`ko-KR`)
- `/cn` → Simplified Chinese (`zh-CN`)
- `/es` → Spanish (`es-ES`)

`kr` and `cn` are intentionally kept as the public URL aliases requested by the
product. The standards-based locale IDs are used for browser and Angular date/
number formatting.

The locale prefix is handled before Angular bootstraps, so existing application
routes remain unchanged: `/en/dashboard`, `/kr/documents`, and so on. A URL
without a locale is redirected to `/en`.

Translation messages should be added under `src/assets/i18n/` and consumed by a
translation service or library in a later migration. Keep API/database enum
values locale-neutral and translate only their display labels in the frontend.
