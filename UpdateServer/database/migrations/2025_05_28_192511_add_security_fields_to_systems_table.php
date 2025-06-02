<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            // اضافه کردن فیلدهای امنیتی
            $table->string('activation_salt', 64)->after('handshake_string')->comment('Salt برای تولید و تایید کد درخواست');
            $table->string('client_nonce_salt', 64)->after('activation_salt')->comment('Salt برای تولید client_nonce');
            $table->string('server_nonce_salt', 64)->after('client_nonce_salt')->comment('Salt برای تولید server_nonce');
            $table->string('hardware_id_salt', 64)->after('server_nonce_salt')->comment('Salt برای تولید و تایید hardware_id');
            $table->string('request_code_salt', 64)->after('hardware_id_salt')->comment('Salt برای تولید و تایید request_code');
            
            // اضافه کردن ایندکس‌ها
            $table->index('activation_salt');
            $table->index('client_nonce_salt');
            $table->index('server_nonce_salt');
            $table->index('hardware_id_salt');
            $table->index('request_code_salt');
        });
    }

    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            // حذف ایندکس‌ها
            $table->dropIndex(['activation_salt']);
            $table->dropIndex(['client_nonce_salt']);
            $table->dropIndex(['server_nonce_salt']);
            $table->dropIndex(['hardware_id_salt']);
            $table->dropIndex(['request_code_salt']);
            
            // حذف فیلدها
            $table->dropColumn([
                'activation_salt',
                'client_nonce_salt',
                'server_nonce_salt',
                'hardware_id_salt',
                'request_code_salt'
            ]);
        });
    }
}; 