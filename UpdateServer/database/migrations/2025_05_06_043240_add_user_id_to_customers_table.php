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
        Schema::table('customers', function (Blueprint $table) {
            // Add the user_id column
            $table->foreignId('user_id')
                  ->nullable() // Allow customers without a linked user initially
                  ->after('id') // Or place it wherever you prefer
                  ->constrained('users') // Define the foreign key constraint to the users table
                  ->onDelete('set null'); // Optional: What happens if the user is deleted? 'set null', 'cascade', 'restrict'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['user_id']);
            // Drop the column
            $table->dropColumn('user_id');
        });
    }
};