<?php

return [
    'allowance_percent' => 0.3, // 0.3% of Delivered@20°C
    'statement_prefix' => 'STMT-',
    'invoice_prefix' => 'INV-',
    'default_timezone' => 'Africa/Lubumbashi',
    // Temperature correction mode: 'simple' (approximation) or 'table' (future hook)
    'vcf_mode' => 'simple',
];
