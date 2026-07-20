-- PAY-001: monobank Покупка Частинами fee types.
-- Local NCRM migration only. No cloud/prod action is performed by this file.
-- Rates confirmed by the owner on 2026-07-19: 3=2.9%, 4=4.1%, 5=5.9%.

insert into public.app_config (
  key, value_num, value_text, value_date, unit, description, effective_from, is_active
)
values
  ('credit_mono_3_fee_pct', 0.029, null, null, 'ratio', 'monobank ПЧ: 3 платежі, комісія магазину', date '2026-07-19', true),
  ('credit_mono_4_fee_pct', 0.041, null, null, 'ratio', 'monobank ПЧ: 4 платежі, комісія магазину', date '2026-07-19', true),
  ('credit_mono_5_fee_pct', 0.059, null, null, 'ratio', 'monobank ПЧ: 5 платежів, комісія магазину', date '2026-07-19', true)
on conflict (key, effective_from) do update
set value_num = excluded.value_num,
    unit = excluded.unit,
    description = excluded.description,
    is_active = excluded.is_active;

insert into public.payment_types (
  code, name_uk, fee_pct_config_key, fee_fixed_config_key, fee_min_config_key, is_active
)
values
  ('credit_mono_3', 'Покупка Частинами monobank — 3 платежі', 'credit_mono_3_fee_pct', null, null, true),
  ('credit_mono_4', 'Покупка Частинами monobank — 4 платежі', 'credit_mono_4_fee_pct', null, null, true),
  ('credit_mono_5', 'Покупка Частинами monobank — 5 платежів', 'credit_mono_5_fee_pct', null, null, true)
on conflict (code) do update
set name_uk = excluded.name_uk,
    fee_pct_config_key = excluded.fee_pct_config_key,
    fee_fixed_config_key = excluded.fee_fixed_config_key,
    fee_min_config_key = excluded.fee_min_config_key,
    is_active = excluded.is_active;
