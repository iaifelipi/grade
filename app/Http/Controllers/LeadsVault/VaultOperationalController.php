<?php

namespace App\Http\Controllers\LeadsVault;

use App\Http\Controllers\Controller;
use App\Models\OperationalBulkTask;
use App\Models\OperationalRecord;
use App\Models\RecordInteraction;
use App\Services\LeadsVault\OperationalBulkTaskService;
use App\Services\LeadsVault\OperationalRecordService;
use App\Support\LeadsVault\BulkTaskPayloadValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class VaultOperationalController extends Controller
{
    public function __construct(private readonly OperationalRecordService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:190'],
            'entity_type' => ['nullable', 'string', 'max:32'],
            'lifecycle_stage' => ['nullable', 'string', 'max:40'],
            'source_id' => ['nullable', 'integer', 'min:1'],
            'channel_optin' => ['nullable', 'in:email,sms,whatsapp'],
            'channel_type' => ['nullable', 'in:email,sms,whatsapp,phone,manual'],
            'channel_value' => ['nullable', 'string', 'max:190'],
            'channel_is_primary' => ['nullable', 'in:0,1'],
            'channel_can_contact' => ['nullable', 'in:0,1'],
            'consent_purpose' => ['nullable', 'string', 'max:40'],
            'consent_status' => ['nullable', 'in:granted,revoked,unknown,pending'],
            'consent_channel' => ['nullable', 'in:email,sms,whatsapp,phone,manual'],
            'next_action_from' => ['nullable', 'date'],
            'next_action_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'sort_by' => ['nullable', 'in:name,score,next_action_at'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = OperationalRecord::query();

        if (!empty($data['q'])) {
            $q = trim((string) $data['q']);
            $query->where(function ($sub) use ($q) {
                $sub->where('display_name', 'like', "%{$q}%")
                    ->orWhere('primary_email', 'like', "%{$q}%")
                    ->orWhere('document_number', 'like', "%{$q}%")
                    ->orWhere('primary_phone_e164', 'like', "%{$q}%")
                    ->orWhere('primary_whatsapp_e164', 'like', "%{$q}%");
            });
        }

        foreach (['entity_type', 'lifecycle_stage'] as $field) {
            if (!empty($data[$field])) {
                $query->where($field, strtolower((string) $data[$field]));
            }
        }

        if (!empty($data['source_id'])) {
            $query->where('lead_source_id', (int) $data['source_id']);
        }

        if (!empty($data['channel_optin'])) {
            if ($data['channel_optin'] === 'email') {
                $query->where('consent_email', true)->whereNotNull('primary_email');
            } elseif ($data['channel_optin'] === 'sms') {
                $query->where('consent_sms', true)->whereNotNull('primary_phone_e164');
            } else {
                $query->where('consent_whatsapp', true)->whereNotNull('primary_whatsapp_e164');
            }
        }

        if (
            !empty($data['channel_type']) ||
            !empty($data['channel_value']) ||
            array_key_exists('channel_is_primary', $data) ||
            array_key_exists('channel_can_contact', $data)
        ) {
            $query->whereHas('channels', function ($sub) use ($data): void {
                if (!empty($data['channel_type'])) {
                    $sub->where('channel_type', (string) $data['channel_type']);
                }

                if (!empty($data['channel_value'])) {
                    $value = trim((string) $data['channel_value']);
                    $sub->where(function ($q) use ($value): void {
                        $q->where('value', 'like', "%{$value}%")
                            ->orWhere('value_normalized', 'like', "%{$value}%");
                    });
                }

                if (array_key_exists('channel_is_primary', $data)) {
                    $sub->where('is_primary', (bool) ((int) $data['channel_is_primary']));
                }

                if (array_key_exists('channel_can_contact', $data)) {
                    $sub->where('can_contact', (bool) ((int) $data['channel_can_contact']));
                }
            });
        }

        if (
            !empty($data['consent_purpose']) ||
            !empty($data['consent_status']) ||
            !empty($data['consent_channel'])
        ) {
            $query->whereHas('consents', function ($sub) use ($data): void {
                if (!empty($data['consent_purpose'])) {
                    $sub->where('purpose', strtolower(trim((string) $data['consent_purpose'])));
                }
                if (!empty($data['consent_status'])) {
                    $sub->where('status', (string) $data['consent_status']);
                }
                if (!empty($data['consent_channel'])) {
                    $sub->where('channel_type', (string) $data['consent_channel']);
                }
            });
        }

        if (!empty($data['next_action_from'])) {
            $query->where('next_action_at', '>=', $data['next_action_from']);
        }
        if (!empty($data['next_action_to'])) {
            $query->where('next_action_at', '<=', $data['next_action_to']);
        }

        $perPage = (int) ($data['per_page'] ?? 30);
        $sortBy = (string) ($data['sort_by'] ?? 'next_action_at');
        $sortDir = (string) ($data['sort_dir'] ?? 'desc');
        $sortColumn = match ($sortBy) {
            'name' => 'display_name',
            'score' => 'score',
            default => 'next_action_at',
        };

        $records = $query
            ->orderBy($sortColumn, $sortDir)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn ($row) => [
                'id' => (int) $row->id,
                'lead_source_id' => (int) $row->lead_source_id,
                'legacy_lead_id' => (int) ($row->legacy_lead_id ?? 0),
                'entity_type' => $row->entity_type,
                'lifecycle_stage' => $row->lifecycle_stage,
                'name' => $row->display_name,
                'email' => $row->primary_email,
                'phone_e164' => $row->primary_phone_e164,
                'whatsapp_e164' => $row->primary_whatsapp_e164,
                'city' => $row->city,
                'uf' => $row->uf,
                'score' => (int) $row->score,
                'optin_email' => (bool) $row->consent_email,
                'optin_sms' => (bool) $row->consent_sms,
                'optin_whatsapp' => (bool) $row->consent_whatsapp,
                'consent_source' => $row->consent_source,
                'consent_at' => optional($row->consent_at)?->toISOString(),
                'last_interaction_at' => optional($row->last_interaction_at)?->toISOString(),
                'next_action_at' => optional($row->next_action_at)?->toISOString(),
                'created_at' => optional($row->created_at)?->toISOString(),
                'updated_at' => optional($row->updated_at)?->toISOString(),
            ]);

        return response()->json($records);
    }

    public function stats(): JsonResponse
    {
        $base = OperationalRecord::query();

        return response()->json([
            'total_records' => (clone $base)->count(),
            'with_email_optin' => (clone $base)->where('consent_email', true)->count(),
            'with_sms_optin' => (clone $base)->where('consent_sms', true)->count(),
            'with_whatsapp_optin' => (clone $base)->where('consent_whatsapp', true)->count(),
            'pending_next_actions' => (clone $base)
                ->whereNotNull('next_action_at')
                ->where('next_action_at', '<=', now())
                ->count(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $record = OperationalRecord::query()->findOrFail($id);

        return response()->json([
            'id' => (int) $record->id,
            'lead_source_id' => (int) $record->lead_source_id,
            'legacy_lead_id' => (int) ($record->legacy_lead_id ?? 0),
            'entity_type' => $record->entity_type,
            'lifecycle_stage' => $record->lifecycle_stage,
            'name' => $record->display_name,
            'email' => $record->primary_email,
            'phone_e164' => $record->primary_phone_e164,
            'whatsapp_e164' => $record->primary_whatsapp_e164,
            'city' => $record->city,
            'uf' => $record->uf,
            'sex' => $record->sex,
            'score' => (int) $record->score,
            'optin_email' => (bool) $record->consent_email,
            'optin_sms' => (bool) $record->consent_sms,
            'optin_whatsapp' => (bool) $record->consent_whatsapp,
            'consent_source' => $record->consent_source,
            'consent_at' => optional($record->consent_at)?->toISOString(),
            'last_interaction_at' => optional($record->last_interaction_at)?->toISOString(),
            'next_action_at' => optional($record->next_action_at)?->toISOString(),
            'extras_json' => $record->metadata_json,
            'created_at' => optional($record->created_at)?->toISOString(),
            'updated_at' => optional($record->updated_at)?->toISOString(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_id' => ['nullable', 'integer', 'min:1'],
            'entity_type' => ['nullable', 'string', 'max:32'],
            'lifecycle_stage' => ['nullable', 'string', 'max:40'],
            'name' => ['nullable', 'string', 'max:190'],
            'email' => ['nullable', 'string', 'max:190'],
            'phone_e164' => ['nullable', 'string', 'max:32'],
            'whatsapp_e164' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:120'],
            'uf' => ['nullable', 'string', 'max:4'],
            'sex' => ['nullable', 'string', 'max:8'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'optin_email' => ['nullable', 'boolean'],
            'optin_sms' => ['nullable', 'boolean'],
            'optin_whatsapp' => ['nullable', 'boolean'],
            'consent_source' => ['nullable', 'string', 'max:120'],
            'consent_at' => ['nullable', 'date'],
            'next_action_at' => ['nullable', 'date'],
            'extras_json' => ['nullable', 'array'],
        ]);

        try {
            $record = $this->service->createRecord($data, auth()->id());
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'record_id' => (int) $record->id,
        ], 201);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $record = OperationalRecord::query()->findOrFail($id);
        try {
            $updated = $this->service->updateRecord($record, $request->all());
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'record' => [
                'id' => (int) $updated->id,
                'entity_type' => $updated->entity_type,
                'lifecycle_stage' => $updated->lifecycle_stage,
                'email' => $updated->primary_email,
                'phone_e164' => $updated->primary_phone_e164,
                'whatsapp_e164' => $updated->primary_whatsapp_e164,
                'optin_email' => (bool) $updated->consent_email,
                'optin_sms' => (bool) $updated->consent_sms,
                'optin_whatsapp' => (bool) $updated->consent_whatsapp,
                'consent_source' => $updated->consent_source,
                'consent_at' => optional($updated->consent_at)?->toISOString(),
                'last_interaction_at' => optional($updated->last_interaction_at)?->toISOString(),
                'next_action_at' => optional($updated->next_action_at)?->toISOString(),
                'updated_at' => optional($updated->updated_at)?->toISOString(),
            ],
        ]);
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $record = OperationalRecord::query()->findOrFail($id);
        $reason = trim((string) $request->input('reason', ''));
        $this->service->deleteRecord($record, auth()->id(), $reason !== '' ? $reason : null);

        return response()->json([
            'ok' => true,
            'deleted' => 1,
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:190'],
        ]);

        $deleted = $this->service->bulkDelete(
            $data['ids'],
            auth()->id(),
            filled($data['reason'] ?? null) ? (string) $data['reason'] : null
        );

        return response()->json([
            'ok' => true,
            'deleted' => $deleted,
        ]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'min:1'],
            'updates' => ['required', 'array', 'min:1'],
        ]);

        try {
            $updatedCount = $this->service->bulkUpdate($data['ids'], $data['updates']);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'updated' => $updatedCount,
        ]);
    }

    public function storeInteraction(int $id, Request $request): JsonResponse
    {
        $record = OperationalRecord::query()->findOrFail($id);

        $data = $request->validate([
            'channel' => ['required', 'string', 'max:24'],
            'direction' => ['nullable', 'string', 'max:16'],
            'status' => ['nullable', 'string', 'max:32'],
            'subject' => ['nullable', 'string', 'max:190'],
            'message' => ['nullable', 'string'],
            'payload_json' => ['nullable', 'array'],
            'result_json' => ['nullable', 'array'],
            'external_ref' => ['nullable', 'string', 'max:160'],
            'occurred_at' => ['nullable', 'date'],
            'next_action_at' => ['nullable', 'date'],
        ]);

        $interaction = $this->service->logInteraction($record, $data, auth()->id());

        return response()->json([
            'ok' => true,
            'interaction_id' => (int) $interaction->id,
        ]);
    }

    public function listInteractions(int $id, Request $request): JsonResponse
    {
        $record = OperationalRecord::query()->findOrFail($id);

        $perPage = (int) $request->integer('per_page', 30);
        $perPage = max(1, min($perPage, 200));

        $items = RecordInteraction::query()
            ->where(function ($q) use ($record): void {
                $q->where('operational_record_id', $record->id);

                if ((int) ($record->legacy_lead_id ?? 0) > 0) {
                    $q->orWhere('lead_id', (int) $record->legacy_lead_id);
                }
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn ($row) => [
                'id' => (int) $row->id,
                'channel' => $row->channel,
                'direction' => $row->direction,
                'status' => $row->status,
                'subject' => $row->subject,
                'message' => $row->message,
                'external_ref' => $row->external_ref,
                'occurred_at' => optional($row->occurred_at)?->toISOString(),
                'created_at' => optional($row->created_at)?->toISOString(),
            ]);

        return response()->json($items);
    }

    public function listBulkTasks(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 20), 100));

        $tasks = OperationalBulkTask::query()
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn ($row) => [
                'id' => (int) $row->id,
                'task_uuid' => $row->task_uuid,
                'status' => $row->status,
                'scope_type' => $row->scope_type,
                'action_type' => $row->action_type,
                'total_items' => (int) $row->total_items,
                'processed_items' => (int) $row->processed_items,
                'success_items' => (int) $row->success_items,
                'failed_items' => (int) $row->failed_items,
                'created_at' => optional($row->created_at)?->toISOString(),
                'started_at' => optional($row->started_at)?->toISOString(),
                'finished_at' => optional($row->finished_at)?->toISOString(),
                'last_error' => $row->last_error,
            ]);

        return response()->json($tasks);
    }

    public function showBulkTask(int $id, Request $request): JsonResponse
    {
        $task = OperationalBulkTask::query()->findOrFail($id);
        $perPage = max(1, min((int) $request->integer('per_page', 30), 200));

        $items = $task->items()
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn ($item) => [
                'id' => (int) $item->id,
                'operational_record_id' => (int) ($item->operational_record_id ?? 0),
                'status' => $item->status,
                'error_message' => $item->error_message,
                'processed_at' => optional($item->processed_at)?->toISOString(),
            ]);

        return response()->json([
            'task' => [
                'id' => (int) $task->id,
                'task_uuid' => $task->task_uuid,
                'status' => $task->status,
                'scope_type' => $task->scope_type,
                'scope_json' => $task->scope_json,
                'action_type' => $task->action_type,
                'action_json' => $task->action_json,
                'total_items' => (int) $task->total_items,
                'processed_items' => (int) $task->processed_items,
                'success_items' => (int) $task->success_items,
                'failed_items' => (int) $task->failed_items,
                'last_error' => $task->last_error,
                'summary_json' => $task->summary_json,
                'created_at' => optional($task->created_at)?->toISOString(),
                'started_at' => optional($task->started_at)?->toISOString(),
                'finished_at' => optional($task->finished_at)?->toISOString(),
            ],
            'items' => $items,
        ]);
    }

    public function createBulkTask(
        Request $request,
        OperationalBulkTaskService $service,
        BulkTaskPayloadValidator $payloadValidator,
    ): JsonResponse
    {
        $data = $request->validate([
            'scope_type' => ['required', 'in:selected_ids,filtered'],
            'scope.ids' => ['required_if:scope_type,selected_ids', 'array'],
            'scope.ids.*' => ['integer', 'min:1'],
            'scope.filters' => ['required_if:scope_type,filtered', 'array'],
            'action_type' => ['required', 'in:update_fields,set_next_action,set_consent'],
            'action.updates' => ['required_if:action_type,update_fields', 'array', 'min:1'],
            'action.days' => ['required_if:action_type,set_next_action', 'integer', 'min:0', 'max:3650'],
            'action.channel' => ['required_if:action_type,set_consent', 'in:email,sms,whatsapp'],
            'action.status' => ['required_if:action_type,set_consent', 'in:granted,revoked'],
            'action.source' => ['nullable', 'string', 'max:120'],
        ]);

        $scopePayload = $data['scope'] ?? [];
        $actionPayload = $data['action'] ?? [];
        $payloadValidator->assertValid(
            (string) $data['scope_type'],
            $scopePayload,
            (string) $data['action_type'],
            $actionPayload
        );

        $task = $service->createTask(
            (string) $data['scope_type'],
            $scopePayload,
            (string) $data['action_type'],
            $actionPayload,
            auth()->id()
        );

        return response()->json([
            'ok' => true,
            'task_id' => (int) $task->id,
            'status' => $task->status,
            'total_items' => (int) $task->total_items,
        ], 201);
    }

    public function cancelBulkTask(int $id, OperationalBulkTaskService $service): JsonResponse
    {
        $task = OperationalBulkTask::query()->findOrFail($id);
        $service->requestCancel($task);

        return response()->json([
            'ok' => true,
            'status' => 'cancel_requested',
        ]);
    }
}
