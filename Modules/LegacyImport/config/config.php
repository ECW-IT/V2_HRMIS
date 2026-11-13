<?php

return [
    'name' => 'LegacyImport',
    'company_map' => base_path('docs/mapping/company_map.csv'),
    'org_unit_map' => base_path('docs/mapping/org_unit_map.csv'),
    'job_map' => base_path('docs/mapping/job_map.csv'),
    'grade_assignment' => base_path('docs/mapping/grade_assignment.csv'),
    'device_groups' => base_path('docs/mapping/device_groups.csv'),
    'document_type_map' => base_path('docs/mapping/document_type_map.csv'),
    'org_units' => base_path('docs/mapping/org_units.csv'),
    'jobs' => base_path('docs/mapping/jobs.csv'),
    'attachment_path' => storage_path('app/private/legacy_attachments'),
];
