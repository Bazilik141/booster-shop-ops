import type { Order, OrderLine, OrderRefund, OrdersPage } from "@/lib/domain";
import { createRepositoryClient } from "@/lib/supabase/client";
import type { Tables } from "@/lib/types/database";
import { mapLookup, repositoryError, toNumber } from "./_utils";

type SaleItemRow = Tables<"sale_items"> & { products?: unknown };
type FinancialRow = Tables<"v_sale_item_financials">;
type RefundItemRow = Tables<"refund_items">;
type RefundRow = Tables<"refunds"> & { refund_items?: RefundItemRow[] | null };
type OrderRow = Tables<"sales"> & {
  sale_channels?: unknown;
  payment_types?: unknown;
  payment_statuses?: unknown;
  order_statuses?: unknown;
  post_methods?: unknown;
  sale_items?: SaleItemRow[] | null;
  refunds?: RefundRow[] | null;
};

type ProductLookup = {
  sku?: string | null;
  name?: string | null;
  full_name?: string | null;
};

const ORDER_SELECT = `
  *,
  sale_channels(id, code, name_uk),
  payment_types(id, code, name_uk),
  payment_statuses(id, code, name_uk),
  order_statuses(id, code, name_uk),
  post_methods(id, code, name_uk),
  sale_items(*, products(sku, name, full_name))
`;

function mapProduct(raw: unknown): ProductLookup {
  const product = Array.isArray(raw) ? raw[0] : raw;
  return product && typeof product === "object" ? (product as ProductLookup) : {};
}

async function getFinancialsByItemId(itemIds: string[]) {
  if (itemIds.length === 0) {
    return new Map<string, FinancialRow>();
  }

  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from("v_sale_item_financials")
    .select("*")
    .in("id", itemIds);

  if (error) {
    throw repositoryError("getOrders.financials", error.message);
  }

  return new Map(
    ((data ?? []) as FinancialRow[])
      .filter((row) => row.id)
      .map((row) => [row.id as string, row])
  );
}

function mapLine(row: SaleItemRow, financial?: FinancialRow): OrderLine {
  const product = mapProduct(row.products);

  return {
    id: row.id,
    productId: row.product_id,
    sku: product.sku ?? null,
    name: product.full_name ?? product.name ?? null,
    qty: toNumber(row.qty),
    unitPrice: toNumber(row.unit_price),
    revenue: toNumber(financial?.revenue),
    managementCogs: toNumber(financial?.mgmt_cogs),
    contributionMargin: toNumber(financial?.net_profit),
    costState: row.cost_state
  };
}

function mapRefund(row: RefundRow): OrderRefund {
  return {
    id: row.id,
    refundType: row.refund_type,
    amount: toNumber(row.amount),
    refundedAt: row.refunded_at,
    restock: row.restock,
    reason: row.reason,
    items: (row.refund_items ?? []).map((item) => ({
      id: item.id,
      saleItemId: item.sale_item_id,
      qty: toNumber(item.qty),
      condition: item.condition,
      managementReversal: toNumber(item.mgmt_reversal_uah)
    }))
  };
}

function mapOrder(row: OrderRow, financials: Map<string, FinancialRow>): Order {
  const lines = (row.sale_items ?? []).map((item) => mapLine(item, financials.get(item.id)));

  return {
    id: row.id,
    orderNo: row.order_no,
    soldAt: row.sold_at,
    customerName: row.customer_name,
    customerPhone: row.customer_phone,
    channel: mapLookup(row.sale_channels),
    paymentType: mapLookup(row.payment_types),
    paymentStatus: mapLookup(row.payment_statuses),
    orderStatus: mapLookup(row.order_statuses),
    postMethod: mapLookup(row.post_methods),
    ttn: row.ttn,
    itemsCount: lines.length,
    units: lines.reduce((total, line) => total + line.qty, 0),
    revenue: lines.reduce((total, line) => total + line.revenue, 0),
    managementCogs: lines.reduce((total, line) => total + line.managementCogs, 0),
    contributionMargin: lines.reduce((total, line) => total + line.contributionMargin, 0),
    lines,
    refunds: (row.refunds ?? []).map(mapRefund)
  };
}

export async function getOrders(
  options: { status?: string; limit?: number; offset?: number } = {}
): Promise<OrdersPage> {
  const supabase = createRepositoryClient();
  const limit = options.limit ?? 100;
  const offset = options.offset ?? 0;
  let query = supabase
    .from("sales")
    .select(ORDER_SELECT, { count: "exact" })
    .order("sold_at", { ascending: false })
    .order("created_at", { ascending: false })
    .range(offset, offset + limit - 1);

  if (options.status) {
    query = query.eq("order_status_id", options.status);
  }

  const { data, error, count } = await query;
  if (error) {
    throw repositoryError("getOrders", error.message);
  }

  const rows = (data ?? []) as OrderRow[];
  const financials = await getFinancialsByItemId(
    rows.flatMap((row) => (row.sale_items ?? []).map((item) => item.id))
  );

  return {
    rows: rows.map((row) => mapOrder(row, financials)),
    total: count ?? 0
  };
}

export async function getOrderById(id: string): Promise<Order | null> {
  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from("sales")
    .select(`${ORDER_SELECT}, refunds(*, refund_items(*))`)
    .eq("id", id)
    .maybeSingle();

  if (error) {
    throw repositoryError("getOrderById", error.message);
  }

  if (!data) {
    return null;
  }

  const row = data as OrderRow;
  const financials = await getFinancialsByItemId((row.sale_items ?? []).map((item) => item.id));
  return mapOrder(row, financials);
}
