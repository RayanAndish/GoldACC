<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('name'); // Add company_name after name
            $table->string('organizational_email')->nullable()->unique()->after('email'); // Add organizational_email after email
            $table->string('domain_name')->nullable()->after('organizational_email'); // Add domain_name after organizational_email
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop columns in reverse order of creation
            $table->dropColumn('domain_name');
            $table->dropUnique(['organizational_email']); // Need to drop unique index first
            $table->dropColumn('organizational_email');
            $table->dropColumn('company_name');
        });
    }
};