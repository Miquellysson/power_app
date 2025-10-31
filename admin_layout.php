<?php
// admin_layout.php — layout unificado (claro/escuro), sem dependências além de Tailwind CDN + nossos assets
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!function_exists('sanitize_html')) { function sanitize_html($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('setting_get')) { function setting_get($k,$d=null){ return $d; } }
if (!function_exists('cfg')) { function cfg(){ return []; } }

function admin_header($title='Admin'){
  $store = setting_get('store_name', (cfg()['store']['name'] ?? 'Farma Fácil'));
  echo '<!doctype html><html lang="pt-br"><head><meta charset="utf-8">';
  echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
  echo '<script src="https://cdn.tailwindcss.com"></script>';
  echo '<script>tailwind.config={theme:{extend:{colors:{brand:{DEFAULT:"#2060C8"}}}}};</script>';
  echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">';
  $css = function_exists('asset_url') ? asset_url('assets/admin.css') : 'assets/admin.css';
  echo '<link rel="stylesheet" href="'.$css.'">';
  echo '<title>'.sanitize_html($title).' — Admin</title>';
  echo '<meta name="theme-color" content="#2060C8">';
  echo '</head><body>';
  // Topbar
  echo '<header class="admin-top"><div class="wrap">';
  echo '<div class="brand"><div class="logo"><i class="fa-solid fa-capsules"></i></div><div><div class="name">'.sanitize_html($store).'</div><div class="sub">Painel Administrativo</div></div></div>';
  echo '<div class="actions">';
  echo '<a class="btn btn-ghost" href="admin.php?route=settings"><i class="fa-solid fa-gear"></i><span>Config</span></a>';
  echo '<a class="btn btn-ghost" href="index.php" target="_blank"><i class="fa-solid fa-store"></i><span>Loja</span></a>';
  echo '<a class="btn btn-ghost" href="admin.php?route=logout"><i class="fa-solid fa-right-from-bracket"></i><span>Sair</span></a>';
  echo '</div></div></header>';
  // Layout: sidebar + main
  echo '<div class="admin-grid"><aside class="admin-side"><nav>';
  $cur = basename($_SERVER['SCRIPT_NAME']);
  echo '<a class="'.($cur==='dashboard.php'?'active':'').'" href="dashboard.php"><i class="fa-solid fa-gauge-high"></i>Dashboard</a>';
  echo '<a class="'.($cur==='orders.php'?'active':'').'" href="orders.php"><i class="fa-solid fa-receipt"></i>Pedidos</a>';
  echo '<a class="'.($cur==='products.php'?'active':'').'" href="products.php"><i class="fa-solid fa-pills"></i>Produtos</a>';
  echo '<a class="'.($cur==='categories.php'?'active':'').'" href="categories.php"><i class="fa-solid fa-tags"></i>Categorias</a>';
  echo '<a class="'.($cur==='customers.php'?'active':'').'" href="customers.php"><i class="fa-solid fa-users"></i>Clientes</a>';
  echo '<a class="'.($cur==='users.php'?'active':'').'" href="users.php"><i class="fa-solid fa-user-shield"></i>Usuários</a>';
  echo '<a class="'.($cur==='settings.php'?'active':'').'" href="settings.php"><i class="fa-solid fa-sliders"></i>Configurações</a>';
  echo '</nav></aside><main class="admin-main">';
}

function admin_footer(){
  echo '</main></div>';
  $js = function_exists('asset_url') ? asset_url('assets/admin.js') : 'assets/admin.js';
  echo '<script src="'.$js.'"></script>';
  echo '</body></html>';
}
