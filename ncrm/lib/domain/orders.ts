import type { LookupLabel } from "./sales";

export type OrderLine = {
  id: string;
  productId: string;
  sku: string | null;
  name: string | null;
  qty: number;
  unitPrice: number;
  revenue: number;
  managementCogs: number;
  contributionMargin: number;
  costState: string;
};

export type OrderRefundItem = {
  id: string;
  saleItemId: string;
  qty: number;
  condition: string;
  managementReversal: number;
};

export type OrderRefund = {
  id: string;
  refundType: string;
  amount: number;
  refundedAt: string;
  restock: boolean;
  reason: string | null;
  items: OrderRefundItem[];
};

export type Order = {
  id: string;
  orderNo: string;
  soldAt: string;
  customerName: string | null;
  customerPhone: string | null;
  channel: LookupLabel | null;
  paymentType: LookupLabel | null;
  paymentStatus: LookupLabel | null;
  orderStatus: LookupLabel | null;
  postMethod: LookupLabel | null;
  ttn: string | null;
  itemsCount: number;
  units: number;
  revenue: number;
  managementCogs: number;
  contributionMargin: number;
  lines: OrderLine[];
  refunds: OrderRefund[];
};

export type OrdersPage = {
  rows: Order[];
  total: number;
};
