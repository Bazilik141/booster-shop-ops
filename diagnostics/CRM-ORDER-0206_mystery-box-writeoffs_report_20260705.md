# CRM-ORDER-0206 — Mystery Box writeoffs

Date: 2026-07-05

## Scope

Added the requested Mystery Box component writeoffs and small packaging for
`OC-FOP-0206`. No payment status, order status, customer data, or purchase rows
were changed.

## Live changes

`Списання!A119:L122`:

- `WRT-0117` — 1× `PKM-JP-INFX-BST`;
- `WRT-0118` — 2× `PKM-JP-MZERO-BST`;
- `WRT-0119` — 1× `PKM-JP-MSYM-BST`;
- `WRT-0120` — 1× `PKM-JP-SPIN-BST`.

All rows use reason `для формування містері бокса` and note
`Продаж OC-FOP-0206; Pokémon Mystery Box`.

Packaging:

- `Продажі!P192 = 2.16`;
- `Продажі!P193 = 1.44`;
- `Продажі!AC192:AC193 = Мала м'яка 14х12 см`.

Total packaging cost: 3.60 грн.

## Cost result

Mystery Box component cost:

- PRRO: 339.40 грн;
- management components: 359.75 грн;
- sticker + blind bag + Mystery Box label: 3.26 грн;
- final management cost: 363.01 грн.

Final order result:

- amount: 1062.50 грн;
- net profit: 311.52 грн;
- status remains `Не оплачено / Нове`.

## Stock result

- Inferno X: 30 → 29;
- Munics Zero: 42 → 40;
- Mega Symphonia: 20 → 19;
- Ninja Spinner: 4 → 3;
- small soft package remaining: 80.

## Verification

Live readback:

- all four writeoff rows populated with calculated PRRO/management totals;
- Mystery Box method is `MBX фактична комплектація`;
- order API reports `profit=311.52`;
- summary API reports:
  - `source_ok=true`;
  - `mystery_boxes_without_writeoffs=0`;
  - `negative_stock=0`.

## Rollback

Clear user-entered values in `Списання!A119:D122`, `F119:F122`,
`K119:L122`; restore `Продажі!P192:P193` and `AC192:AC193` to blank; restore
the prior Mystery Box cost/audit values in `Продажі!L192:M192`,
`AD192:AF192`.
