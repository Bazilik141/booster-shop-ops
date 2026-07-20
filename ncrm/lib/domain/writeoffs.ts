export type WriteoffItem = {
  id: string;
  writeoffId: string;
  productId: string;
  productSku?: string | null;
  productName?: string | null;
  qty: number;
  note: string | null;
  createdAt: string;
  updatedAt: string;
};

export type Writeoff = {
  id: string;
  writeoffNo: string;
  type: string;
  reason: string | null;
  expectedQty: number | null;
  writtenOffAt: string;
  mysterySaleId: string | null;
  note: string | null;
  createdAt: string;
  updatedAt: string;
  items?: WriteoffItem[];
};

export type AddWriteoffItemPayload = {
  productId: string;
  qty: number;
  note?: string | null;
};

export type AddWriteoffPayload = {
  createdBy: string;
  writeoffNo: string;
  type: string;
  reason?: string | null;
  expectedQty?: number | null;
  writtenOffAt: string;
  mysterySaleId?: string | null;
  note?: string | null;
  items?: AddWriteoffItemPayload[];
};
