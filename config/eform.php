<?php

return [
    // Referensi perusahaan untuk label/dokumen. Tidak dipersist agar seeding
    // foundation tidak menambah master organisasi di luar scope MVP.
    'company' => [
        'name' => 'PT Borneo Prima',
        'industry' => 'pertambangan batubara',
        'city' => 'Puruk Cahu',
        'regency' => 'Murung Raya',
        'province' => 'Kalimantan Tengah',
    ],
    'employee_workbook_path' => env('EFORM_EMPLOYEE_WORKBOOK_PATH'),
    'leave_types' => [
        'annual_leave', 'roster_leave', 'coff', 'permission', 'sick', 'other',
    ],
    'leave_cost_categories' => [
        'land_transport', 'hotel', 'meal', 'other',
    ],
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
    'it_request' => [
        'devices' => ['laptop', 'desktop', 'workstation_cad', 'upgrade'],
        'priorities' => ['normal', 'high', 'critical'],
        'software_standard' => ['MS Office', 'AntiVirus', 'WinZip', 'PDF Reader', 'AnyDesk'],
        'replacement_reasons' => ['not_suitable', 'damaged', 'other'],
        'accessories' => [
            'monitor_20', 'external_hdd', 'digital_camera', 'printer_bw_laser',
            'printer_colour_laser', 'printer_a4_inkjet', 'printer_plotter', 'gps_unit',
            'rig_mobile_radio', 'handheld_radio', 'ups_stabilizer', 'wireless_keyboard_mouse',
            'notebook_battery', 'notebook_power_adapter', 'other',
        ],
        'required_documents' => [],
        'allowed_documents' => ['justification', 'quotation', 'supporting_document'],
        'max_file_size_kb' => 5120,
    ],
    'erp_request' => [
        'action_types' => ['new_account', 'modify_role', 'reset_auth'],
        'modules' => [
            'finance_gl', 'accounts_payable', 'accounts_receivable',
            'supply_chain_procurement', 'inventory_warehouse', 'fixed_assets',
            'project_management', 'budgeting',
        ],
        'required_documents' => [],
        'allowed_documents' => ['justification', 'supporting_document'],
        'max_file_size_kb' => 5120,
    ],
];
