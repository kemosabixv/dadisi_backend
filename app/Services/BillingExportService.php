<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\EventOrder;
use App\Models\Event;
use App\Models\County;
use Illuminate\Support\Facades\DB;
use League\Csv\Writer;
use SplTempFileObject;

/**
 * BillingExportService
 *
 * Handles CSV exports for billing, donations, and event orders.
 * Supports filtering by date range, county, and event.
 */
class BillingExportService
{
    /**
     * Export donations to CSV
     */
    public function exportDonations($startDate = null, $endDate = null, $countyId = null, $status = null): string
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());

        // Add headers
        $csv->insertOne([
            'ID',
            'Reference',
            'Donor Name',
            'Donor Email',
            'Donor Phone',
            'County',
            'Amount',
            'Currency',
            'Status',
            'Receipt Number',
            'Date Created',
            'Date Paid',
        ]);

        // Build query
        $query = Donation::query()
            ->with(['county'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        if ($countyId) {
            $query->where('county_id', $countyId);
        }
        if ($status) {
            $query->where('status', $status);
        }

        // Add rows
        foreach ($query->get() as $donation) {
            $csv->insertOne([
                $donation->id,
                $donation->reference,
                $donation->donor_name,
                $donation->donor_email,
                $donation->donor_phone ?? 'N/A',
                $donation->county?->name ?? 'N/A',
                number_format($donation->amount, 2),
                $donation->currency,
                ucfirst($donation->status),
                $donation->receipt_number ?? 'N/A',
                $donation->created_at->format('Y-m-d H:i:s'),
                $donation->payment?->paid_at?->format('Y-m-d H:i:s') ?? 'Pending',
            ]);
        }

        return $csv->getContent();
    }

    /**
     * Export event orders to CSV
     */
    public function exportEventOrders($startDate = null, $endDate = null, $eventId = null, $status = null): string
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());

        // Add headers
        $csv->insertOne([
            'ID',
            'Reference',
            'Event',
            'User',
            'Quantity',
            'Unit Price',
            'Total Amount',
            'Currency',
            'Status',
            'Receipt Number',
            'Date Created',
            'Date Purchased',
            'Date Paid',
        ]);

        // Build query
        $query = EventOrder::query()
            ->with(['event', 'user'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        if ($eventId) {
            $query->where('event_id', $eventId);
        }
        if ($status) {
            $query->where('status', $status);
        }

        // Add rows
        foreach ($query->get() as $order) {
            $csv->insertOne([
                $order->id,
                $order->reference,
                $order->event?->title ?? 'N/A',
                $order->user?->email ?? 'N/A',
                $order->quantity,
                $order->unit_price ? number_format($order->unit_price, 2) : 'N/A',
                number_format($order->total_amount, 2),
                $order->currency,
                ucfirst($order->status),
                $order->receipt_number ?? 'N/A',
                $order->created_at->format('Y-m-d H:i:s'),
                $order->purchased_at?->format('Y-m-d H:i:s') ?? 'Pending',
                $order->payment?->paid_at?->format('Y-m-d H:i:s') ?? 'Pending',
            ]);
        }

        return $csv->getContent();
    }

    /**
     * Export donation summary by county
     */
    public function exportDonationSummaryByCounty($startDate = null, $endDate = null): string
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());

        // Add headers
        $csv->insertOne([
            'County',
            'Total Donations',
            'Paid Donations',
            'Pending Donations',
            'Failed Donations',
            'Total Paid (KES)',
            'Total Pending (KES)',
            'Average Donation (KES)',
        ]);

        // Build query
        $query = DB::table('donations')
            ->select(
                'counties.name',
                DB::raw('COUNT(*) as total_donations'),
                DB::raw('SUM(CASE WHEN donations.status = "paid" THEN 1 ELSE 0 END) as paid_donations'),
                DB::raw('SUM(CASE WHEN donations.status = "pending" THEN 1 ELSE 0 END) as pending_donations'),
                DB::raw('SUM(CASE WHEN donations.status = "failed" THEN 1 ELSE 0 END) as failed_donations'),
                DB::raw('SUM(CASE WHEN donations.status = "paid" THEN donations.amount ELSE 0 END) as total_paid'),
                DB::raw('SUM(CASE WHEN donations.status = "pending" THEN donations.amount ELSE 0 END) as total_pending'),
                DB::raw('AVG(CASE WHEN donations.status = "paid" THEN donations.amount ELSE NULL END) as avg_donation'),
            )
            ->join('counties', 'donations.county_id', '=', 'counties.id')
            ->groupBy('counties.id', 'counties.name');

        // Apply date filters
        if ($startDate) {
            $query->where('donations.created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('donations.created_at', '<=', $endDate);
        }

        $query->orderBy('total_paid', 'desc');

        // Add rows
        foreach ($query->get() as $row) {
            $csv->insertOne([
                $row->name,
                $row->total_donations,
                $row->paid_donations,
                $row->pending_donations,
                $row->failed_donations,
                number_format($row->total_paid, 2),
                number_format($row->total_pending, 2),
                number_format($row->avg_donation, 2),
            ]);
        }

        return $csv->getContent();
    }

    /**
     * Export event sales summary
     */
    public function exportEventSalesSummary($startDate = null, $endDate = null): string
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());

        // Add headers
        $csv->insertOne([
            'Event',
            'Total Orders',
            'Paid Orders',
            'Pending Orders',
            'Total Tickets Sold',
            'Total Revenue (KES)',
            'Pending Revenue (KES)',
            'Average Order Value (KES)',
        ]);

        // Build query
        $query = DB::table('event_orders')
            ->select(
                'events.title',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(CASE WHEN event_orders.status = "paid" THEN 1 ELSE 0 END) as paid_orders'),
                DB::raw('SUM(CASE WHEN event_orders.status = "pending" THEN 1 ELSE 0 END) as pending_orders'),
                DB::raw('SUM(CASE WHEN event_orders.status = "paid" THEN event_orders.quantity ELSE 0 END) as total_tickets'),
                DB::raw('SUM(CASE WHEN event_orders.status = "paid" THEN event_orders.total_amount ELSE 0 END) as total_revenue'),
                DB::raw('SUM(CASE WHEN event_orders.status = "pending" THEN event_orders.total_amount ELSE 0 END) as pending_revenue'),
                DB::raw('AVG(CASE WHEN event_orders.status = "paid" THEN event_orders.total_amount ELSE NULL END) as avg_order_value'),
            )
            ->join('events', 'event_orders.event_id', '=', 'events.id')
            ->groupBy('events.id', 'events.title');

        // Apply date filters
        if ($startDate) {
            $query->where('event_orders.created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('event_orders.created_at', '<=', $endDate);
        }

        $query->orderBy('total_revenue', 'desc');

        // Add rows
        foreach ($query->get() as $row) {
            $csv->insertOne([
                $row->title,
                $row->total_orders,
                $row->paid_orders,
                $row->pending_orders,
                $row->total_tickets ?? 0,
                number_format($row->total_revenue, 2),
                number_format($row->pending_revenue, 2),
                number_format($row->avg_order_value, 2),
            ]);
        }

        return $csv->getContent();
    }

    /**
     * Export financial reconciliation report
     */
    public function exportFinancialReconciliation($startDate = null, $endDate = null): string
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());

        // Build donation query
        $donationQuery = DB::table('donations')
            ->select(
                'donations.reference',
                'donations.donor_name',
                'donations.amount',
                DB::raw('"donation" as type'),
                'donations.status',
                'donations.created_at',
                'payments.payment_id',
                'payments.external_reference',
                'payments.paid_at',
            )
            ->leftJoin('payments', function ($join) {
                $join->on('donations.payment_id', '=', 'payments.id');
            });

        // Build event order query
        $orderQuery = DB::table('event_orders')
            ->select(
                'event_orders.reference',
                'event_orders.attendee_name',
                'event_orders.total_amount as amount',
                DB::raw('"event_order" as type'),
                'event_orders.status',
                'event_orders.created_at',
                'payments.id as payment_id',
                'payments.external_reference',
                'payments.paid_at',
            )
            ->leftJoin('payments', function ($join) {
                $join->on('event_orders.payment_id', '=', 'payments.id');
            });

        // Union and apply filters
        $query = $donationQuery->union($orderQuery);

        if ($startDate) {
            $donationQuery->where('donations.created_at', '>=', $startDate);
            $orderQuery->where('event_orders.created_at', '>=', $startDate);
        }
        if ($endDate) {
            $donationQuery->where('donations.created_at', '<=', $endDate);
            $orderQuery->where('event_orders.created_at', '<=', $endDate);
        }

        // Add headers
        $csv->insertOne([
            'Reference',

            'Name/Email',
            'Type',
            'Amount (KES)',
            'Status',
            'Date Created',
            'Payment ID',
            'External Reference',
            'Date Paid',
        ]);

        // Add rows
        foreach ($query->orderByDesc('created_at')->get() as $row) {
            $csv->insertOne([
                $row->reference,
                $row->donor_name ?? ($row->user_id ?? 'Guest'),
                ucfirst($row->type),
                number_format($row->amount, 2),
                ucfirst($row->status),
                (new \DateTime($row->created_at))->format('Y-m-d H:i:s'),
                $row->payment_id ?? 'N/A',
                $row->external_reference ?? 'N/A',
                $row->paid_at ? (new \DateTime($row->paid_at))->format('Y-m-d H:i:s') : 'Pending',
            ]);
        }

        return $csv->getContent();
    }

    /**
     * Generate filename with timestamp
     */
    public function generateFilename(string $type): string
    {
        $timestamp = now()->format('Y-m-d_Hi');
        return "{$type}_{$timestamp}.csv";
    }
}
