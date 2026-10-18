<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Currencies an invoice can be billed in. Purchases are made wherever the
 * ship happens to be - a Chinese yard bills in RMB, a Rotterdam chandler in
 * EUR - so the invoice can no longer assume BDT.
 *
 * is_top marks the handful used week in, week out; the picker pins those
 * above the full list (in sort_order) so nobody scrolls past Albanian Lek to
 * find US Dollars. status lets super-admin retire one without deleting it
 * from invoices that were already billed in it.
 */
return new class extends Migration
{
    /** [code, name, symbol, is_top] - top ones in the order they're pinned. */
    private const CURRENCIES = [
        ['BDT', 'Bangladeshi Taka', '৳', true],
        ['USD', 'US Dollar', '$', true],
        ['EUR', 'Euro', '€', true],
        ['CNY', 'Chinese Yuan (RMB)', '¥', true],
        ['SGD', 'Singapore Dollar', 'S$', true],
        ['GBP', 'British Pound', '£', true],
        ['INR', 'Indian Rupee', '₹', true],
        ['AED', 'UAE Dirham', 'AED', true],
        ['JPY', 'Japanese Yen', '¥', true],

        ['AUD', 'Australian Dollar', 'A$', false],
        ['BHD', 'Bahraini Dinar', 'BHD', false],
        ['BRL', 'Brazilian Real', 'R$', false],
        ['CAD', 'Canadian Dollar', 'C$', false],
        ['CHF', 'Swiss Franc', 'CHF', false],
        ['CLP', 'Chilean Peso', '$', false],
        ['CZK', 'Czech Koruna', 'Kč', false],
        ['DKK', 'Danish Krone', 'kr', false],
        ['EGP', 'Egyptian Pound', 'E£', false],
        ['HKD', 'Hong Kong Dollar', 'HK$', false],
        ['IDR', 'Indonesian Rupiah', 'Rp', false],
        ['ILS', 'Israeli New Shekel', '₪', false],
        ['IQD', 'Iraqi Dinar', 'IQD', false],
        ['IRR', 'Iranian Rial', 'IRR', false],
        ['JOD', 'Jordanian Dinar', 'JOD', false],
        ['KES', 'Kenyan Shilling', 'KSh', false],
        ['KRW', 'South Korean Won', '₩', false],
        ['KWD', 'Kuwaiti Dinar', 'KWD', false],
        ['LKR', 'Sri Lankan Rupee', 'Rs', false],
        ['MMK', 'Myanmar Kyat', 'K', false],
        ['MXN', 'Mexican Peso', '$', false],
        ['MYR', 'Malaysian Ringgit', 'RM', false],
        ['NGN', 'Nigerian Naira', '₦', false],
        ['NOK', 'Norwegian Krone', 'kr', false],
        ['NZD', 'New Zealand Dollar', 'NZ$', false],
        ['OMR', 'Omani Rial', 'OMR', false],
        ['PHP', 'Philippine Peso', '₱', false],
        ['PKR', 'Pakistani Rupee', 'Rs', false],
        ['PLN', 'Polish Złoty', 'zł', false],
        ['QAR', 'Qatari Riyal', 'QAR', false],
        ['RUB', 'Russian Ruble', '₽', false],
        ['SAR', 'Saudi Riyal', 'SAR', false],
        ['SEK', 'Swedish Krona', 'kr', false],
        ['THB', 'Thai Baht', '฿', false],
        ['TRY', 'Turkish Lira', '₺', false],
        ['TWD', 'New Taiwan Dollar', 'NT$', false],
        ['UAH', 'Ukrainian Hryvnia', '₴', false],
        ['VND', 'Vietnamese Dong', '₫', false],
        ['ZAR', 'South African Rand', 'R', false],
    ];

    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('code', 3)->unique();      // ISO 4217
            $table->string('name', 60);
            $table->string('symbol', 8)->nullable();
            $table->boolean('is_top')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('currencies')->insert(array_map(
            fn ($c, $i) => [
                'code' => $c[0], 'name' => $c[1], 'symbol' => $c[2], 'is_top' => $c[3],
                'sort_order' => $i + 1, 'status' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            self::CURRENCIES,
            array_keys(self::CURRENCIES)
        ));

        // Every invoice already captured was entered as BDT - the only
        // currency the screen offered - so that's what the default records.
        foreach (['order_invoices', 'service_requisition_invoices'] as $invoiceTable) {
            Schema::table($invoiceTable, function (Blueprint $table) {
                $table->char('currency_code', 3)->default('BDT')->after('invoice_date');
                $table->foreign('currency_code')->references('code')->on('currencies');
            });
        }
    }

    public function down(): void
    {
        foreach (['order_invoices', 'service_requisition_invoices'] as $invoiceTable) {
            Schema::table($invoiceTable, function (Blueprint $table) {
                $table->dropForeign(['currency_code']);
                $table->dropColumn('currency_code');
            });
        }

        Schema::dropIfExists('currencies');
    }
};
