<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('transaction_code');
            $table->enum('type', ['setor_tunai', 'tarik_tunai', 'tarik_kas']);
            $table->decimal('amount', 15, 2);
            $table->string('processed_by');
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
