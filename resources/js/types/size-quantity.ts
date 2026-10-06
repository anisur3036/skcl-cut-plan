export type Quantities = Record<string, string>;

export type PivotRow = {
    country: string;
    item_name: string;
    color_name: string;
    available: string[];
    quantities: Quantities;
};

export type Meta = { file_no: string; order_no: string; style_no: string };

export type FormShape = { skcl_no: string; rows: PivotRow[] };

export type RefListItem = {
    ref_no: string;
    skcl_no: string;
    file_no: string;
    order_no: string;
    style_no: string;
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
