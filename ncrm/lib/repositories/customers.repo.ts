import type { Customer, CustomersPage } from "@/lib/domain";
import { createRepositoryClient } from "@/lib/supabase/client";
import type { Tables } from "@/lib/types/database";
import { repositoryError, toNumber } from "./_utils";

type CustomerSaleRow = Pick<
  Tables<"sales">,
  "id" | "customer_name" | "customer_phone" | "sold_at"
> & {
  order_statuses?: unknown;
  payment_statuses?: unknown;
  sale_items?: Array<Pick<Tables<"sale_items">, "id">> | null;
};
type FinancialRow = Tables<"v_sale_item_financials">;

type LookupCode = {
  code?: string | null;
};

type CustomerAggregate = Customer & {
  normalizedPhone: string;
};

function normalizePhone(phone: string | null) {
  return (phone ?? "").replace(/[^0-9+]/g, "");
}

function getLookupCode(raw: unknown) {
  const value = Array.isArray(raw) ? raw[0] : raw;
  return value && typeof value === "object"
    ? ((value as LookupCode).code ?? null)
    : null;
}

function isActualSaleRow(orderStatusCode: string | null, paymentStatusCode: string | null) {
  // Mirror public.fn_is_actual_sale from 0002_stage2_sales.sql.
  return (
    (paymentStatusCode === "paid" || ["shipped", "received"].includes(orderStatusCode ?? "")) &&
    !["cancelled", "refund"].includes(orderStatusCode ?? "") &&
    !["cancelled", "refund"].includes(paymentStatusCode ?? "")
  );
}

async function getFinancialRevenueByItemId(itemIds: string[]) {
  if (itemIds.length === 0) {
    return new Map<string, number>();
  }

  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from("v_sale_item_financials")
    .select("id, revenue")
    .in("id", itemIds);

  if (error) {
    throw repositoryError("getCustomers.financials", error.message);
  }

  return new Map(
    ((data ?? []) as FinancialRow[])
      .filter((row) => row.id)
      .map((row) => [row.id as string, toNumber(row.revenue)])
  );
}

export async function getCustomers(
  options: { limit?: number; offset?: number } = {}
): Promise<CustomersPage> {
  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from("sales")
    .select(
      "id, customer_name, customer_phone, sold_at, order_statuses(code), payment_statuses(code), sale_items(id)"
    )
    .order("sold_at", { ascending: false });

  if (error) {
    throw repositoryError("getCustomers.sales", error.message);
  }

  const sales = (data ?? []) as CustomerSaleRow[];
  const actualSales = sales.filter((sale) =>
    isActualSaleRow(
      getLookupCode(sale.order_statuses),
      getLookupCode(sale.payment_statuses)
    )
  );
  const revenueByItemId = await getFinancialRevenueByItemId(
    actualSales.flatMap((sale) => (sale.sale_items ?? []).map((item) => item.id))
  );
  const customers = new Map<string, CustomerAggregate>();

  for (const sale of actualSales) {
    const normalizedPhone = normalizePhone(sale.customer_phone);
    const revenue = (sale.sale_items ?? []).reduce(
      (total, item) => total + (revenueByItemId.get(item.id) ?? 0),
      0
    );
    const existing = customers.get(normalizedPhone);

    if (existing) {
      existing.orderCount += 1;
      existing.lifetimeRevenue += revenue;
      existing.firstOrderAt =
        sale.sold_at < existing.firstOrderAt ? sale.sold_at : existing.firstOrderAt;
      existing.lastOrderAt = sale.sold_at > existing.lastOrderAt ? sale.sold_at : existing.lastOrderAt;
      if (!existing.customerName && sale.customer_name) {
        existing.customerName = sale.customer_name;
      }
      continue;
    }

    customers.set(normalizedPhone, {
      normalizedPhone,
      customerPhone: normalizedPhone || null,
      customerName: sale.customer_name,
      orderCount: 1,
      firstOrderAt: sale.sold_at,
      lastOrderAt: sale.sold_at,
      lifetimeRevenue: revenue,
      isRepeat: false
    });
  }

  const rows = [...customers.values()]
    .map(({ normalizedPhone: _normalizedPhone, ...customer }) => ({
      ...customer,
      isRepeat: customer.orderCount > 1
    }))
    .sort(
      (left, right) =>
        right.lastOrderAt.localeCompare(left.lastOrderAt) ||
        right.lifetimeRevenue - left.lifetimeRevenue
    );
  const offset = options.offset ?? 0;
  const limit = options.limit ?? 100;

  return {
    rows: rows.slice(offset, offset + limit),
    total: rows.length
  };
}
