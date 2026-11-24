<?php
return [
    'bank_bin' => env('FIELDLY_BANK_BIN', ''),
    'account_number' => env('FIELDLY_BANK_ACCOUNT', ''),
    'account_name' => env('FIELDLY_BANK_ACCOUNT_NAME', ''),
    'deposit_threshold_vnd' => (int) env('FIELDLY_DEPOSIT_THRESHOLD_VND', 500000),
    'deposit_amount_vnd' => (int) env('FIELDLY_DEPOSIT_AMOUNT_VND', 100000),
];
