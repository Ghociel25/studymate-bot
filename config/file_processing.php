<?php

return [
    // FILE_PROCESSING.md: "Tentukan secara eksplisit... Jangan menerima
    // semua MIME type secara default." Extension is the primary check
    // (Telegram-supplied mime_type is advisory only — not fully trusted,
    // see SECURITY.md "Input Security").
    'allowed_extensions' => [
        'txt' => 'text/plain',
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],

    'max_size_kb' => (int) env('FILE_MAX_SIZE_KB', 5120), // 5 MB default
];
