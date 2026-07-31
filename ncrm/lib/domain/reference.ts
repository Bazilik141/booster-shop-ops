export type ReferenceOption = {
  id: string;
  code: string;
  name: string;
};

export type PaymentTypeOption = ReferenceOption & {
  feePct: number | null;
};

export type SupplierRegionOption = ReferenceOption & {
  defaultGoodsCurrency: string;
  defaultForwardingCurrency: string;
  defaultIntlShippingCurrency: string;
  defaultLocalCurrency: string;
};

export type SaleFormReferences = {
  channels: ReferenceOption[];
  paymentTypes: PaymentTypeOption[];
  paymentStatuses: ReferenceOption[];
  orderStatuses: ReferenceOption[];
  postMethods: ReferenceOption[];
};

export type PurchaseFormReferences = {
  regions: SupplierRegionOption[];
  lotStatuses: ReferenceOption[];
};
