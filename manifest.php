<?php
require __DIR__.'/bootstrap.php';
require __DIR__.'/config.php';
require __DIR__.'/lib/db.php';
require __DIR__.'/lib/utils.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=600');

$cfg = cfg();
$name = setting_get('pwa_name', $cfg['store']['name'] ?? 'Farma Fácil');
$short = setting_get('pwa_short_name', $name);
$themeColor = setting_get('theme_color', '#2060C8');
$backgroundColor = setting_get('pwa_background_color', $themeColor);
$description = setting_get('store_meta_title', $name.' | Loja');

$iconsInfo = get_pwa_icon_paths();
$icons = [];
foreach ([192, 512] as $size) {
    if (!isset($iconsInfo[$size])) {
        continue;
    }
    $rel = '/' . ltrim($iconsInfo[$size]['relative'], '/');
    $icons[] = [
        'src' => $rel,
        'sizes' => $size . 'x' . $size,
        'type' => 'image/png',
        'purpose' => 'any maskable'
    ];
}
if (!$icons) {
    $icons = [
        [
            'src' => '/assets/icons/farma-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => '/assets/icons/farma-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ]
    ];
}

$manifest = [
    'name' => $name,
    'short_name' => $short,
    'start_url' => './?source=pwa',
    'scope' => './',
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => $backgroundColor,
    'theme_color' => $themeColor,
    'description' => $description,
    'icons' => $icons
];

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
