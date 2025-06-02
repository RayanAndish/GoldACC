<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('systems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('name');
            $table->string('domain')->unique();
            $table->string('status')->default('active');
            $table->string('current_version')->nullable();
            $table->timestamps();
        });

        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->nullable()->constrained('systems')->onDelete('set null');
            $table->string('license_key')->unique();
            $table->string('status')->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('encryption_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->constrained('systems')->onDelete('cascade');
            $table->text('key_value');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->constrained('systems')->onDelete('cascade');
            $table->string('file_path');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('restored_at')->nullable();
            $table->string('status')->default('pending');
        });

        Schema::create('versions', function (Blueprint $table) {
            $table->id();
            $table->string('version_code')->unique();
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamp('release_date')->nullable();
        });

        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->nullable()->constrained('systems')->onDelete('cascade');
            $table->string('type');
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('admin');
            $table->timestamps();
        });

        Schema::create('sms_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->constrained('systems')->onDelete('cascade');
            $table->string('provider');
            $table->string('api_key');
            $table->string('sender_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->constrained('systems')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('to_number');
            $table->text('message');
            $table->string('status')->default('pending');
            $table->text('response')->nullable();
            $table->timestamp('sent_at')->nullable();
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->constrained('systems')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('to_email');
            $table->string('subject');
            $table->text('message');
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
        });

        Schema::create('customer_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('system_id')->nullable()->constrained('systems')->onDelete('cascade');
            $table->string('action_type');
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_activity_logs');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('sms_settings');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('logs');
        Schema::dropIfExists('versions');
        Schema::dropIfExists('backups');
        Schema::dropIfExists('encryption_keys');
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('systems');
        Schema::dropIfExists('customers');
    }
};