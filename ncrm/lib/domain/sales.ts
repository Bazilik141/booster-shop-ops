export type LookupLabel = {
  id?: string | null;
  code?: string | null;
  name?: string | null;
};

export type SaleItem = {
  id: string;
  saleId: string;
  productId: string;
  qty: number;
  unitPrice: number;
  discountAlloc: number;
  packagingAlloc: number;
  shopDeliveryAlloc: number;
  paymentFee: number;
  prroUnit: number | null;
  mgmtUnit: number | null;
  costMethod: string;
  costState: string;
  note: string | null;
};

export type Sale = {
  id: string;
  orderNo: string;
  openCartOrderId: string | null;
  soldAt: string;
  customerName: string | null;
  customerPhone: string | null;
  channelId: string;
  channel?: LookupLabel | null;
  paymentTypeId: string;
  paymentType?: LookupLabel | null;
  paymentStatusId: string;
  paymentStatus?: LookupLabel | null;
  orderStatusId: string;
  orderStatus?: LookupLabel | null;
  postMethodId: string | null;
  postMethod?: LookupLabel | null;
  ttn: string | null;
  discountTotal: number;
  packagingCost: number;
  shopDelivery: number;
  note: string | null;
  createdAt: string;
  updatedAt: string;
  items?: SaleItem[];
};

export type AddSaleItemPayload = {
  productId: string;
  qty: number;
  unitPrice: number;
  discountAlloc?: number;
  packagingAlloc?: number;
  shopDeliveryAlloc?: number;
  paymentFee?: number;
  prroUnit?: number | null;
  mgmtUnit?: number | null;
  note?: string | null;
};

export type AddSalePayload = {
  createdBy: string;
  orderNo: string;
  openCartOrderId?: string | null;
  channelId: string;
  soldAt: string;
  customerName?: string | null;
  customerPhone?: string | null;
  paymentTypeId: string;
  paymentStatusId: string;
  orderStatusId: string;
  postMethodId?: string | null;
  ttn?: string | null;
  discountTotal?: number;
  packagingTypeId?: string | null;
  packagingCost?: number;
  shopDelivery?: number;
  note?: string | null;
  items?: AddSaleItemPayload[];
};

export type UpdateSaleStatusPayload = {
  orderStatusId?: string;
  paymentStatusId?: string;
  ttn?: string | null;
  note?: string | null;
};
