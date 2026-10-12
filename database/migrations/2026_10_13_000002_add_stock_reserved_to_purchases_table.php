<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchases') && ! Schema::hasColumn('purchases', 'stock_reserved')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->boolean('stock_reserved')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchases') && Schema::hasColumn('purchases', 'stock_reserved')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropColumn('stock_reserved');
            });
        }
    }
};

