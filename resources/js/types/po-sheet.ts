export type PoSheet = {
    id: number;
    file_no: string;
    skcl_no: string;
    order_no: string;
    style_no: string;
    country: string;
    item_name: string;
    color_name: string;
    size: string;
    quantity: string;
    created_at: string;
    updated_at: string;
};

export type PoSheetFormData = Omit<PoSheet, 'id' | 'created_at' | 'updated_at'>;

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
