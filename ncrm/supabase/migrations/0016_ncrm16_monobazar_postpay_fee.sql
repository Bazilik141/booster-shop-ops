-- NCRM-16: Monobazar postpay fee type.
-- Local NCRM migration only. No cloud or production action is performed by this file.

insert into public.app_config (
  key, value_num, value_text, value_date, unit, description, effective_from, is_active
)
values (
  'postpay_monobazar_pct',
  0.029,
  null,
  null,
  'ratio',
  'Післяплата monobazar: комісія від вартості товару',
  date '2026-07-26',
  true
)
on conflict (key, effective_from) do update
set value_num = excluded.value_num,
    unit = excluded.unit,
    description = excluded.description,
    is_active = excluded.is_active;

insert into public.payment_types (
  code, name_uk, fee_pct_config_key, fee_fixed_config_key, fee_min_config_key, is_active
)
values (
  'postpay_monobazar',
  'Післяплата monobazar',
  'postpay_monobazar_pct',
  null,
  null,
  true
)
on conflict (code) do update
set name_uk = excluded.name_uk,
    fee_pct_config_key = excluded.fee_pct_config_key,
    fee_fixed_config_key = excluded.fee_fixed_config_key,
    fee_min_config_key = excluded.fee_min_config_key,
    is_active = excluded.is_active;
