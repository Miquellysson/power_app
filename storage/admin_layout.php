<?php
// admin_layout.php — layout unificado (claro/escuro), sem dependências além de Tailwind CDN + nossos assets
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!function_exists('sanitize_html')) { function sanitize_html($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('setting_get')) { function setting_get($k,$d=null){ return $d; } }
if (!function_exists('cfg')) { function cfg(){ return []; } }

function admin_header($title='Admin'){
  $store = setting_get('store_name', cfg()['store']['name'] ?? 'Farma Fácil');
  echo '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
  echo '<script src="https://cdn.tailwindcss.com"></script>';
  echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">';
  if (function_exists('asset_url')) {
    echo '<link rel="stylesheet" href="'.asset_url('assets/admin.css').'">';
  } else {
    echo '<link rel="stylesheet" href="assets/admin.css">';
  }
  echo '<title>'.sanitize_html($title).' — Admin</title>';
  echo '<meta name="theme-color" content="#B91C1C">';
  echo '</head><body>';
  echo '<header class="admin-top"><div class="wrap">';
  echo '<div class="brand"><div class="logo"><i class="fa-solid fa-capsules"></i></div><div><div class="name">'.sanitize_html($store).'</div><div class="sub">Painel</div></div></div>';
  echo '<div class="actions"><a class="btn ghost" href="admin.php?route=settings"><i class="fa-solid fa-gear"></i><span>Config</span></a>';
  echo '<a class="btn ghost" href="index.php" target="_blank"><i class="fa-solid fa-store"></i><span>Loja</span></a>';
  echo '<a class="btn ghost" href="admin.php?route=logout"><i class="fa-solid fa-right-from-bracket"></i><span>Sair</span></a></div>';
  echo '</div></header>';
  echo '<div class="admin-grid"><aside class="admin-side"><nav>';
  $cur = basename($_SERVER['SCRIPT_NAME']);
  echo '<a class="'.($cur==='dashboard.php'?'active':'').'" href="dashboard.php"><i class="fa-solid fa-gauge-high"></i>Dashboard</a>';
  echo '<a class="'.($cur==='orders.php'?'active':'').'" href="orders.php"><i class="fa-solid fa-receipt"></i>Pedidos</a>';
  echo '<a class="'.($cur==='products.php'?'active':'').'" href="products.php"><i class="fa-solid fa-pills"></i>Produtos</a>';
  echo '<a class="'.($cur==='categories.php'?'active':'').'" href="categories.php"><i class="fa-solid fa-tags"></i>Categorias</a>';
  echo '<a class="'.($cur==='customers.php'?'active':'').'" href="customers.php"><i class="fa-solid fa-users"></i>Clientes</a>';
  echo '<a class="'.($cur==='users.php'?'active':'').'" href="users.php"><i class="fa-solid fa-user-shield"></i>Usuários</a>';
  echo '</nav></aside><main class="admin-main">';
}

function admin_footer(){
  echo '</main></div>';
  if (function_exists('asset_url')) {
    echo '<script src="'.asset_url('assets/admin.js').'"></script>';
  } else {
    echo '<script src="assets/admin.js"></script>';
  }
  echo '</body></html>';
}
