import type {
  CrmSummary,
  PnlMonth,
  SalesPeriodSummary,
  SkuMetrics,
  StockRow
} from "@/lib/domain";
import { createRepositoryClient } from "@/lib/supabase/client";
import type { Tables } from "@/lib/types/database";
import { repositoryError, toNullableNumber, toNumber } from "./_utils";

type SalesReportRow = Tables<"v_sales_report">;
type PnlMonthRow = Tables<"v_pnl_monthly">;
type StockAlertRow = Tables<"v_stock_alerts">;
type TopSkuRow = Tables<"v_top_skus">;

function mapSalesPeriod(row: SalesReportRow): SalesPeriodSummary {
  return {
    periodCode: row.period_code,
    periodName: row.period_name,
    dateFrom: row.date_from,
    dateTo: row.date_to,
    orders: toNumber(row.orders),
    units: toNumber(row.units),
    revenue: toNumber(row.revenue),
    trueNetProfit: toNumber(row.true_net_profit),
    marginPct: toNullableNumber(row.margin_pct),
    averageOrderValue: toNullableNumber(row.average_order_value)
  };
}

function mapPnlMonth(row: PnlMonthRow): PnlMonth {
  return {
    month: row.month,
    revenue: toNumber(row.revenue),
    cogs: toNumber(row.cogs),
    directSaleCosts: toNumber(row.direct_sale_costs),
    contributionMargin: toNumber(row.contribution_margin),
    operatingExpenses: toNumber(row.operating_expenses),
    refunds: toNumber(row.refunds),
    trueNetProfit: toNumber(row.true_net_profit),
    marginPct: toNullableNumber(row.margin_pct)
  };
}

function mapStock(row: StockAlertRow): StockRow {
  return {
    productId: row.product_id,
    sku: row.sku,
    name: row.name,
    stockQty: toNumber(row.stock_qty),
    soldQty30d: toNumber(row.sold_qty_30d),
    coverageDays: toNullableNumber(row.coverage_days),
    alert: row.alert
  };
}

function mapSkuMetric(row: TopSkuRow, stock?: StockRow): SkuMetrics {
  return {
    productId: row.product_id,
    sku: row.sku,
    name: row.name,
    units: toNumber(row.units),
    revenue: toNumber(row.revenue),
    contributionMargin: toNumber(row.contribution_margin),
    stockQty: stock?.stockQty ?? null,
    coverageDays: stock?.coverageDays ?? null,
    alert: stock?.alert ?? null
  };
}

export async function getSummary(): Promise<CrmSummary> {
  const supabase = createRepositoryClient();
  const [salesPeriods, latestPnl, products] = await Promise.all([
    supabase
      .from("v_sales_report")
      .select("*")
      .order("date_from", { ascending: true }),
    supabase
      .from("v_pnl_monthly")
      .select("*")
      .order("month", { ascending: false })
      .limit(1)
      .maybeSingle(),
    supabase.from("products").select("id", { count: "exact", head: true })
  ]);

  if (salesPeriods.error) {
    throw repositoryError("getSummary.salesPeriods", salesPeriods.error.message);
  }

  if (latestPnl.error) {
    throw repositoryError("getSummary.latestPnl", latestPnl.error.message);
  }

  if (products.error) {
    throw repositoryError("getSummary.productCount", products.error.message);
  }

  return {
    generatedAt: new Date().toISOString(),
    productCount: products.count ?? 0,
    salesPeriods: ((salesPeriods.data ?? []) as SalesReportRow[]).map(mapSalesPeriod),
    latestPnlMonth: latestPnl.data ? mapPnlMonth(latestPnl.data as PnlMonthRow) : null
  };
}

export async function getStock(options: { limit?: number } = {}): Promise<StockRow[]> {
  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from("v_stock_alerts")
    .select("*")
    .order("stock_qty", { ascending: true })
    .limit(options.limit ?? 100);

  if (error) {
    throw repositoryError("getStock", error.message);
  }

  return ((data ?? []) as StockAlertRow[]).map(mapStock);
}

export async function getSkuMetrics(
  options: { limit?: number } = {}
): Promise<SkuMetrics[]> {
  const supabase = createRepositoryClient();
  const limit = options.limit ?? 100;
  const [topSkus, stock] = await Promise.all([
    supabase
      .from("v_top_skus")
      .select("*")
      .order("revenue", { ascending: false })
      .limit(limit),
    getStock({ limit: 500 })
  ]);

  if (topSkus.error) {
    throw repositoryError("getSkuMetrics", topSkus.error.message);
  }

  const stockByProduct = new Map(stock.map((row) => [row.productId, row]));

  return ((topSkus.data ?? []) as TopSkuRow[]).map((row) =>
    mapSkuMetric(row, stockByProduct.get(row.product_id))
  );
}
