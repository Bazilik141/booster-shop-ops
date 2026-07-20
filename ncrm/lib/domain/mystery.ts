export type MysteryFulfillmentState =
  | "needs_assembly"
  | "reserved"
  | "committed"
  | "released"
  | "reversed";

export type MysteryQueueItem = {
  fulfillmentId: string;
  saleItemId: string;
  state: MysteryFulfillmentState;
  reservedAt: string | null;
  committedAt: string | null;
  releasedAt: string | null;
  orderNo: string;
  customerName: string | null;
  customerPhone: string | null;
  orderStatusCode: string | null;
  orderStatusName: string | null;
  mysteryProductId: string;
  mysterySku: string;
  mysteryName: string | null;
  saleQty: number;
};

export type MysteryEligibleComponent = {
  productId: string;
  sku: string;
  name: string | null;
  physicalQty: number;
  reservedQty: number;
  availableQty: number;
};

export type MysteryBoxType = {
  productId: string;
  expectedPackCount: number;
  hasHolo: boolean;
  holoCost: number;
  provisionalUnitCost: number;
};

export type MysteryComponentSelection = {
  productId: string;
  qty: number;
};
