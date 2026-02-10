<?php

return [
    'version' => 1,
    'notes' => [
        'This is the contract for the future batch job that will apply rules to a full source.',
        'Use LeadDataQualityService::applyRules for deterministic rule evaluation.',
    ],
    'request' => [
        'source_id' => 'int (required)',
        'column_key' => 'string (required)',
        'rules' => ['trim','upper','lower','title','remove_accents','digits_only','date_iso','null_if_empty'],
        'mode' => 'preview|apply',
        'scope' => 'all|filtered',
        'filters' => [
            'segment_id' => 'int|null',
            'niche_id' => 'int|null',
            'origin_id' => 'int|null',
            'states' => 'string[]',
            'cities' => 'string[]',
            'min_score' => 'int',
        ],
    ],
    'response_preview' => [
        'items' => [
            ['id' => 'int', 'before' => 'mixed', 'after' => 'mixed'],
        ],
    ],
    'response_apply' => [
        'job_id' => 'string',
        'estimated' => 'int',
        'source_kind' => 'edited',
        'parent_source_id' => 'int',
        'output_file_path' => 'string',
    ],
];
