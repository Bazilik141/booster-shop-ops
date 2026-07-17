export type PnlMonth = {
  month: string | null;
  revenue: number;
  netRevenue: number;
  prroGrossProfit: number;
  cogs: number;
  cogsReversals: number;
  directSaleCosts: number;
  contributionMargin: number;
  operatingExpenses: number;
  refunds: number;
  inventoryAdjustmentImpact: number;
  trueNetProfit: number;
  marginPct: number | null;
};

export type SalesPeriodSummary = {
  periodCode: string | null;
  periodName: string | null;
  dateFrom: string | null;
  dateTo: string | null;
  orders: number;
  units: number;
  revenue: number;
  trueNetProfit: number;
  marginPct: number | null;
  averageOrderValue: number | null;
};

export type StockRow = {
  productId: string | null;
  sku: string | null;
  name: string | null;
  stockQty: number;
  soldQty30d: number;
  coverageDays: number | null;
  alert: string | null;
};

export type SkuMetrics = {
  productId: string | null;
  sku: string | null;
  name: string | null;
  units: number;
  revenue: number;
  contributionMargin: number;
  stockQty?: number | null;
  coverageDays?: number | null;
  alert?: string | null;
};

export type CrmSummary = {
  generatedAt: string;
  productCount: number;
  salesPeriods: SalesPeriodSummary[];
  latestPnlMonth: PnlMonth | null;
};

export type CostQualityExposure = {
  month: string | null;
  costState: string | null;
  saleItemCount: number;
  units: number;
  revenue: number;
  managementCogs: number;
};

export type UnpricedInventoryRow = {
  productId: string | null;
  sku: string | null;
  name: string | null;
  physicalQty: number;
  reservedQty: number;
  availableQty: number;
  warehouseMgmtCost: number;
  assetMgmtCost: number;
};

export type ForecastMarginRow = {
  productId: string | null;
  sku: string | null;
  name: string | null;
  manualRrc: number | null;
  physicalQty: number;
  reservedQty: number;
  availableQty: number;
  expectedDiscountPct: number | null;
  forecastRevenueBeforeReserve: number;
  expectedDiscountAmount: number;
  forecastNetRevenue: number;
  managementInventoryCost: number;
  forecastMargin: number;
};

export type DashboardGuardrails = {
  physicalQty: number;
  reservedQty: number;
  availableQty: number;
  warehousePrroCost: number;
  warehouseMgmtCost: number;
  assetPrroCost: number;
  assetMgmtCost: number;
};

export type SkuCatalogRow = {
  productId: string;
  sku: string;
  name: string | null;
  fullName: string | null;
  currentRrc: number | null;
  stockQty: number | null;
  soldQty30d: number;
  coverageDays: number | null;
  alert: string | null;
  warehouseQty: number;
  warehouseMgmtCost: number;
  revenue30d: number;
  units30d: number;
  contributionMargin30d: number;
};

export type SkuCatalogPage = {
  rows: SkuCatalogRow[];
  total: number;
};
