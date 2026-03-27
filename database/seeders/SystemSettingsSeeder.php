<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Footer & Branding Settings
            [
                'key' => 'brand_name',
                'value' => 'Dadisi Community Labs',
                'group' => 'footer',
                'type' => 'string',
                'description' => 'The official name of the organization',
                'is_public' => true,
            ],
            [
                'key' => 'brand_description',
                'value' => 'Discovering together. Inclusive labs and programs for Kenya.',
                'group' => 'footer',
                'type' => 'string',
                'description' => 'Discovery description displayed in the site footer',
                'is_public' => true,
            ],
            [
                'key' => 'contact_email',
                'value' => 'info@dadisilab.com',
                'group' => 'footer',
                'type' => 'string',
                'description' => 'Information email address',
                'is_public' => true,
            ],
            [
                'key' => 'support_email',
                'value' => 'support@dadisilab.com',
                'group' => 'footer',
                'type' => 'string',
                'description' => 'Support email address',
                'is_public' => true,
            ],
            [
                'key' => 'contact_phone',
                'value' => '+254-768-376-822',
                'group' => 'footer',
                'type' => 'string',
                'description' => 'Primary contact line',
                'is_public' => true,
            ],
            [
                'key' => 'contact_address',
                'value' => "1.9A Kitisuru Road\nNairobi",
                'group' => 'footer',
                'type' => 'string',
                'description' => 'Physical location',
                'is_public' => true,
            ],
            [
                'key' => 'social_links',
                'value' => [
                    'facebook' => 'https://www.facebook.com/people/Dadisi-Labs/100087106885614',
                ],
                'group' => 'footer',
                'type' => 'json',
                'description' => 'Social media platform URLs',
                'is_public' => true,
            ],
            [
                'key' => 'quick_links',
                'value' => [
                    ['label' => 'Privacy Policy', 'url' => '/privacy'],
                    ['label' => 'Terms of Service', 'url' => '/terms'],
                    ['label' => 'About Us', 'url' => '/#about'],
                    ['label' => 'Contact Us', 'url' => '/contact'],
                ],
                'group' => 'footer',
                'type' => 'json',
                'description' => 'Quick links for footer navigation',
                'is_public' => true,
            ],

            // Legal & Operations
            [
                'key' => 'event_cancellation_deadline_days',
                'value' => '2',
                'group' => 'events',
                'type' => 'integer',
                'description' => 'Number of days before an event when cancellations become non-refundable',
                'is_public' => true,
            ],
            [
                'key' => 'lab_booking_cancellation_deadline_days',
                'value' => '1',
                'group' => 'lab_spaces',
                'type' => 'integer',
                'description' => 'Number of days before a lab space booking starts where users can still cancel for a refund.',
                'is_public' => true,
            ],
            [
                'key' => 'subscription_refund_threshold_monthly_days',
                'value' => '14',
                'group' => 'subscriptions',
                'type' => 'integer',
                'description' => 'Number of days after payment during which a monthly subscription is eligible for a refund',
                'is_public' => true,
            ],
            [
                'key' => 'subscription_refund_threshold_yearly_days',
                'value' => '90',
                'group' => 'subscriptions',
                'type' => 'integer',
                'description' => 'Number of days after payment during which a yearly subscription is eligible for a refund',
                'is_public' => true,
            ],
            [
                'key' => 'terms_and_conditions',
                'value' => '<h2>Terms of Service</h2>
<p>Welcome to Dadisi Community Labs. By accessing our platform, you agree to comply with these terms.</p>
<h3>1. Account Registration</h3>
<p>Members must provide accurate information, including their county of residence, as required for our NGO compliance reporting.</p>
<h3>2. Events and Tickets</h3>
<p>Ticket purchases are processed through Pesapal. Cancellations must be made at least 2 days before the event to be eligible for a refund, unless otherwise stated.</p>
<h3>3. Donations</h3>
<p>Donations are non-refundable and will be used to support Dadisi Community Labs programs across Kenya.</p>
<h3>4. Prohibited Conduct</h3>
<p>Users may not use the platform for any unlawful purpose or to distribute harmful content.</p>
<h3 id="lab-refunds">5. Lab Cancellation & Refund Policy</h3>
<p>Lab reservations are subject to monthly quotas or hourly rates. Cancellations apply to the <strong>entire booking series</strong>.</p>
<ul>
    <li><strong>Refund Eligibility:</strong> Only "No-show" and "Future" slots are eligible for refunds. "Attended" slots are strictly non-refundable.</li>
    <li><strong>Card Payments:</strong> Partial refunds are permitted for all eligible (No-show or Future) slots within the booking.</li>
    <li><strong>M-Pesa Payments:</strong> Refunds are only available as a <strong>Full Refund</strong>. If any single slot in the booking has already been "Attended", the entire transaction becomes ineligible for a refund.</li>
    <li><strong>Quota Restoration:</strong> Quota hours for eligible slots are restored if the cancellation occurs before your current subscription month resets.</li>
</ul>
<h3>6. Governing Law</h3>
<p>These terms are governed by the laws of the Republic of Kenya.</p>',
                'group' => 'legal',
                'type' => 'string',
                'description' => 'Global terms of service for the platform',
                'is_public' => true,
            ],
            [
                'key' => 'privacy_policy',
                'value' => '<h2>Privacy Policy</h2>
<p>At Dadisi Community Labs, we take your privacy seriously. This policy explains how we handle your data.</p>
<h3>1. Data Collection</h3>
<p>We collect personal identifiers, demographics, and county information to facilitate our programs and satisfy NGO reporting requirements.</p>
<h3>2. Payment Security</h3>
<p>All financial transactions are handled securely by Pesapal. We do not store full credit card details on our servers.</p>
<h3>3. Data Protection</h3>
<p>In accordance with the Kenya Data Protection Act, we implement strict measures to protect your information from unauthorized access.</p>
<h3>4. Third-Party Sharing</h3>
<p>We do not sell your data. We only share information with partners (like county governments or payment gateways) when necessary to provide our services.</p>
<h3>5. Your Rights</h3>
<p>You have the right to access, correct, or request the deletion of your personal data at any time.</p>',
                'group' => 'legal',
                'type' => 'string',
                'description' => 'Global privacy policy for the platform',
                'is_public' => true,
            ],
            [
                'key' => 'membership_page_user_list_enabled',
                'value' => 'true',
                'group' => 'membership',
                'type' => 'boolean',
                'description' => 'Whether to display the community member list on the public membership page.',
                'is_public' => true,
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
