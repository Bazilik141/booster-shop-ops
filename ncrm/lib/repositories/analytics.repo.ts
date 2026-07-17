import type {
  CostQualityExposure,
  CrmSummary,
  DashboardGuardrails,
  ForecastMarginRow,
  PnlMonth,
  SalesPeriodSummary,
  SkuCatalogPage,
  SkuCatalogRow,
  SkuMetrics,
  StockRow,
  UnpricedInventoryRow
} from "@/lib/domain";
import { createRepositoryClient } from "@/lib/supabase/client";
import type { Tables } from "@/lib/types/database";
import { repositoryError, toNullableNumber, toNumber } from "./_utils";

type SalesReportRow = Tables<"v_sales_report">;
type PnlMonthRow = Tables<"v_pnl_monthly">;
type StockAlertRow = Tables<"v_stock_alerts">;
type TopSkuRow = Tables<"v_top_skus">;
type ProductRow = Tables<"products">;
type CurrentRrcRow = Tables<"v_current_rrc">;
type InventoryFifoRow = Tables<"v_inventory_fifo_valuation">;
type CostQualityExposureRow = Tables<"v_cost_quality_exposure">;
type UnpricedInventoryRecord = Tables<"v_unpriced_inventory">;
type ForecastMarginRecord = Tables<"v_forecast_margin">;
type DashboardGuardrailsRow = Tables<"v_inventory_dashboard_guardrails">;

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
    netRevenue: toNumber(row.net_revenue),
    prroGrossProfit: toNumber(row.prro_gross_profit),
    cogs: toNumber(row.cogs),
    cogsReversals: toNumber(row.cogs_reversals),
    directSaleCosts: toNumber(row.direct_sale_costs),
    contributionMargin: toNumber(row.contribution_margin),
    operatingExpenses: toNumber(row.operating_expenses),
    refunds: toNumber(row.refunds),
    inventoryAdjustmentImpact: toNumber(row.inventory_adjustment_impact),
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

function mapCostQualityExposure(row: CostQualityExposureRow): CostQualityExposure {
  return {
    month: row.month,
    costState: row.cost_state,
    saleItemCount: toNumber(row.sale_item_count),
    units: toNumber(row.units),
    revenue: toNumber(row.revenue),
    managementCogs: toNumber(row.management_cogs)
  };
}

function mapUnpricedInventory(row: UnpricedInventoryRecord): UnpricedInventoryRow {
  return {
    productId: row.product_id,
    sku: row.sku,
    name: row.name,
    physicalQty: toNumber(row.physical_qty),
    reservedQty: toNumber(row.reserved_qty),
    availableQty: toNumber(row.available_qty),
    warehouseMgmtCost: toNumber(row.warehouse_mgmt_cost),
    assetMgmtCost: toNumber(row.asset_mgmt_cost)
  };
}

function mapForecastMargin(row: ForecastMarginRecord): ForecastMarginRow {
  return {
    productId: row.product_id,
    sku: row.sku,
    name: row.name,
    manualRrc: toNullableNumber(row.manual_rrc),
    physicalQty: toNumber(row.physical_qty),
    reservedQty: toNumber(row.reserved_qty),
    availableQty: toNumber(row.available_qty),
    expectedDiscountPct: toNullableNumber(row.expected_discount_pct),
    forecastRevenueBeforeReserve: toNumber(row.forecast_revenue_before_reserve),
    expectedDiscountAmount: toNumber(row.expected_discount_amount),
    forecastNetRevenue: toNumber(row.forecast_net_revenue),
    managementInventoryCost: toNumber(row.management_inventory_cost),
    forecastMargin: toNumber(row.forecast_margin)
  };
}

function mapDashboardGuardrails(row: DashboardGuardrailsRow): DashboardGuardrails {
  return {
    physicalQty: toNumber(row.physical_qty),
    reservedQty: toNumber(row.reserved_qty),
    availableQty: toNumber(row.available_qty),
    warehousePrroCost: toNumber(row.warehouse_prro_cost),
    warehouseMgmtCost: toNumber(row.warehouse_mgmt_cost),
    assetPrroCost: toNumber(row.asset_prro_cost),
    assetMgmtCost: toNumber(row.asset_mgmt_cost)
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

export async function getStock(
  options: { limit?: number; offset?: number } = {}
): Promise<StockRow[]> {
  const supabase = createRepositoryClient();
  const limit = options.limit ?? 1000;
  const offset = options.offset ?? 0;
  const { data, error } = await supabase
    .from("v_stock_alerts")
    .select("*")
    .order("stock_qty", { ascending: true })
    .range(offset, offset + limit - 1);

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
    getStock({ limit: 1000 })
  ]);

  if (topSkus.error) {
    throw repositoryError("getSkuMetrics", topSkus.error.message);
  }

  const stockByProduct = new Map(stock.map((row) => [row.productId, row]));

  return ((topSkus.data ?? []) as TopSkuRow[]).map((row) =>
    mapSkuMetric(row, stockByProduct.get(row.product_id))
  );
}

export async function getCostQualityExposure(): Promise<CostQualityExposure[]> {
  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from("v_cost_quality_exposure")
    .select("*")
    .order("month", { ascending: false });

  if (error) {
    throw repositoryError("getCostQualityExposure", error.message);
  }

  return ((data ?? []) as CostQualityExposureRow[]).map(mapCostQualityExposure);
}

export async function getUnpricedInventory(): Promise<UnpricedInventoryRow[]> {
  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from("v_unpriced_inventory")
    .select("*")
    .order("physical_qty", { ascending: false });

  if (error) {
    throw repositoryError("getUnpricedInventory", error.message);
  }

  return ((data ?? []) as UnpricedInventoryRecord[]).map(mapUnpricedInventory);
}

export async function getForecastMargin(): Promise<ForecastMarginRow[]> {
  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from("v_forecast_margin")
    .select("*")
    .order("forecast_margin", { ascending: false });

  if (error) {
    throw repositoryError("getForecastMargin", error.message);
  }

  return ((data ?? []) as ForecastMarginRecord[]).map(mapForecastMargin);
}

export async function getDashboardGuardrails(): Promise<DashboardGuardrails | null> {
  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from("v_inventory_dashboard_guardrails")
    .select("*")
    .maybeSingle();

  if (error) {
    throw repositoryError("getDashboardGuardrails", error.message);
  }

  return data ? mapDashboardGuardrails(data as DashboardGuardrailsRow) : null;
}

function normalizeSearch(value: string) {
  return value.replace(/[^0-9A-Za-zА-Яа-яІіЇїЄєҐґ -]/g, "").trim();
}

export async function getSkuCatalog(
  options: { limit?: number; offset?: number; search?: string } = {}
): Promise<SkuCatalogPage> {
  const supabase = createRepositoryClient();
  const limit = options.limit ?? 100;
  const offset = options.offset ?? 0;
  const search = options.search ? normalizeSearch(options.search) : "";
  let productQuery = supabase
    .from("products")
    .select("*", { count: "exact" })
    .eq("is_active", true)
    .is("archived_at", null)
    .order("sku", { ascending: true })
    .range(offset, offset + limit - 1);

  if (search) {
    productQuery = productQuery.or(
      `sku.ilike.%${search}%,name.ilike.%${search}%,full_name.ilike.%${search}%`
    );
  }

  const { data: productData, error: productsError, count } = await productQuery;
  if (productsError) {
    throw repositoryError("getSkuCatalog.products", productsError.message);
  }

  const products = (productData ?? []) as ProductRow[];
  const productIds = products.map((product) => product.id);
  if (productIds.length === 0) {
    return { rows: [], total: count ?? 0 };
  }

  const [rrc, valuation, stock, topSkus] = await Promise.all([
    supabase.from("v_current_rrc").select("*").in("product_id", productIds),
    supabase
      .from("v_inventory_fifo_valuation")
      .select("*")
      .in("product_id", productIds),
    supabase.from("v_stock_alerts").select("*").in("product_id", productIds),
    supabase.from("v_top_skus").select("*").in("product_id", productIds)
  ]);

  for (const [action, result] of [
    ["getSkuCatalog.rrc", rrc],
    ["getSkuCatalog.valuation", valuation],
    ["getSkuCatalog.stock", stock],
    ["getSkuCatalog.topSkus", topSkus]
  ] as const) {
    if (result.error) {
      throw repositoryError(action, result.error.message);
    }
  }

  const rrcByProduct = new Map(
    ((rrc.data ?? []) as CurrentRrcRow[])
      .filter((row) => row.product_id)
      .map((row) => [row.product_id as string, toNullableNumber(row.rrc)])
  );
  const valuationByProduct = new Map(
    ((valuation.data ?? []) as InventoryFifoRow[])
      .filter((row) => row.product_id)
      .map((row) => [row.product_id as string, row])
  );
  const stockByProduct = new Map(
    ((stock.data ?? []) as StockAlertRow[])
      .filter((row) => row.product_id)
      .map((row) => [row.product_id as string, row])
  );
  const topByProduct = new Map(
    ((topSkus.data ?? []) as TopSkuRow[])
      .filter((row) => row.product_id)
      .map((row) => [row.product_id as string, row])
  );

  return {
    total: count ?? 0,
    rows: products.map((product): SkuCatalogRow => {
      const stockRow = stockByProduct.get(product.id);
      const valuationRow = valuationByProduct.get(product.id);
      const topSku = topByProduct.get(product.id);

      return {
        productId: product.id,
        sku: product.sku,
        name: product.name,
        fullName: product.full_name,
        currentRrc: rrcByProduct.get(product.id) ?? null,
        stockQty: stockRow ? toNumber(stockRow.stock_qty) : null,
        soldQty30d: stockRow ? toNumber(stockRow.sold_qty_30d) : 0,
        coverageDays: stockRow ? toNullableNumber(stockRow.coverage_days) : null,
        alert: stockRow?.alert ?? null,
        warehouseQty: valuationRow ? toNumber(valuationRow.warehouse_qty) : 0,
        warehouseMgmtCost: valuationRow
          ? toNumber(valuationRow.warehouse_mgmt_cost)
          : 0,
        revenue30d: topSku ? toNumber(topSku.revenue) : 0,
        units30d: topSku ? toNumber(topSku.units) : 0,
        contributionMargin30d: topSku ? toNumber(topSku.contribution_margin) : 0
      };
    })
  };
}
