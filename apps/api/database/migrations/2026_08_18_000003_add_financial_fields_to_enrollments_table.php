<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->decimal('registration_amount', 10, 2)->nullable()->after('is_assigned');
            $table->decimal('installment_1_amount', 10, 2)->nullable()->after('registration_amount');
            $table->decimal('installment_2_amount', 10, 2)->nullable()->after('installment_1_amount');
            $table->decimal('installment_3_amount', 10, 2)->nullable()->after('installment_2_amount');
            $table->decimal('installment_4_amount', 10, 2)->nullable()->after('installment_3_amount');
            $table->decimal('installment_5_amount', 10, 2)->nullable()->after('installment_4_amount');
            $table->decimal('installment_6_amount', 10, 2)->nullable()->after('installment_5_amount');
            $table->decimal('installment_7_amount', 10, 2)->nullable()->after('installment_6_amount');
            $table->decimal('total_paid', 10, 2)->default(0)->after('installment_7_amount');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropColumn([
                'registration_amount',
                'installment_1_amount',
                'installment_2_amount',
                'installment_3_amount',
                'installment_4_amount',
                'installment_5_amount',
                'installment_6_amount',
                'installment_7_amount',
                'total_paid',
            ]);
        });
    }
};
