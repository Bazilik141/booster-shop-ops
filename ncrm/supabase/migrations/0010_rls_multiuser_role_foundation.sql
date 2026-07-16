-- NCRM-07b
-- Deny-by-default RLS foundation and future multi-user role schema.
-- No anon/authenticated policies are intentionally created in this migration.

alter table public.app_config enable row level security;
alter table public.auto_consumable_rules enable row level security;
alter table public.consumable_consumptions enable row level security;
alter table public.consumables enable row level security;
alter table public.currency_rates enable row level security;
alter table public.expenses enable row level security;
alter table public.games enable row level security;
alter table public.inventory_adjustment_items enable row level security;
alter table public.inventory_adjustments enable row level security;
alter table public.inventory_reservations enable row level security;
alter table public.mystery_box_types enable row level security;
alter table public.mystery_contents enable row level security;
alter table public.mystery_fulfillment_items enable row level security;
alter table public.mystery_fulfillments enable row level security;
alter table public.mystery_return_components enable row level security;
alter table public.order_statuses enable row level security;
alter table public.payment_statuses enable row level security;
alter table public.payment_types enable row level security;
alter table public.post_methods enable row level security;
alter table public.product_brands enable row level security;
alter table public.product_categories enable row level security;
alter table public.product_languages enable row level security;
alter table public.product_prices enable row level security;
alter table public.products enable row level security;
alter table public.purchase_lot_fee_allocations enable row level security;
alter table public.purchase_lot_statuses enable row level security;
alter table public.purchase_lots enable row level security;
alter table public.purchases enable row level security;
alter table public.refund_items enable row level security;
alter table public.refunds enable row level security;
alter table public.sale_channels enable row level security;
alter table public.sale_items enable row level security;
alter table public.sales enable row level security;
alter table public.supplier_regions enable row level security;
alter table public.writeoff_items enable row level security;
alter table public.writeoffs enable row level security;

create table public.staff (
  id uuid primary key references auth.users(id) on delete cascade,
  role text not null check (role in ('owner', 'admin', 'user_plus', 'user')),
  display_name text,
  created_at timestamptz not null default now()
);

create table public.staff_permission_overrides (
  id bigint generated always as identity primary key,
  staff_id uuid not null references public.staff(id) on delete cascade,
  permission_key text not null,
  granted boolean not null default true,
  created_at timestamptz not null default now(),
  unique (staff_id, permission_key)
);

alter table public.staff enable row level security;
alter table public.staff_permission_overrides enable row level security;

alter table public.sales
  add column created_by uuid references auth.users(id);

alter table public.purchases
  add column created_by uuid references auth.users(id);

alter table public.writeoffs
  add column created_by uuid references auth.users(id);

alter table public.mystery_fulfillments
  add column created_by uuid references auth.users(id);

alter view public.v_current_rrc set (security_invoker = true);
alter view public.v_pnl_monthly set (security_invoker = true);
alter view public.v_cost_quality_exposure set (security_invoker = true);
alter view public.v_unpriced_inventory set (security_invoker = true);
alter view public.v_forecast_margin set (security_invoker = true);
alter view public.v_inventory_dashboard_guardrails set (security_invoker = true);
alter view public.v_data_quality set (security_invoker = true);
