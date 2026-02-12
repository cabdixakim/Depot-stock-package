<?php

return [
    'allowance_percent' => 0.3, // 0.3% of Delivered@20°C
    'statement_prefix' => 'STMT-',
    'invoice_prefix' => 'INV-',
    'default_timezone' => 'Africa/Lubumbashi',
    // Temperature correction mode: 'simple' (approximation) or 'table' (future hook)
    'vcf_mode' => 'simple',
    'closing_variance_tolerance_pct' => 0.003, // 0.3% tolerance for closing variance
    'pool_adjust_max_days' => 5,
];
