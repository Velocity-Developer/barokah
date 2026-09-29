export type PayoutStatus = 'pending' | 'approved' | 'paid' | 'rejected';

export type PayoutRow = {
    id: number;
    reference: string;
    status: PayoutStatus;
    store_name: string | null;
    seller_id: number;
    orders_count: number;
    product_amount_formatted: string;
    shipping_amount_formatted: string;
    commission_rate: number;
    commission_amount_formatted: string;
    net_amount_formatted: string;
    bank_account: string;
    seller_note: string | null;
    admin_note: string | null;
    transfer_reference: string | null;
    proof_url: string | null;
    requested_at: string | null;
    approved_at: string | null;
    paid_at: string | null;
    rejected_at: string | null;
};

export type PayoutShipment = {
    id: number;
    order_number: string;
    delivered_at: string | null;
    product_amount_formatted: string;
    shipping_amount_formatted: string;
    total_formatted: string;
};

export const payoutStatusLabels: Record<PayoutStatus, string> = {
    pending: 'Waiting for approval',
    approved: 'Approved, transfer pending',
    paid: 'Sent',
    rejected: 'Rejected',
};

export function formatPayoutDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
}
