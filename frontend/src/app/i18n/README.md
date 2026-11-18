# TaxVault locale URLs

The frontend keeps the selected locale out of the URL:

- English → `en-AU`
- Korean → `ko-KR`
- Simplified Chinese → `zh-CN`
- Spanish → `es-ES`

`kr` and `cn` are intentionally kept as the public URL aliases requested by the
product. The standards-based locale IDs are used for browser and Angular date/
number formatting.

Legacy locale-prefixed URLs such as `/en/dashboard` and `/kr/documents` are
rewritten to `/dashboard` and `/documents` when the app starts. New URLs use
the root domain directly.

Translation messages should be added under `src/assets/i18n/` and consumed by a
translation service or library in a later migration. Keep API/database enum
values locale-neutral and translate only their display labels in the frontend.
