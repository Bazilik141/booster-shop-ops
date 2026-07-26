-- NCRM-11: fallback buffer for official NBU exchange rates.
-- Local NCRM migration only. No cloud/prod action is performed by this file.

insert into public.app_config (
  key, value_num, value_text, value_date, unit, description, effective_from, is_active
)
values (
  'nbu_rate_buffer_pct',
  0.01,
  null,
  null,
  'ratio',
  'Буфер +1% до курсу НБУ, коли банківське API недоступне',
  date '2026-07-26',
  true
)
on conflict (key, effective_from) do update
set value_num = excluded.value_num,
    unit = excluded.unit,
    description = excluded.description,
    is_active = excluded.is_active;
