/**
 * Shared shape for simple contact "counterparty" master-data (Providers,
 * Transporters). Each module keeps its own entity-specific logic; this is only
 * the common contact record reused by the shared workspace components.
 */
export interface Counterparty {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    website: string | null;
}

export type CounterpartyPaginator = {
    data: Counterparty[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};
