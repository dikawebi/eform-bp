<?php

return [
    'employee_workbook_path' => env('EFORM_EMPLOYEE_WORKBOOK_PATH'),
    'leave_types' => [
        'annual_leave', 'roster_leave', 'coff', 'permission', 'sick', 'other',
    ],
    'leave_cost_categories' => [
        'land_transport', 'hotel', 'meal', 'other',
    ],
    'leave_local_eligible' => false,
    'settlement' => [
        'allowed_source_statuses' => ['advance_paid', 'settlement_required'],
        'allow_partial' => false,
    ],
    'medical' => [
        'benefit_types' => ['obat_vitamin', 'rawat_jalan', 'rawat_inap', 'lensa_kacamata', 'frame_kacamata', 'medical_check_up'],
        'required_documents' => ['receipt'],
        'allowed_documents' => ['receipt', 'prescription', 'doctor_letter'],
        'max_file_size_kb' => 5120,
    ],
];
