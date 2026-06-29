export interface Transporter {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    website: string | null;
}

export type TransporterPaginator = {
    data: Transporter[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};
