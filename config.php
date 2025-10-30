<?php
// ==========================
// CONFIGURAÇÕES DO SISTEMA
// ==========================

// Banco de Dados
if (!defined('DB_HOST'))    define('DB_HOST', 'localhost');
if (!defined('DB_NAME'))    define('DB_NAME', 'u100060033_farmav6');
if (!defined('DB_USER'))    define('DB_USER', 'u100060033_farmav6');
if (!defined('DB_PASS'))    define('DB_PASS', 'Arka2025!@#');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Idioma padrão
if (!defined('DEFAULT_LANG')) define('DEFAULT_LANG', 'pt_BR');

// Admin
if (!defined('ADMIN_EMAIL'))     define('ADMIN_EMAIL', 'admin@farmafacil.com');
if (!defined('ADMIN_PASS_HASH')) {
    // Senha: arkaleads2025!
    define('ADMIN_PASS_HASH', '$2y$10$wxiT4tLWJSbAfv/BkBnoGOz96j7wrH3QthsgSCz08o.RVG3AJsASS');
}

// ==========================
// CONFIGURAÇÕES DO SITE
// ==========================

return [
    'store' => [
        'name'          => 'Farma Fácil',
        'currency'      => 'USD',
        'support_email' => 'contato@farmafacil.com',
        'phone'         => '(82) 99999-9999',
        'address'       => 'Maceió, Alagoas, Brasil',
    ],
    'payments' => [
        'zelle' => [
            'enabled'                => true,
            'recipient_name'         => 'MHBS MULTISERVICES',
            'recipient_email'        => '8568794719',
            'require_receipt_upload' => true,
        ],
        'venmo' => [
            'enabled' => true,
            'handle'  => 'https://venmo.com/code?user_id=4077225473213622325',
        ],
        'pix' => [
            'enabled'       => true,
            'pix_key'       => '35.816.920/0001-67',
            'merchant_name' => 'MH Baltazar de Souza',
            'merchant_city' => 'Maceio',
        ],
        'paypal' => [
            'enabled'    => true,
            'business'   => '@MarceloSouza972',
            'currency'      => 'USD',
            // URLs ajustadas para /
            'return_url' => 'https://victorfarmafacil.com/index.php?route=checkout_complete',
            'cancel_url' => 'https://victorfarmafacil.com/index.php?route=checkout_cancel',
        ],
    ],
    'paths' => [
        'zelle_receipts' => __DIR__ . '/storage/zelle_receipts',
        'products'       => __DIR__ . '/storage/products',
        'logo'           => __DIR__ . '/storage/logo',
    ],
    'notifications' => [
        'sound_enabled'       => true,
        'email_notifications' => true,
        'check_interval'      => 5000, // 5 segundos
    ],
];
