<?php

namespace App\Reports\Extraction;

/**
 * The canonical extraction shape. Every reader produces it (the PDF layout readers in
 * Extraction/Pdf and the Fandaqah Reports Plus JSON mapper); validation and storage only
 * understand this shape:
 *
 *   report_type, report_title_as_printed, property_name, property_code, period_start,
 *   business_date, provisional, currency, confidence, issues,
 *   manager_flash { metrics[{metric, label_as_printed, day, mtd, ytd, day_ly, mtd_ly, ytd_ly}], revenue_departments[] }
 *   trial_balance { lines[{account_code, local_account_code, account_name, account_class, opening_balance, debit, credit, closing_balance}], printed_total_debit, printed_total_credit }
 *   front_office_trial_balance { transactions[], category_totals[], ledgers[{ledger, label_as_printed, opening_balance, movements[], closing_balance}], printed_hotel_balance }
 *   guest_trial_balance { lines[{room, name, arrival, departure, opening_balance, debit, transfer_debit, credit, transfer_credit, closing_balance, status}], printed_totals }
 */
final class ExtractionSchema
{
    public const PERIODS = ['day', 'mtd', 'ytd', 'day_ly', 'mtd_ly', 'ytd_ly'];

    /**
     * Canonical Manager Flash metrics => [daily_property_stats column, meaning].
     */
    public const FLASH_METRICS = [
        'total_rooms' => ['total_rooms', 'Total rooms in hotel (room inventory, room-nights for MTD/YTD). Not "Available Rooms" when that means vacant rooms.'],
        'rooms_occupied' => ['rooms_occupied', 'Rooms occupied / rooms sold, the plain line (including complimentary and house use). Not a "minus Comp/House" variant.'],
        'ooo_rooms' => ['ooo_rooms', 'Out of order rooms.'],
        'oos_rooms' => ['oos_rooms', 'Out of service rooms.'],
        'comp_rooms' => ['comp_rooms', 'Complimentary rooms.'],
        'house_use_rooms' => ['house_use_rooms', 'House use rooms.'],
        'day_use_rooms' => ['day_use_rooms', 'Day use rooms.'],
        'room_revenue' => ['room_revenue', 'Room revenue (total line, not block/individual splits).'],
        'fnb_revenue' => ['fnb_revenue', 'Food and beverage revenue.'],
        'other_revenue' => ['other_revenue', 'Other revenue.'],
        'total_revenue' => ['total_revenue', 'Total revenue.'],
        'persons_in_house' => ['persons_in_house', 'Total in-house persons / guests.'],
        'arrival_rooms' => ['arrival_rooms', 'Arrival rooms.'],
        'departure_rooms' => ['departure_rooms', 'Departure rooms.'],
        'no_show_rooms' => ['no_show_rooms', 'No-show rooms.'],
        'cancellations' => ['cancellations', 'Cancelled reservations for today (for the period).'],
        'occupancy_pct' => ['printed_occupancy_pct', 'The plain "% Rooms Occupied" / occupancy % line, as a 0-100 number. Not a "minus" variant.'],
        'adr' => ['printed_adr', 'The plain ADR / average room rate line. Not a "minus Comp/House" variant.'],
        'revpar' => ['printed_revpar', 'RevPAR, if printed.'],
    ];

    /** Metrics stored as whole numbers. */
    public const COUNT_METRICS = [
        'total_rooms', 'rooms_occupied', 'ooo_rooms', 'oos_rooms', 'comp_rooms', 'house_use_rooms',
        'day_use_rooms', 'persons_in_house', 'arrival_rooms', 'departure_rooms', 'no_show_rooms', 'cancellations',
    ];

    public const TRANSACTION_CATEGORIES = ['revenue', 'non_revenue', 'paid_out', 'payment', 'other'];

    public const LEDGERS = ['guest', 'ar', 'deposit', 'package', 'other'];
}
