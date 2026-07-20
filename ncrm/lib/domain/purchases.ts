export type PurchaseLot = {
  id: string;
  lotCode: string;
  purchaseId: string;
  productId: string;
  productSku?: string | null;
  productName?: string | null;
  qty: number;
  goodsCostUah: number;
  forwardingFeeUah: number;
  intlShippingUah: number;
  localDeliveryUah: number;
  manualUnitCost: number | null;
  deliveryDate: string | null;
  trackNumber: string | null;
  status: string;
  legacyStatus: string | null;
  note: string | null;
  createdAt: string;
  updatedAt: string;
};

export type Purchase = {
  id: string;
  regionId: string;
  supplierName: string | null;
  orderRef: string | null;
  orderUrl: string;
  orderedAt: string;
  goodsTotalAmount: number;
  goodsTotalCurrency: string;
  goodsTotalRate: number;
  goodsTotalUah: number;
  forwardingFeeAmount: number;
  forwardingFeeCurrency: string;
  forwardingFeeRate: number;
  forwardingFeeUah: number;
  intlShippingAmount: number;
  intlShippingCurrency: string;
  intlShippingRate: number;
  intlShippingUah: number;
  localDeliveryAmount: number;
  localDeliveryCurrency: string;
  localDeliveryRate: number;
  localDeliveryUah: number;
  note: string | null;
  createdAt: string;
  updatedAt: string;
  lots?: PurchaseLot[];
};

export type AddPurchaseLotPayload = {
  lotCode: string;
  productId: string;
  qty: number;
  goodsCostUah: number;
  forwardingFeeUah: number;
  intlShippingUah: number;
  localDeliveryUah: number;
  manualUnitCost?: number | null;
  deliveryDate?: string | null;
  trackNumber?: string | null;
  status: string;
  legacyStatus?: string | null;
  note?: string | null;
};

export type PurchaseSharedFeeType =
  | "forwarding_fee"
  | "intl_shipping"
  | "local_delivery";

export type PurchaseFeeAllocationMethod = "weight" | "value" | "manual";

export type PurchaseSharedFeeAllocation = {
  method: PurchaseFeeAllocationMethod;
  manualAllocations?: Partial<Record<PurchaseSharedFeeType, Record<string, number>>>;
};

export type AddPurchasePayload = {
  createdBy: string;
  regionId: string;
  supplierName?: string | null;
  orderRef?: string | null;
  orderUrl: string;
  orderedAt: string;
  goodsTotalAmount: number;
  goodsTotalCurrency: string;
  goodsTotalRate: number;
  goodsTotalUah: number;
  forwardingFeeAmount: number;
  forwardingFeeCurrency: string;
  forwardingFeeRate: number;
  forwardingFeeUah: number;
  intlShippingAmount: number;
  intlShippingCurrency: string;
  intlShippingRate: number;
  intlShippingUah: number;
  localDeliveryAmount: number;
  localDeliveryCurrency: string;
  localDeliveryRate: number;
  localDeliveryUah: number;
  note?: string | null;
  lots?: AddPurchaseLotPayload[];
  sharedFeeAllocation?: PurchaseSharedFeeAllocation;
};

export type BatchUpdateLotStatusPayload = {
  lotIds: string[];
  status: string;
  legacyStatus?: string | null;
  deliveryDate?: string | null;
  note?: string | null;
};
