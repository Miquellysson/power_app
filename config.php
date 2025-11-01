<?php
// ==========================
// CONFIGURAÇÕES DO SISTEMA
// ==========================

// Banco de Dados
if (!defined('DB_HOST')) {
    $env = getenv('FF_DB_HOST');
    define('DB_HOST', $env !== false ? $env : 'localhost');
}
if (!defined('DB_NAME')) {
    $env = getenv('FF_DB_NAME');
    define('DB_NAME', $env !== false ? $env : 'app_get_power');
}
if (!defined('DB_USER')) {
    $env = getenv('FF_DB_USER');
    define('DB_USER', $env !== false ? $env : 'app_user');
}
if (!defined('DB_PASS')) {
    $env = getenv('FF_DB_PASS');
    define('DB_PASS', $env !== false ? $env : '');
}
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Idioma padrão
if (!defined('DEFAULT_LANG')) define('DEFAULT_LANG', getenv('FF_DEFAULT_LANG') ?: 'pt_BR');

// Admin
if (!defined('ADMIN_EMAIL')) {
    $env = getenv('FF_ADMIN_EMAIL');
    define('ADMIN_EMAIL', $env !== false ? $env : 'admin@example.com');
}
if (!defined('ADMIN_PASS_HASH')) {
    $env = getenv('FF_ADMIN_PASS_HASH');
    define('ADMIN_PASS_HASH', $env !== false ? $env : '');
}

// ==========================
// CONFIGURAÇÕES DO SITE
// ==========================

return [
    'store' => [
        'name'          => 'Get Power',
        'currency'      => 'USD',
        'support_email' => 'support@getpower.local',
        'phone'         => '(00) 00000-0000',
        'address'       => 'Atualize este endereço nas configurações.',
        'base_url'      => getenv('FF_BASE_URL') ?: '',
    ],
    'payments' => [
        'zelle' => [
            'enabled'                => false,
            'recipient_name'         => '',
            'recipient_email'        => '',
            'require_receipt_upload' => true,
        ],
        'venmo' => [
            'enabled' => false,
            'handle'  => '',
        ],
        'pix' => [
            'enabled'       => false,
            'pix_key'       => '',
            'merchant_name' => '',
            'merchant_city' => '',
        ],
        'paypal' => [
            'enabled'    => false,
            'business'   => '',
            'currency'      => 'USD',
            'return_url' => '',
            'cancel_url' => '',
        ],
        'square' => [
            'enabled'      => false,
            'instructions' => 'Abriremos o checkout Square em uma nova aba para concluir o pagamento.',
            'open_new_tab' => true,
        ],
    ],
    'paths' => [
        'zelle_receipts' => __DIR__ . '/storage/zelle_receipts',
        'products'       => __DIR__ . '/storage/products',
        'logo'           => __DIR__ . '/storage/logo',
    ],
    'media' => [
        'proxy_whitelist' => [
            'base.rhemacriativa.com',
            'store.nestgeneralservices.company',
        ],
    ],
    'notifications' => [
        'sound_enabled'       => true,
        'email_notifications' => true,
        'check_interval'      => 5000, // 5 segundos
    ],
];
