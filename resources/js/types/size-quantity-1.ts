export type StatusOption = { value: string; label: string; locked: boolean };
export type Quantities = Record<string, string>;

export type MarkerPlanListItem = {
  // ... আগের ফিল্ডগুলো
  status: string;
  locked: boolean;
  total_qty: number;
  created_at: string;
};

export type PivotRow = {
  country: string;
  item_name: string;
  color_name: string;
  available: string[];
  quantities: Quantities;
  po_quantities: Record<string, number>;
  used_quantities: Record<string, number>;
};

export type Meta = { file_no: string; order_no: string; style_no: string };

export type TableOption = { id: number; name: string };

export type FormShape = {
  skcl_no: string;
  table_no_id: string;
  fixed_qty: string;
  ratios: Record<string, string>;
  rows: PivotRow[];
};

// PivotForm থেকে পেজে পাঠানো পরিবর্তন
export type PivotChange = {
  rows: PivotRow[];
  fixedQty: string
  ratios: Record<string, string>;
};

export type RefListItem = {
  ref_no: string;
  skcl_no: string;
  file_no: string;
  order_no: string;
  style_no: string;
  table_name: string | null;
  fixed_qty: number | null;
  colors: string;
  total_qty: number;
  created_at: string;
  updated_at: string;
};

export type Paginated<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
  prev_page_url: string | null;
  next_page_url: string | null;
};
