export type Quantities = Record<string, string>;

export type PivotRow = {
  order_id: number;
  item_name: string;
  color_name: string;
  available: string[];
  quantities: Quantities;
  po_quantities: Record<string, number>;
  used_quantities: Record<string, number>;
};

export type Meta = { file_no: string; style: string; buyer: string | null };

export type TableOption = { id: number; name: string };

export type StatusOption = { value: string; label: string; locked: boolean };

export type FormShape = {
  skcl_no: string;
  table_no_id: string;
  status: string;
  fixed_qty: string;
  ratios: Record<string, string>;
  rows: PivotRow[];
};

// PivotForm থেকে পেজে পাঠানো পরিবর্তন
export type PivotChange = {
  rows: PivotRow[];
  fixedQty: string;
  ratios: Record<string, string>;
};

export type PlanInfo = {
  id: number;
  ref_no: string;
  skcl_no: string;
  file_no: string;
  buyer: string | null;
  style: string;
  item_name: string;
  color_name: string;
};

export type MarkerPlanListItem = {
  id: number;
  ref_no: string;
  skcl_no: string;
  buyer: string | null;
  style: string;
  item_name: string;
  color_name: string;
  table_name: string | null;
  fixed_qty: number | null;
  status: string;
  locked: boolean;
  total_qty: number;
  created_at: string;
};

export type Paginated<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
  prev_page_url: string | null;
  next_page_url: string | null;
};
