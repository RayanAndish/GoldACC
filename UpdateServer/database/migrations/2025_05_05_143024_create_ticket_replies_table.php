<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // User reply, set null if user deleted
            $table->foreignId('admin_id')->nullable()->constrained('admins')->onDelete('set null'); // Admin reply, set null if admin deleted
            $table->text('message');
            $table->timestamps();

            // Ensure only one of user_id or admin_id is set (optional constraint)
            // $table->check('(user_id IS NOT NULL AND admin_id IS NULL) OR (user_id IS NULL AND admin_id IS NOT NULL)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_replies');
    }
};
