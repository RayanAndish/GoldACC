<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_activation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('hardware_id');
            $table->string('domain');
            $table->string('client_nonce');
            $table->string('server_nonce');
            $table->string('salt');
            $table->string('activation_ip');
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->string('ray_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            // ایندکس‌ها برای جستجوی سریع
            $table->index('hardware_id');
            $table->index('domain');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_activation_requests');
    }
}; 