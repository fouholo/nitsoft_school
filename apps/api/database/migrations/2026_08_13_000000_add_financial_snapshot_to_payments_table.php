<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('tuition_paid_total', 12, 2)->nullable()->after('reference');
            $table->decimal('tuition_remaining', 12, 2)->nullable()->after('tuition_paid_total');
            $table->date('next_installment_due_date')->nullable()->after('tuition_remaining');
            $table->decimal('next_installment_amount', 12, 2)->nullable()->after('next_installment_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'tuition_paid_total',
                'tuition_remaining',
                'next_installment_due_date',
                'next_installment_amount',
            ]);
        });
    }
};
