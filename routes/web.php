<?php

use Illuminate\Support\Facades\Route;
use App\Models\LeadSource;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileModalController;

use App\Http\Controllers\LeadsVault\VaultSourcesController;
use App\Http\Controllers\LeadsVault\VaultExploreController;
use App\Http\Controllers\LeadsVault\VaultExploreMarketingController;
use App\Http\Controllers\LeadsVault\VaultSemanticController;
use App\Http\Controllers\LeadsVault\VaultAutomationController;
use App\Http\Controllers\LeadsVault\VaultOperationalController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Admin\AdminAuditAdminController;
use App\Http\Controllers\Admin\TenantUserAdminController;
use App\Http\Controllers\Admin\TenantUserGroupAdminController;
use App\Http\Controllers\Tenant\TenantInviteController;
use App\Http\Controllers\Tenant\TenantModuleController;
use App\Http\Controllers\Admin\RoleAdminController;
use App\Http\Controllers\Admin\PlanAdminController;
use App\Http\Controllers\Admin\SemanticTaxonomyController;
use App\Http\Controllers\Admin\LeadColumnAdminController;
use App\Http\Controllers\Admin\LeadDataQualityController;
use App\Http\Controllers\Admin\BugReportAdminController;
use App\Http\Controllers\Admin\GuestAuditAdminController;
use App\Http\Controllers\Admin\MonitoringAdminController;
use App\Http\Controllers\Admin\SecurityAccessAdminController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\CustomerImportFileAdminController;
use App\Http\Controllers\Admin\Monetization\MonetizationDashboardController;
use App\Http\Controllers\Admin\Monetization\PaymentGatewayAdminController;
use App\Http\Controllers\Admin\Monetization\PricePlanAdminController;
use App\Http\Controllers\Admin\Monetization\OrderAdminController;
use App\Http\Controllers\Admin\Monetization\PromoCodeAdminController;
use App\Http\Controllers\Admin\Monetization\CurrencyAdminController;
use App\Http\Controllers\Admin\Monetization\TaxRateAdminController;
use App\Http\Controllers\Support\BugReportController;
use App\Http\Controllers\Webhooks\IntegrationWebhookController;


/*
|--------------------------------------------------------------------------
| HOME
|--------------------------------------------------------------------------
*/

Route::get('/', [VaultExploreController::class, 'index'])->name('home');

/*
|--------------------------------------------------------------------------
| LEGAL (public)
|--------------------------------------------------------------------------
*/
Route::view('/termos-e-politica', 'legal.terms')->name('legal.terms');

/*
|--------------------------------------------------------------------------
| GUEST EXPLORE (public)
|--------------------------------------------------------------------------
*/
Route::prefix('explore')
    ->name('guest.explore.')
    ->controller(VaultExploreController::class)
    ->group(function () {
        Route::get('/', fn () => redirect()->route('home'))->name('legacy');
        Route::get('/db', 'db')->name('db');
        Route::get('/source/clear', 'clearSource')->name('clearSource');
        Route::get('/source/{id}', 'selectSource')->name('selectSource');
        Route::get('/semantic-options', 'semanticOptions')->name('options');
        Route::get('/semantic-autocomplete', 'semanticAutocomplete')->name('semanticAutocomplete');
        Route::get('/semantic-autocomplete-unified', 'semanticAutocompleteUnified')->name('semanticAutocompleteUnified');
        Route::get('/semantic', 'semanticLoad')->name('semanticLoad');
        Route::post('/semantic', 'semanticSave')->name('semanticSave');
        Route::get('/sources', 'sourcesList')->name('sourcesList');
    });

Route::middleware(['auth','tenant','superadmin.readonly'])
    ->prefix('explore')
    ->name('explore.')
    ->group(function () {
        Route::get('/columns', [LeadColumnAdminController::class, 'index'])
            ->middleware('permission:system.settings')
            ->name('columns.index');

        Route::get('/columns/modal', [LeadColumnAdminController::class, 'modal'])
            ->middleware('permission:system.settings')
            ->name('columns.modal');

        Route::get('/columns/data', [LeadColumnAdminController::class, 'data'])
            ->middleware('permission:system.settings')
            ->name('columns.data');

        Route::get('/columns/source/clear', [LeadColumnAdminController::class, 'clearSource'])
            ->middleware('permission:system.settings')
            ->name('columns.clearSource');

        Route::get('/columns/source/{id}', [LeadColumnAdminController::class, 'selectSource'])
            ->middleware('permission:system.settings')
            ->name('columns.selectSource');

        Route::post('/columns', [LeadColumnAdminController::class, 'store'])
            ->middleware('permission:system.settings')
            ->name('columns.store');

        Route::post('/columns/save', [LeadColumnAdminController::class, 'save'])
            ->middleware('permission:system.settings')
            ->name('columns.save');

        Route::post('/columns/reset', [LeadColumnAdminController::class, 'resetDefaults'])
            ->middleware('permission:system.settings')
            ->name('columns.reset');

        Route::post('/columns/bulk-delete', [LeadColumnAdminController::class, 'bulkDelete'])
            ->middleware('permission:system.settings')
            ->name('columns.bulkDelete');

        Route::post('/columns/merge', [LeadColumnAdminController::class, 'merge'])
            ->middleware('permission:system.settings')
            ->name('columns.merge');

        Route::delete('/columns/{id}', [LeadColumnAdminController::class, 'destroy'])
            ->middleware('permission:system.settings')
            ->name('columns.destroy');

        Route::get('/data-quality', [LeadDataQualityController::class, 'index'])
            ->middleware('permission:system.settings')
            ->name('dataQuality.index');

        Route::get('/data-quality/modal', [LeadDataQualityController::class, 'modal'])
            ->middleware('permission:system.settings')
            ->name('dataQuality.modal');

        Route::get('/data-quality/statuses', [LeadDataQualityController::class, 'statuses'])
            ->middleware('permission:system.settings')
            ->name('dataQuality.statuses');

        Route::post('/data-quality/preview', [LeadDataQualityController::class, 'preview'])
            ->middleware('permission:system.settings')
            ->name('dataQuality.preview');

        Route::post('/data-quality/apply', [LeadDataQualityController::class, 'apply'])
            ->middleware('permission:system.settings')
            ->name('dataQuality.apply');

        Route::post('/data-quality/discard/{id}', [LeadDataQualityController::class, 'discard'])
            ->middleware('permission:system.settings')
            ->name('dataQuality.discard');
    });

Route::prefix('sources')
    ->name('guest.sources.')
    ->controller(VaultSourcesController::class)
    ->group(function () {
        Route::post('/', 'store')->name('store');
        Route::get('/status', 'status')->name('status');
        Route::get('/health', 'health')->name('health');
        Route::post('/purge-selected', 'purgeSelected')->name('purgeSelected');
    });

Route::post('/reports', [BugReportController::class, 'store'])
    ->name('reports.store');


/*
|--------------------------------------------------------------------------
| DASHBOARD
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', fn () => redirect()->route('home'))
    ->middleware(['auth','tenant','superadmin.readonly'])
    ->name('dashboard');


/*
|--------------------------------------------------------------------------
| PROFILE
|--------------------------------------------------------------------------
*/

Route::middleware(['auth','tenant','superadmin.readonly'])->group(function () {

    Route::get('/profile',   [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',[ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/modal', [ProfileModalController::class, 'update'])->name('profile.modal.update');
    Route::put('/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');
    Route::delete('/profile',[ProfileController::class, 'destroy'])->name('profile.destroy');

});


/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/

Route::middleware(['auth','tenant','superadmin.readonly'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/users', [UserAdminController::class, 'index'])
            ->middleware('permission:users.manage')
            ->name('users.index');

        Route::post('/users', [UserAdminController::class, 'store'])
            ->middleware('permission:users.manage')
            ->name('users.store');

        Route::put('/users/{id}', [UserAdminController::class, 'update'])
            ->middleware('permission:users.manage')
            ->name('users.update');

        Route::delete('/users/{id}', [UserAdminController::class, 'destroy'])
            ->middleware('permission:users.manage')
            ->name('users.destroy');

        Route::post('/users/{id}/disable', [UserAdminController::class, 'disable'])
            ->middleware('permission:users.manage')
            ->name('users.disable');

        Route::post('/users/{id}/enable', [UserAdminController::class, 'enable'])
            ->middleware('permission:users.manage')
            ->name('users.enable');

        Route::post('/users/{id}/roles', [UserAdminController::class, 'updateRoles'])
            // Users screen includes per-user role assignment; gate it with users.manage (same as the screen).
            // Role matrix editing still lives under roles.manage.
            ->middleware('permission:users.manage')
            ->name('users.roles.update');

        Route::post('/users/{id}/impersonate', [UserAdminController::class, 'impersonate'])
            ->middleware('permission:users.manage')
            ->name('users.impersonate');

        Route::post('/users/{id}/promote', [UserAdminController::class, 'promoteToAdmin'])
            ->middleware('permission:users.manage')
            ->name('users.promote');

        Route::post('/impersonate/stop', [UserAdminController::class, 'stopImpersonate'])
            ->middleware('permission:users.manage')
            ->name('impersonate.stop');

        Route::get('/tenant-users', [TenantUserAdminController::class, 'index'])
            ->middleware('permission:users.manage')
            ->name('tenantUsers.index');
        Route::get('/customers', [TenantUserAdminController::class, 'index'])
            ->middleware('permission:users.manage');
        Route::get('/lists', [CustomerImportFileAdminController::class, 'index'])
            ->middleware('permission:users.manage')
            ->name('customers.files.index');
        Route::get('/files', function (\Illuminate\Http\Request $request) {
            return redirect()->route('admin.customers.files.index', $request->query(), 301);
        })->middleware('permission:users.manage');
        Route::get('/lists/{id}/subscribers', [CustomerImportFileAdminController::class, 'subscribers'])
            ->middleware('permission:users.manage')
            ->name('customers.files.subscribers');
        Route::get('/lists/{id}/subscribers/{subscriberId}/edit', [CustomerImportFileAdminController::class, 'editSubscriber'])
            ->middleware('permission:users.manage')
            ->name('customers.files.subscribers.edit');
        Route::get('/lists/{id}/subscribers-export', [CustomerImportFileAdminController::class, 'subscribersExport'])
            ->middleware('permission:users.manage')
            ->name('customers.files.subscribersExport');
        Route::put('/lists/{id}/subscribers/{subscriberId}', [CustomerImportFileAdminController::class, 'updateSubscriber'])
            ->middleware('permission:users.manage')
            ->name('customers.files.subscribers.update');
        Route::post('/lists/{id}/subscribers/bulk-action', [CustomerImportFileAdminController::class, 'bulkSubscribersAction'])
            ->middleware('permission:users.manage')
            ->name('customers.files.subscribers.bulkAction');
        Route::get('/lists/{id}/overview', [CustomerImportFileAdminController::class, 'show'])
            ->middleware('permission:users.manage')
            ->name('customers.files.show');
        Route::post('/lists/{id}/cancel', [CustomerImportFileAdminController::class, 'cancel'])
            ->middleware('permission:users.manage')
            ->name('customers.files.cancel');
        Route::post('/lists/{id}/reprocess', [CustomerImportFileAdminController::class, 'reprocess'])
            ->middleware('permission:users.manage')
            ->name('customers.files.reprocess');
        Route::put('/lists/{id}', [CustomerImportFileAdminController::class, 'update'])
            ->middleware('permission:users.manage')
            ->name('customers.files.update');
        Route::post('/lists/{id}/archive', [CustomerImportFileAdminController::class, 'archive'])
            ->middleware('permission:users.manage')
            ->name('customers.files.archive');
        Route::delete('/lists/{id}', [CustomerImportFileAdminController::class, 'destroy'])
            ->middleware('permission:users.manage')
            ->name('customers.files.destroy');
        Route::get('/lists/{id}/history', [CustomerImportFileAdminController::class, 'history'])
            ->middleware('permission:users.manage')
            ->name('customers.files.history');
        Route::get('/lists/{id}/history-export', [CustomerImportFileAdminController::class, 'historyExport'])
            ->middleware('permission:users.manage')
            ->name('customers.files.historyExport');
        Route::post('/lists/{id}/retry-failed-rows', [CustomerImportFileAdminController::class, 'retryFailedRows'])
            ->middleware('permission:users.manage')
            ->name('customers.files.retryFailedRows');
        Route::get('/lists/{id}/error-report', [CustomerImportFileAdminController::class, 'errorReport'])
            ->middleware('permission:users.manage')
            ->name('customers.files.errorReport');

        // Legacy GET fallbacks -> canonical /admin/lists/*
        Route::get('/files/{id}', function (\Illuminate\Http\Request $request, string $id) {
            $uid = LeadSource::withoutGlobalScopes()->whereKey($id)->value('public_uid') ?: $id;
            return redirect()->route('admin.customers.files.show', array_merge(['id' => $uid], $request->query()), 301);
        })->middleware('permission:users.manage');
        Route::get('/files/{id}/subscribers', function (\Illuminate\Http\Request $request, string $id) {
            $uid = LeadSource::withoutGlobalScopes()->whereKey($id)->value('public_uid') ?: $id;
            return redirect()->route('admin.customers.files.subscribers', array_merge(['id' => $uid], $request->query()), 301);
        })->middleware('permission:users.manage');
        Route::get('/files/{id}/subscribers/{subscriberId}/edit', function (\Illuminate\Http\Request $request, string $id, string $subscriberId) {
            $uid = LeadSource::withoutGlobalScopes()->whereKey($id)->value('public_uid') ?: $id;
            return redirect()->route('admin.customers.files.subscribers.edit', array_merge(['id' => $uid, 'subscriberId' => $subscriberId], $request->query()), 301);
        })->middleware('permission:users.manage');
        Route::get('/files/{id}/subscribers-export', function (\Illuminate\Http\Request $request, string $id) {
            $uid = LeadSource::withoutGlobalScopes()->whereKey($id)->value('public_uid') ?: $id;
            return redirect()->route('admin.customers.files.subscribersExport', array_merge(['id' => $uid], $request->query()), 301);
        })->middleware('permission:users.manage');
        Route::get('/files/{id}/history', function (\Illuminate\Http\Request $request, string $id) {
            $uid = LeadSource::withoutGlobalScopes()->whereKey($id)->value('public_uid') ?: $id;
            return redirect()->route('admin.customers.files.history', array_merge(['id' => $uid], $request->query()), 301);
        })->middleware('permission:users.manage');
        Route::get('/files/{id}/history-export', function (\Illuminate\Http\Request $request, string $id) {
            $uid = LeadSource::withoutGlobalScopes()->whereKey($id)->value('public_uid') ?: $id;
            return redirect()->route('admin.customers.files.historyExport', array_merge(['id' => $uid], $request->query()), 301);
        })->middleware('permission:users.manage');
        Route::get('/files/{id}/error-report', function (\Illuminate\Http\Request $request, string $id) {
            $uid = LeadSource::withoutGlobalScopes()->whereKey($id)->value('public_uid') ?: $id;
            return redirect()->route('admin.customers.files.errorReport', array_merge(['id' => $uid], $request->query()), 301);
        })->middleware('permission:users.manage');
        Route::post('/files/{id}/cancel', [CustomerImportFileAdminController::class, 'cancel'])
            ->middleware('permission:users.manage');
        Route::post('/files/{id}/reprocess', [CustomerImportFileAdminController::class, 'reprocess'])
            ->middleware('permission:users.manage');
        Route::put('/files/{id}', [CustomerImportFileAdminController::class, 'update'])
            ->middleware('permission:users.manage');
        Route::post('/files/{id}/archive', [CustomerImportFileAdminController::class, 'archive'])
            ->middleware('permission:users.manage');
        Route::delete('/files/{id}', [CustomerImportFileAdminController::class, 'destroy'])
            ->middleware('permission:users.manage');
        Route::post('/files/{id}/retry-failed-rows', [CustomerImportFileAdminController::class, 'retryFailedRows'])
            ->middleware('permission:users.manage');
        Route::put('/files/{id}/subscribers/{subscriberId}', [CustomerImportFileAdminController::class, 'updateSubscriber'])
            ->middleware('permission:users.manage');
        Route::post('/files/{id}/subscribers/bulk-action', [CustomerImportFileAdminController::class, 'bulkSubscribersAction'])
            ->middleware('permission:users.manage');

        Route::get('/customers/files', function (\Illuminate\Http\Request $request) {
            return redirect()->route('admin.customers.files.index', $request->query(), 301);
        })->middleware('permission:users.manage');
        Route::get('/customers/files/{id}', function (\Illuminate\Http\Request $request, string $id) {
            $uid = LeadSource::withoutGlobalScopes()->whereKey($id)->value('public_uid') ?: $id;
            return redirect()->route('admin.customers.files.show', array_merge(['id' => $uid], $request->query()), 301);
        })->middleware('permission:users.manage');
        Route::get('/customers/files/{id}/subscribers', function (\Illuminate\Http\Request $request, string $id) {
            $uid = LeadSource::withoutGlobalScopes()->whereKey($id)->value('public_uid') ?: $id;
            return redirect()->route('admin.customers.files.subscribers', array_merge(['id' => $uid], $request->query()), 301);
        })->middleware('permission:users.manage');
        Route::get('/customers/files/{id}/subscribers/{subscriberId}/edit', function (\Illuminate\Http\Request $request, string $id, string $subscriberId) {
            $uid = LeadSource::withoutGlobalScopes()->whereKey($id)->value('public_uid') ?: $id;
            return redirect()->route('admin.customers.files.subscribers.edit', array_merge(['id' => $uid, 'subscriberId' => $subscriberId], $request->query()), 301);
        })->middleware('permission:users.manage');
        Route::get('/customers/files/{id}/subscribers-export', function (\Illuminate\Http\Request $request, string $id) {
            $uid = LeadSource::withoutGlobalScopes()->whereKey($id)->value('public_uid') ?: $id;
            return redirect()->route('admin.customers.files.subscribersExport', array_merge(['id' => $uid], $request->query()), 301);
        })->middleware('permission:users.manage');
        Route::get('/customers/files/{id}/history', function (\Illuminate\Http\Request $request, string $id) {
            $uid = LeadSource::withoutGlobalScopes()->whereKey($id)->value('public_uid') ?: $id;
            return redirect()->route('admin.customers.files.history', array_merge(['id' => $uid], $request->query()), 301);
        })->middleware('permission:users.manage');
        Route::get('/customers/files/{id}/history-export', function (\Illuminate\Http\Request $request, string $id) {
            $uid = LeadSource::withoutGlobalScopes()->whereKey($id)->value('public_uid') ?: $id;
            return redirect()->route('admin.customers.files.historyExport', array_merge(['id' => $uid], $request->query()), 301);
        })->middleware('permission:users.manage');
        Route::get('/customers/files/{id}/error-report', function (\Illuminate\Http\Request $request, string $id) {
            $uid = LeadSource::withoutGlobalScopes()->whereKey($id)->value('public_uid') ?: $id;
            return redirect()->route('admin.customers.files.errorReport', array_merge(['id' => $uid], $request->query()), 301);
        })->middleware('permission:users.manage');
        Route::post('/customers/files/{id}/subscribers/bulk-action', [CustomerImportFileAdminController::class, 'bulkSubscribersAction'])
            ->middleware('permission:users.manage');
        Route::get('/customers/imports', function (\Illuminate\Http\Request $request) {
            $target = url('/admin/lists');
            $query = (string) $request->getQueryString();
            return redirect()->to($query !== '' ? ($target . '?' . $query) : $target, 301);
        })->middleware('permission:users.manage')
            ->name('customers.imports.index');

        Route::post('/tenant-users', [TenantUserAdminController::class, 'store'])
            ->middleware('permission:users.manage')
            ->name('tenantUsers.store');

        Route::put('/tenant-users/{id}', [TenantUserAdminController::class, 'update'])
            ->middleware('permission:users.manage')
            ->name('tenantUsers.update');

        Route::delete('/tenant-users/{id}', [TenantUserAdminController::class, 'destroy'])
            ->middleware('permission:users.manage')
            ->name('tenantUsers.destroy');

        Route::post('/tenant-users/{id}/impersonate', [TenantUserAdminController::class, 'impersonate'])
            ->middleware('permission:users.manage')
            ->name('tenantUsers.impersonate');

        Route::post('/tenant-users/invite', [TenantUserAdminController::class, 'invite'])
            ->middleware('permission:users.manage')
            ->name('tenantUsers.invite');

        Route::get('/tenant-user-groups', [TenantUserGroupAdminController::class, 'index'])
            ->middleware('permission:users.manage')
            ->name('tenantUserGroups.index');
        Route::get('/customers/user-groups', [TenantUserGroupAdminController::class, 'index'])
            ->middleware('permission:users.manage');
        Route::get('/customers-user-groups', function (\Illuminate\Http\Request $request) {
            $target = url('/admin/customers/user-groups');
            $query = (string) $request->getQueryString();
            return redirect()->to($query !== '' ? ($target . '?' . $query) : $target, 301);
        })->middleware('permission:users.manage');

        Route::put('/tenant-user-groups/{id}/permissions', [TenantUserGroupAdminController::class, 'updatePermissions'])
            ->middleware('permission:users.manage')
            ->name('tenantUserGroups.permissions.update');

        Route::get('/audit/admin-actions', [AdminAuditAdminController::class, 'index'])
            ->middleware('permission:audit.view_sensitive')
            ->name('audit.adminActions');


        Route::get('/users/user-groups', [RoleAdminController::class, 'index'])
            ->middleware('permission:roles.manage')
            ->name('roles.index');
        Route::get('/roles', function (\Illuminate\Http\Request $request) {
            $target = url('/admin/users/user-groups');
            $query = (string) $request->getQueryString();
            return redirect()->to($query !== '' ? ($target . '?' . $query) : $target, 301);
        })->middleware('permission:roles.manage');

        Route::post('/roles/{id}/permissions', [RoleAdminController::class, 'updatePermissions'])
            ->middleware('permission:roles.manage')
            ->name('roles.permissions.update');

        Route::get('/customers/subscriptions', [PlanAdminController::class, 'index'])
            ->middleware('permission:users.manage')
            ->name('customers.subscriptions.index');

        Route::post('/customers/subscriptions/{id}', [PlanAdminController::class, 'update'])
            ->middleware('permission:users.manage')
            ->name('customers.subscriptions.update');

        Route::get('/customers/plans', function (\Illuminate\Http\Request $request) {
            $target = url('/admin/customers/subscriptions');
            $query = (string) $request->getQueryString();
            return redirect()->to($query !== '' ? ($target . '?' . $query) : $target, 301);
        })->middleware('permission:users.manage')
            ->name('customers.plans.index');

        Route::post('/customers/plans/{id}', [PlanAdminController::class, 'update'])
            ->middleware('permission:users.manage')
            ->name('customers.plans.update');

        Route::get('/plans', function (\Illuminate\Http\Request $request) {
            $target = url('/admin/customers/subscriptions');
            $query = (string) $request->getQueryString();
            return redirect()->to($query !== '' ? ($target . '?' . $query) : $target, 301);
        })->middleware('permission:users.manage')
            ->name('plans.index');

        Route::post('/plans/{id}', [PlanAdminController::class, 'update'])
            ->middleware('permission:users.manage')
            ->name('plans.update');

        Route::get('/integrations', [\App\Http\Controllers\Admin\IntegrationAdminController::class, 'index'])
            ->name('integrations.index');

        Route::post('/integrations', [\App\Http\Controllers\Admin\IntegrationAdminController::class, 'store'])
            ->name('integrations.store');

        Route::put('/integrations/{id}', [\App\Http\Controllers\Admin\IntegrationAdminController::class, 'update'])
            ->name('integrations.update');

        Route::delete('/integrations/{id}', [\App\Http\Controllers\Admin\IntegrationAdminController::class, 'destroy'])
            ->name('integrations.destroy');

        Route::post('/integrations/{id}/test', [\App\Http\Controllers\Admin\IntegrationAdminController::class, 'test'])
            ->name('integrations.test');

        Route::get('/semantic', [SemanticTaxonomyController::class, 'index'])
            ->middleware('permission:system.settings')
            ->name('semantic.index');

        Route::post('/semantic/{type}', [SemanticTaxonomyController::class, 'store'])
            ->middleware('permission:system.settings')
            ->name('semantic.store');

        Route::put('/semantic/{type}/{id}', [SemanticTaxonomyController::class, 'update'])
            ->middleware('permission:system.settings')
            ->name('semantic.update');

        Route::delete('/semantic/{type}/{id}', [SemanticTaxonomyController::class, 'destroy'])
            ->middleware('permission:system.settings')
            ->name('semantic.destroy');

        Route::post('/semantic/{type}/bulk-add', [SemanticTaxonomyController::class, 'bulkAdd'])
            ->middleware('permission:system.settings')
            ->name('semantic.bulkAdd');

        Route::post('/semantic/{type}/bulk-delete', [SemanticTaxonomyController::class, 'bulkDelete'])
            ->middleware('permission:system.settings')
            ->name('semantic.bulkDelete');


        Route::get('/reports', [BugReportAdminController::class, 'index'])
            ->middleware('permission:system.settings')
            ->name('reports.index');

        Route::prefix('monetization')
            ->name('monetization.')
            ->middleware('permission:users.manage')
            ->group(function () {
                Route::get('/dashboard', [MonetizationDashboardController::class, 'index'])->name('dashboard');

                Route::get('/gateways', [PaymentGatewayAdminController::class, 'index'])->name('gateways.index');
                Route::post('/gateways', [PaymentGatewayAdminController::class, 'store'])->name('gateways.store');
                Route::put('/gateways/{id}', [PaymentGatewayAdminController::class, 'update'])->name('gateways.update');
                Route::delete('/gateways/{id}', [PaymentGatewayAdminController::class, 'destroy'])->name('gateways.destroy');

                Route::get('/price-plans', [PricePlanAdminController::class, 'index'])->name('price-plans.index');
                Route::post('/price-plans', [PricePlanAdminController::class, 'store'])->name('price-plans.store');
                Route::put('/price-plans/{id}', [PricePlanAdminController::class, 'update'])->name('price-plans.update');
                Route::delete('/price-plans/{id}', [PricePlanAdminController::class, 'destroy'])->name('price-plans.destroy');

                Route::get('/orders', [OrderAdminController::class, 'index'])->name('orders.index');
                Route::post('/orders', [OrderAdminController::class, 'store'])->name('orders.store');
                Route::put('/orders/{id}', [OrderAdminController::class, 'update'])->name('orders.update');
                Route::delete('/orders/{id}', [OrderAdminController::class, 'destroy'])->name('orders.destroy');

                Route::get('/promo-codes', [PromoCodeAdminController::class, 'index'])->name('promo-codes.index');
                Route::post('/promo-codes', [PromoCodeAdminController::class, 'store'])->name('promo-codes.store');
                Route::put('/promo-codes/{id}', [PromoCodeAdminController::class, 'update'])->name('promo-codes.update');
                Route::delete('/promo-codes/{id}', [PromoCodeAdminController::class, 'destroy'])->name('promo-codes.destroy');

                Route::get('/currencies', [CurrencyAdminController::class, 'index'])->name('currencies.index');
                Route::post('/currencies', [CurrencyAdminController::class, 'store'])->name('currencies.store');
                Route::put('/currencies/{id}', [CurrencyAdminController::class, 'update'])->name('currencies.update');
                Route::delete('/currencies/{id}', [CurrencyAdminController::class, 'destroy'])->name('currencies.destroy');

                Route::get('/taxes', [TaxRateAdminController::class, 'index'])->name('taxes.index');
                Route::post('/taxes', [TaxRateAdminController::class, 'store'])->name('taxes.store');
                Route::put('/taxes/{id}', [TaxRateAdminController::class, 'update'])->name('taxes.update');
                Route::delete('/taxes/{id}', [TaxRateAdminController::class, 'destroy'])->name('taxes.destroy');
            });

        Route::get('/monitoring', [MonitoringAdminController::class, 'index'])
            ->middleware('permission:system.settings')
            ->name('monitoring.index');

        Route::get('/monitoring/health', [MonitoringAdminController::class, 'health'])
            ->middleware('permission:system.settings')
            ->name('monitoring.health');

        Route::get('/monitoring/performance', [MonitoringAdminController::class, 'performance'])
            ->middleware('permission:system.settings')
            ->name('monitoring.performance');

        Route::post('/monitoring/queue-restart', [MonitoringAdminController::class, 'restartQueues'])
            ->middleware('permission:system.settings')
            ->name('monitoring.queueRestart');

        Route::post('/monitoring/recover-queue', [MonitoringAdminController::class, 'recoverQueue'])
            ->middleware('permission:system.settings')
            ->name('monitoring.recoverQueue');

        Route::get('/monitoring/incidents/export', [MonitoringAdminController::class, 'exportIncidentsCsv'])
            ->middleware('permission:system.settings')
            ->name('monitoring.incidentsExport');

        Route::post('/monitoring/incidents/{id}/ack', [MonitoringAdminController::class, 'acknowledgeIncident'])
            ->middleware('permission:system.settings')
            ->name('monitoring.incidentsAck');

        Route::get('/security', [SecurityAccessAdminController::class, 'index'])
            ->middleware('permission:system.settings')
            ->name('security.index');

        Route::get('/security/health', [SecurityAccessAdminController::class, 'health'])
            ->middleware('permission:system.settings')
            ->name('security.health');

        Route::post('/security/cloudflare/ingest', [SecurityAccessAdminController::class, 'ingestCloudflare'])
            ->middleware('permission:system.settings')
            ->name('security.cloudflareIngest');

        Route::post('/security/evaluate-risk', [SecurityAccessAdminController::class, 'evaluateRisk'])
            ->middleware('permission:system.settings')
            ->name('security.evaluateRisk');

        Route::post('/security/action/block-ip', [SecurityAccessAdminController::class, 'blockIp'])
            ->middleware('permission:system.settings')
            ->name('security.blockIp');

        Route::post('/security/action/challenge-ip', [SecurityAccessAdminController::class, 'challengeIp'])
            ->middleware('permission:system.settings')
            ->name('security.challengeIp');

        Route::post('/security/action/unblock-ip', [SecurityAccessAdminController::class, 'unblockIp'])
            ->middleware('permission:system.settings')
            ->name('security.unblockIp');

        Route::get('/security/incidents/export', [SecurityAccessAdminController::class, 'exportIncidentsCsv'])
            ->middleware('permission:system.settings')
            ->name('security.incidentsExport');

        Route::post('/security/incidents/{id}/ack', [SecurityAccessAdminController::class, 'acknowledgeIncident'])
            ->middleware('permission:system.settings')
            ->name('security.incidentsAck');

        Route::get('/audit/access', [GuestAuditAdminController::class, 'index'])
            ->middleware('permission:audit.view_sensitive')
            ->name('audit.access');

        Route::get('/audit/guest', function (\Illuminate\Http\Request $request) {
            return redirect()->route('admin.audit.access', $request->query());
        })
            ->middleware('permission:audit.view_sensitive')
            ->name('audit.guest');
});

/*
|--------------------------------------------------------------------------
| INVITES (guest)
|--------------------------------------------------------------------------
*/

Route::middleware('guest:tenant')->group(function () {
    Route::get('/tenant/invite/{token}', [TenantInviteController::class, 'show'])
        ->name('tenant.invite.accept');
    Route::post('/tenant/invite/{token}', [TenantInviteController::class, 'accept'])
        ->name('tenant.invite.accept.submit');
});

Route::middleware(['auth:tenant', 'tenant.authctx'])->prefix('tenant')->name('tenant.')->group(function () {
    Route::get('/imports', [TenantModuleController::class, 'imports'])
        ->middleware('tenant.permission:imports.manage')
        ->name('imports.index');
    Route::get('/campaigns', [TenantModuleController::class, 'campaigns'])
        ->middleware('tenant.permission:campaigns.run')
        ->name('campaigns.index');
    Route::get('/inbox', [TenantModuleController::class, 'inbox'])
        ->middleware('tenant.permission:inbox.view')
        ->name('inbox.index');
    Route::get('/exports', [TenantModuleController::class, 'exports'])
        ->middleware('tenant.permission:exports.view')
        ->name('exports.index');
});


/*
|--------------------------------------------------------------------------
| VAULT (LEADS VAULT)
|--------------------------------------------------------------------------
|
| Arquitetura modular Grade
|
|   /vault/sources      → ingestão/importação
|   /vault/explore      → consulta rápida
|   /vault/source/{id}/semantic → semântica
|   /vault/automation   → jobs gerais
|
| Controllers = HTTP only
| Services = business logic
|
*/


Route::middleware(['auth','tenant','superadmin.readonly'])
    ->prefix('vault')
    ->name('vault.')
    ->group(function () {

        /* =========================================================
           ROOT
        ========================================================= */

        Route::get('/', fn () =>
            redirect()->route('vault.sources.index')
        );


        /* =========================================================
           SOURCES — Importação / Upload / Status / Cancel / Reprocess
        ========================================================= */

        Route::prefix('sources')
            ->name('sources.')
            ->controller(VaultSourcesController::class)
            ->group(function () {

                /* pages */
                Route::get('/', 'index')
                    ->middleware('permission:leads.view')
                    ->name('index');

                /* upload */
                Route::post('/', 'store')
                    ->middleware('permission:leads.import')
                    ->name('store');

                /* polling */
                Route::get('/status', 'status')
                    ->middleware('permission:leads.view')
                    ->name('status');

                Route::get('/health', 'health')
                    ->middleware('permission:leads.view')
                    ->name('health');

                /* actions */
                Route::post('/{id}/cancel', 'cancel')
                    ->middleware('permission:automation.cancel')
                    ->name('cancel');

                Route::post('/{id}/reprocess', 'reprocess')
                    ->middleware('permission:automation.reprocess')
                    ->name('reprocess');

                Route::post('/purge-all', 'purgeAll')
                    ->middleware('permission:leads.delete')
                    ->name('purgeAll');

                Route::post('/purge-selected', 'purgeSelected')
                    ->middleware('permission:leads.delete')
                    ->name('purgeSelected');
            });


        /* =========================================================
           EXPLORE — Leads Normalizados
        ========================================================= */

        Route::middleware('permission:leads.view')
            ->prefix('explore')
            ->name('explore.')
            ->controller(VaultExploreController::class)
            ->group(function () {

                Route::get('/', 'index')->name('index');

                Route::get('/source/clear', 'clearSource')->name('clearSource');
                Route::get('/source/{id}', 'selectSource')->name('selectSource');

                Route::get('/db', 'db')->name('db');

                Route::get('/semantic-options', 'semanticOptions')
                    ->name('options');

                Route::get('/semantic-autocomplete', 'semanticAutocomplete')
                    ->name('semanticAutocomplete');

                Route::get('/semantic-autocomplete-unified', 'semanticAutocompleteUnified')
                    ->name('semanticAutocompleteUnified');

                Route::get('/semantic', 'semanticLoad')->name('semanticLoad');
                Route::post('/semantic', 'semanticSave')->name('semanticSave');

                Route::get('/sources', 'sourcesList')->name('sourcesList');
                Route::post('/view-preference', 'saveViewPreference')->name('saveViewPreference');
                Route::post('/override', 'saveOverride')->name('saveOverride');
                Route::get('/overrides-summary', 'overridesSummary')->name('overridesSummary');
                Route::post('/overrides/publish', 'publishOverrides')->name('publishOverrides');
                Route::post('/overrides/discard', 'discardOverrides')->name('discardOverrides');
            });

        Route::middleware('permission:automation.run')
            ->prefix('explore')
            ->name('explore.')
            ->controller(VaultExploreMarketingController::class)
            ->group(function () {
                Route::post('/marketing/availability', 'availability')->name('marketing.availability');
                Route::post('/marketing/dispatch', 'dispatch')->name('marketing.dispatch');
            });


        /* =========================================================
           SEMANTIC — Identidade / Segmento / Niche / Origem
        ========================================================= */

        Route::middleware('permission:leads.normalize')
            ->prefix('source/{id}/semantic')
            ->name('semantic.')
            ->controller(VaultSemanticController::class)
            ->group(function () {

                Route::get('/', 'index')->name('index');

                Route::get('/show', 'show')->name('show');
                Route::get('/suggest', 'suggest')->name('suggest');
                Route::get('/autocomplete', 'autocompleteUnified')->name('autocomplete');

                Route::post('/save', 'save')->name('save');
            });


        /* =========================================================
           AUTOMATION — Jobs gerais (não específicos de source)
        ========================================================= */

        Route::middleware('permission:leads.merge')
            ->prefix('automation')
            ->name('automation.')
            ->controller(VaultAutomationController::class)
            ->group(function () {

                Route::get('/', 'index')->name('index');
                Route::get('/stats', 'stats')->name('stats');
                Route::get('/ops/health', 'health')->name('ops.health');

                Route::get('/flows', 'listFlows')->name('flows.index');
                Route::post('/flows', 'storeFlow')
                    ->middleware('permission:automation.run')
                    ->name('flows.store');
                Route::get('/flows/{id}', 'showFlow')->name('flows.show');
                Route::put('/flows/{id}', 'updateFlow')
                    ->middleware('permission:automation.run')
                    ->name('flows.update');
                Route::post('/flows/{id}/steps', 'replaceFlowSteps')
                    ->middleware('permission:automation.run')
                    ->name('flows.steps.replace');
                Route::post('/flows/{id}/run', 'runFlow')
                    ->middleware('permission:automation.run')
                    ->name('flows.run');

                Route::get('/runs', 'listRuns')->name('runs.index');
                Route::get('/runs/{id}', 'showRun')->name('runs.show');
                Route::get('/runs/{id}/events', 'runEvents')->name('runs.events');
                Route::post('/runs/{id}/cancel', 'cancelRun')
                    ->middleware('permission:automation.cancel')
                    ->name('runs.cancel');
            });

        Route::prefix('records')
            ->name('records.')
            ->controller(VaultOperationalController::class)
            ->group(function () {
                Route::get('/stats', 'stats')
                    ->middleware('permission:leads.view')
                    ->name('stats');
                Route::get('/', 'index')
                    ->middleware('permission:leads.view')
                    ->name('index');
                Route::post('/', 'store')
                    ->middleware('permission:leads.merge')
                    ->name('store');
                Route::post('/bulk-update', 'bulkUpdate')
                    ->middleware('permission:leads.merge')
                    ->name('bulkUpdate');
                Route::post('/bulk-delete', 'bulkDestroy')
                    ->middleware('permission:leads.merge')
                    ->name('bulkDestroy');
                Route::get('/bulk-tasks', 'listBulkTasks')
                    ->middleware('permission:leads.merge')
                    ->name('bulkTasks.index');
                Route::post('/bulk-tasks', 'createBulkTask')
                    ->middleware('permission:leads.merge')
                    ->name('bulkTasks.store');
                Route::get('/bulk-tasks/{id}', 'showBulkTask')
                    ->whereNumber('id')
                    ->middleware('permission:leads.merge')
                    ->name('bulkTasks.show');
                Route::post('/bulk-tasks/{id}/cancel', 'cancelBulkTask')
                    ->whereNumber('id')
                    ->middleware('permission:leads.merge')
                    ->name('bulkTasks.cancel');
                Route::get('/{id}', 'show')
                    ->whereNumber('id')
                    ->middleware('permission:leads.view')
                    ->name('show');
                Route::patch('/{id}', 'update')
                    ->whereNumber('id')
                    ->middleware('permission:leads.merge')
                    ->name('update');
                Route::delete('/{id}', 'destroy')
                    ->whereNumber('id')
                    ->middleware('permission:leads.merge')
                    ->name('destroy');
                Route::get('/{id}/interactions', 'listInteractions')
                    ->whereNumber('id')
                    ->middleware('permission:leads.view')
                    ->name('interactions.index');
                Route::post('/{id}/interactions', 'storeInteraction')
                    ->whereNumber('id')
                    ->middleware('permission:leads.merge')
                    ->name('interactions.store');
            });

        Route::prefix('operational-records')
            ->name('operationalRecords.')
            ->controller(VaultOperationalController::class)
            ->group(function () {
                Route::get('/stats', 'stats')
                    ->middleware('permission:leads.view')
                    ->name('stats');
                Route::get('/', 'index')
                    ->middleware('permission:leads.view')
                    ->name('index');
                Route::post('/', 'store')
                    ->middleware('permission:leads.merge')
                    ->name('store');
                Route::post('/bulk-update', 'bulkUpdate')
                    ->middleware('permission:leads.merge')
                    ->name('bulkUpdate');
                Route::post('/bulk-delete', 'bulkDestroy')
                    ->middleware('permission:leads.merge')
                    ->name('bulkDestroy');
                Route::get('/bulk-tasks', 'listBulkTasks')
                    ->middleware('permission:leads.merge')
                    ->name('bulkTasks.index');
                Route::post('/bulk-tasks', 'createBulkTask')
                    ->middleware('permission:leads.merge')
                    ->name('bulkTasks.store');
                Route::get('/bulk-tasks/{id}', 'showBulkTask')
                    ->whereNumber('id')
                    ->middleware('permission:leads.merge')
                    ->name('bulkTasks.show');
                Route::post('/bulk-tasks/{id}/cancel', 'cancelBulkTask')
                    ->whereNumber('id')
                    ->middleware('permission:leads.merge')
                    ->name('bulkTasks.cancel');
                Route::get('/{id}', 'show')
                    ->whereNumber('id')
                    ->middleware('permission:leads.view')
                    ->name('show');
                Route::patch('/{id}', 'update')
                    ->whereNumber('id')
                    ->middleware('permission:leads.merge')
                    ->name('update');
                Route::delete('/{id}', 'destroy')
                    ->whereNumber('id')
                    ->middleware('permission:leads.merge')
                    ->name('destroy');
                Route::get('/{id}/interactions', 'listInteractions')
                    ->whereNumber('id')
                    ->middleware('permission:leads.view')
                    ->name('interactions.index');
                Route::post('/{id}/interactions', 'storeInteraction')
                    ->whereNumber('id')
                    ->middleware('permission:leads.merge')
                    ->name('interactions.store');
            });


        /*
        ==========================================================
        FUTURO: ANALYTICS
        ==========================================================

        Route::middleware('permission:analytics.view')
            ->prefix('analytics')
            ->name('analytics.')
            ->controller(VaultAnalyticsController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/stats', 'stats')->name('stats');
            });
        */
    });

/*
|--------------------------------------------------------------------------
| WEBHOOKS (no CSRF)
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/sms-gateway', [IntegrationWebhookController::class, 'smsGateway'])
    ->name('webhooks.sms_gateway')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/webhooks/mailwizz', [IntegrationWebhookController::class, 'mailwizz'])
    ->name('webhooks.mailwizz')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

/*
|--------------------------------------------------------------------------
| AUTH (Laravel Breeze/Fortify)
|--------------------------------------------------------------------------
*/

require __DIR__.'/auth.php';
