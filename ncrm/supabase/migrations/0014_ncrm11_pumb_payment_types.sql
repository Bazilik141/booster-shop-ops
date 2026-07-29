-- NCRM-14: PUMB Сплата частинами fee types.
-- Local NCRM migration only. No cloud/prod action is performed by this file.
-- Rates confirmed from PUMB contract Annex 2 on 2026-07-26: 3=3.0%, 4=4.5%, 5=5.8%.

insert into public.app_config (
  key, value_num, value_text, value_date, unit, description, effective_from, is_active
)
values
  ('credit_pumb_3_fee_pct', 0.030, null, null, 'ratio', 'ПУМБ Сплата частинами: 3 платежі, комісія магазину', date '2026-07-26', true),
  ('credit_pumb_4_fee_pct', 0.045, null, null, 'ratio', 'ПУМБ Сплата частинами: 4 платежі, комісія магазину', date '2026-07-26', true),
  ('credit_pumb_5_fee_pct', 0.058, null, null, 'ratio', 'ПУМБ Сплата частинами: 5 платежів, комісія магазину', date '2026-07-26', true)
on conflict (key, effective_from) do update
set value_num = excluded.value_num,
    unit = excluded.unit,
    description = excluded.description,
    is_active = excluded.is_active;

insert into public.payment_types (
  code, name_uk, fee_pct_config_key, fee_fixed_config_key, fee_min_config_key, is_active
)
values
  ('credit_pumb_3', 'Сплата частинами ПУМБ — 3 платежі', 'credit_pumb_3_fee_pct', null, null, true),
  ('credit_pumb_4', 'Сплата частинами ПУМБ — 4 платежі', 'credit_pumb_4_fee_pct', null, null, true),
  ('credit_pumb_5', 'Сплата частинами ПУМБ — 5 платежів', 'credit_pumb_5_fee_pct', null, null, true)
on conflict (code) do update
set name_uk = excluded.name_uk,
    fee_pct_config_key = excluded.fee_pct_config_key,
    fee_fixed_config_key = excluded.fee_fixed_config_key,
    fee_min_config_key = excluded.fee_min_config_key,
    is_active = excluded.is_active;