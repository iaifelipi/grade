<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_user_groups')) {
            Schema::create('tenant_user_groups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('tenant_uuid', 64)->nullable()->index();
                $table->string('name', 120);
                $table->string('slug', 120);
                $table->text('description')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'slug'], 'tenant_user_groups_tenant_slug_unique');
                $table->index(['tenant_uuid', 'is_active'], 'tenant_user_groups_uuid_active_idx');
            });
        }

        if (!Schema::hasTable('tenant_users')) {
            Schema::create('tenant_users', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('tenant_uuid', 64)->nullable()->index();

                $table->unsignedBigInteger('group_id')->nullable()->index();
                $table->unsignedBigInteger('invited_by_tenant_user_id')->nullable()->index();

                $table->string('first_name', 120)->nullable();
                $table->string('last_name', 120)->nullable();
                $table->string('name', 191)->nullable();
                $table->string('email', 190);
                $table->string('username', 64)->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');

                $table->string('phone_e164', 32)->nullable();
                $table->date('birth_date')->nullable();
                $table->string('locale', 10)->nullable();
                $table->string('timezone', 64)->nullable();

                $table->string('status', 20)->default('active')->index();
                $table->boolean('is_owner')->default(false);
                $table->boolean('is_primary_account')->default(false);
                $table->boolean('send_welcome_email')->default(true);
                $table->boolean('must_change_password')->default(false);

                $table->string('avatar_path', 255)->nullable();
                $table->string('avatar_disk', 32)->nullable();
                $table->timestamp('avatar_updated_at')->nullable();

                $table->timestamp('invited_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('deactivate_at')->nullable()->index();
                $table->timestamp('last_login_at')->nullable();
                $table->string('last_login_ip', 45)->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'email'], 'tenant_users_tenant_email_unique');
                $table->unique(['tenant_id', 'username'], 'tenant_users_tenant_username_unique');
                $table->index(['tenant_id', 'status'], 'tenant_users_tenant_status_idx');
                $table->index(['tenant_id', 'group_id'], 'tenant_users_tenant_group_idx');
                $table->index(['tenant_uuid', 'status'], 'tenant_users_uuid_status_idx');
            });
        }

        if (!Schema::hasTable('tenant_user_invitations')) {
            Schema::create('tenant_user_invitations', function (Blueprint $table) {
                $table->id();
                $table->string('token_hash', 64)->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('tenant_uuid', 64)->nullable()->index();

                $table->string('email', 190);
                $table->string('first_name', 120)->nullable();
                $table->string('last_name', 120)->nullable();
                $table->string('name', 120)->nullable();

                $table->unsignedBigInteger('group_id')->nullable()->index();
                $table->json('roles_json')->nullable();
                $table->json('permissions_json')->nullable();

                $table->unsignedBigInteger('inviter_user_id')->nullable()->index();
                $table->unsignedBigInteger('inviter_tenant_user_id')->nullable()->index();

                $table->timestamp('expires_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['email', 'accepted_at'], 'tenant_user_invitations_email_accepted_idx');
                $table->index(['tenant_uuid', 'accepted_at'], 'tenant_user_invitations_uuid_accepted_idx');
                $table->index(['tenant_id', 'accepted_at'], 'tenant_user_invitations_tenant_accepted_idx');
            });
        }

        Schema::table('tenant_user_groups', function (Blueprint $table) {
            $table->foreign('tenant_id', 'tenant_user_groups_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });

        Schema::table('tenant_users', function (Blueprint $table) {
            $table->foreign('tenant_id', 'tenant_users_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('group_id', 'tenant_users_group_fk')
                ->references('id')
                ->on('tenant_user_groups')
                ->nullOnDelete();

            $table->foreign('invited_by_tenant_user_id', 'tenant_users_inviter_tenant_user_fk')
                ->references('id')
                ->on('tenant_users')
                ->nullOnDelete();
        });

        Schema::table('tenant_user_invitations', function (Blueprint $table) {
            $table->foreign('tenant_id', 'tenant_user_invitations_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('group_id', 'tenant_user_invitations_group_fk')
                ->references('id')
                ->on('tenant_user_groups')
                ->nullOnDelete();

            $table->foreign('inviter_user_id', 'tenant_user_invitations_inviter_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('inviter_tenant_user_id', 'tenant_user_invitations_inviter_tenant_user_fk')
                ->references('id')
                ->on('tenant_users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_user_invitations')) {
            Schema::table('tenant_user_invitations', function (Blueprint $table) {
                $table->dropForeign('tenant_user_invitations_tenant_fk');
                $table->dropForeign('tenant_user_invitations_group_fk');
                $table->dropForeign('tenant_user_invitations_inviter_user_fk');
                $table->dropForeign('tenant_user_invitations_inviter_tenant_user_fk');
            });
        }

        if (Schema::hasTable('tenant_users')) {
            Schema::table('tenant_users', function (Blueprint $table) {
                $table->dropForeign('tenant_users_tenant_fk');
                $table->dropForeign('tenant_users_group_fk');
                $table->dropForeign('tenant_users_inviter_tenant_user_fk');
            });
        }

        if (Schema::hasTable('tenant_user_groups')) {
            Schema::table('tenant_user_groups', function (Blueprint $table) {
                $table->dropForeign('tenant_user_groups_tenant_fk');
            });
        }

        Schema::dropIfExists('tenant_user_invitations');
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenant_user_groups');
    }
};

