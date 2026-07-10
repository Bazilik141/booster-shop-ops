export type Product = {
  id: string;
  sku: string;
  name: string | null;
  fullName: string | null;
  brandCode: string | null;
  categoryCode: string | null;
  gameCode: string | null;
  languageCode: string | null;
  gtin: string | null;
  legacySku: string | null;
  isActive: boolean;
  archivedAt: string | null;
  createdAt: string;
  updatedAt: string;
  currentRrc?: number | null;
};

export type SkuOption = {
  productId: string;
  sku: string;
  name: string | null;
  isActive: boolean;
  currentRrc?: number | null;
};

export type UpdateRrcPayload = {
  productId: string;
  rrc: number;
  source?: string;
  note?: string | null;
  effectiveFrom?: string;
};
