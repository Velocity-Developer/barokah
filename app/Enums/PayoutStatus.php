<?php

namespace App\Enums;

/**
 * Seller payout lifecycle. The money itself moves outside the site; these
 * steps only track the request, the admin decision and the transfer proof.
 */
enum PayoutStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';

    /**
     * Requests still waiting on the admin (their orders stay locked).
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Pending, self::Approved];
    }
}
