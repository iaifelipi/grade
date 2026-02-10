<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProfileController;

use App\Http\Controllers\LeadsVault\VaultSourcesController;
use App\Http\Controllers\LeadsVault\VaultExploreController;
use App\Http\Controllers\LeadsVault\VaultSemanticController;
use App\Http\Controllers\LeadsVault\VaultAutomationController;
use App\Http\Controllers\LeadsVault\VaultOperationalController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Admin\RoleAdminController;
use App\Http\Controllers\Admin\PlanAdminController;
use App\Http\Controllers\Admin\SemanticTaxonomyController;
use App\Http\Controllers\Admin\LeadColumnAdminController;
use App\Http\Controllers\Admin\LeadDataQualityController;
use App\Http\Controllers\Admin\BugReportAdminController;
use App\Http\Controllers\Admin\GuestAuditAdminController;
use App\Http\Controllers\Admin\MonitoringAdminController;
use App\Http\Controllers\Support\BugReportController;


/*
|--------------------------------------------------------------------------
| HOME
|--------------------------------------------------------------------------
*/

Route::get('/', [VaultExploreController::class, 'index'])->name('home');

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

        Route::get('/users', [UserAdminController::class, 'index'])
            ->middleware('permission:users.manage')
            ->name('users.index');

        Route::post('/users/{id}/roles', [UserAdminController::class, 'updateRoles'])
            ->middleware('permission:roles.manage')
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


        Route::get('/roles', [RoleAdminController::class, 'index'])
            ->middleware('permission:roles.manage')
            ->name('roles.index');

        Route::post('/roles/{id}/permissions', [RoleAdminController::class, 'updatePermissions'])
            ->middleware('permission:roles.manage')
            ->name('roles.permissions.update');

        Route::get('/plans', [PlanAdminController::class, 'index'])
            ->middleware('permission:users.manage')
            ->name('plans.index');

        Route::post('/plans/{id}', [PlanAdminController::class, 'update'])
            ->middleware('permission:users.manage')
            ->name('plans.update');

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

        Route::get('/monitoring', [MonitoringAdminController::class, 'index'])
            ->middleware('permission:system.settings')
            ->name('monitoring.index');

        Route::get('/monitoring/health', [MonitoringAdminController::class, 'health'])
            ->middleware('permission:system.settings')
            ->name('monitoring.health');

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
| AUTH (Laravel Breeze/Fortify)
|--------------------------------------------------------------------------
*/

require __DIR__.'/auth.php';
