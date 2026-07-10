export type PnlMonth = {
  month: string | null;
  revenue: number;
  cogs: number;
  directSaleCosts: number;
  contributionMargin: number;
  operatingExpenses: number;
  refunds: number;
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
