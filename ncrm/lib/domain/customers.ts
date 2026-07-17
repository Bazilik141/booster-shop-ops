export type Customer = {
  customerPhone: string | null;
  customerName: string | null;
  orderCount: number;
  firstOrderAt: string;
  lastOrderAt: string;
  lifetimeRevenue: number;
  isRepeat: boolean;
};

export type CustomersPage = {
  rows: Customer[];
  total: number;
};
