<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('leads_normalized')) {
            return;
        }

        Schema::table('leads_normalized', function (Blueprint $table) {
            if (!Schema::hasColumn('leads_normalized', 'entity_type')) {
                $table->string('entity_type', 32)->nullable()->after('email')->index();
            }
            if (!Schema::hasColumn('leads_normalized', 'lifecycle_stage')) {
                $table->string('lifecycle_stage', 40)->nullable()->after('entity_type')->index();
            }
            if (!Schema::hasColumn('leads_normalized', 'whatsapp_e164')) {
                $table->string('whatsapp_e164', 32)->nullable()->after('phone_e164')->index();
            }

            if (!Schema::hasColumn('leads_normalized', 'optin_email')) {
                $table->boolean('optin_email')->default(false)->after('origin_id');
            }
            if (!Schema::hasColumn('leads_normalized', 'optin_sms')) {
                $table->boolean('optin_sms')->default(false)->after('optin_email');
            }
            if (!Schema::hasColumn('leads_normalized', 'optin_whatsapp')) {
                $table->boolean('optin_whatsapp')->default(false)->after('optin_sms');
            }

            if (!Schema::hasColumn('leads_normalized', 'consent_source')) {
                $table->string('consent_source', 120)->nullable()->after('optin_whatsapp');
            }
            if (!Schema::hasColumn('leads_normalized', 'consent_at')) {
                $table->timestamp('consent_at')->nullable()->after('consent_source');
            }

            if (!Schema::hasColumn('leads_normalized', 'last_interaction_at')) {
                $table->timestamp('last_interaction_at')->nullable()->after('consent_at')->index();
            }
            if (!Schema::hasColumn('leads_normalized', 'next_action_at')) {
                $table->timestamp('next_action_at')->nullable()->after('last_interaction_at')->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('leads_normalized')) {
            return;
        }

        Schema::table('leads_normalized', function (Blueprint $table) {
            if (Schema::hasColumn('leads_normalized', 'next_action_at')) {
                $table->dropColumn('next_action_at');
            }
            if (Schema::hasColumn('leads_normalized', 'last_interaction_at')) {
                $table->dropColumn('last_interaction_at');
            }
            if (Schema::hasColumn('leads_normalized', 'consent_at')) {
                $table->dropColumn('consent_at');
            }
            if (Schema::hasColumn('leads_normalized', 'consent_source')) {
                $table->dropColumn('consent_source');
            }
            if (Schema::hasColumn('leads_normalized', 'optin_whatsapp')) {
                $table->dropColumn('optin_whatsapp');
            }
            if (Schema::hasColumn('leads_normalized', 'optin_sms')) {
                $table->dropColumn('optin_sms');
            }
            if (Schema::hasColumn('leads_normalized', 'optin_email')) {
                $table->dropColumn('optin_email');
            }
            if (Schema::hasColumn('leads_normalized', 'whatsapp_e164')) {
                $table->dropColumn('whatsapp_e164');
            }
            if (Schema::hasColumn('leads_normalized', 'lifecycle_stage')) {
                $table->dropColumn('lifecycle_stage');
            }
            if (Schema::hasColumn('leads_normalized', 'entity_type')) {
                $table->dropColumn('entity_type');
            }
        });
    }
};
