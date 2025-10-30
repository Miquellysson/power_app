<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require __DIR__.'/bootstrap.php';
require __DIR__.'/config.php';
require __DIR__.'/lib/db.php';
require __DIR__.'/lib/utils.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ===== Helpers seguros (guards) ===== */
if (!function_exists('sanitize_html')) { function sanitize_html($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('cfg')) { function cfg(){ return []; } }
if (!function_exists('setting_get')) { function setting_get($k,$d=null){ return $d; } }
if (!function_exists('setting_set')) { function setting_set($k,$v){ return true; } }
if (!function_exists('validate_email')) { function validate_email($e){ return filter_var($e, FILTER_VALIDATE_EMAIL); } }
if (!function_exists('csrf_token')) { function csrf_token(){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; } }
if (!function_exists('csrf_check')) { function csrf_check($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$t); } }

function find_logo_path(){
  $opt = setting_get('store_logo_url');
  if ($opt && file_exists(__DIR__.'/'.$opt)) return $opt;
  foreach (['storage/logo/logo.png','storage/logo/logo.jpg','storage/logo/logo.jpeg','storage/logo/logo.webp','assets/logo.png'] as $c) {
    if (file_exists(__DIR__.'/'.$c)) return $c;
  }
  return null;
}
function is_admin(){ return !empty($_SESSION['admin_id']); }
function require_admin(){ if (!is_admin()) { header('Location: admin.php?route=login'); exit; } }

/* ===== Router ===== */
$route = $_GET['route'] ?? (is_admin() ? 'dashboard' : 'login');
$allowed = ['login','logout','dashboard','settings','api_ping'];
if (!in_array($route, $allowed, true)) $route = is_admin() ? 'dashboard' : 'login';

/* ===== Layout ===== */
function admin_header($title='Admin - FarmaFixed', $withLayout=true){
  $logo = find_logo_path();
  $cfg  = function_exists('cfg') ? cfg() : [];
  $storeName = setting_get('store_name', $cfg['store']['name'] ?? 'Farma Fácil');
  $currentScript = basename($_SERVER['SCRIPT_NAME']);
  $route = $_GET['route'] ?? '';

  // Flag para o footer
  $GLOBALS['_ADMIN_WITH_LAYOUT'] = $withLayout;

  echo '<!doctype html><html lang="pt-br"><head><meta charset="utf-8">';
  echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
  echo '<link rel="manifest" href="/manifest-admin.webmanifest">';
  echo '<meta name="theme-color" content="#B91C1C">';
  echo '<script src="https://cdn.tailwindcss.com"></script>';
  echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">';
  echo '<title>'.sanitize_html($title).'</title>';
  echo '<style>
    :root{--bg:#ffffff;--fg:#111827;--muted:#6b7280;--line:#e5e7eb;--brand:#DC2626;--brand-700:#B91C1C;--amber:#F59E0B}
    html[data-theme=dark]{--bg:#0b0b0c;--fg:#E5E7EB;--muted:#A1A1AA;--line:#232428}
    body{background:var(--bg);color:var(--fg)}
    .topbar{background:rgba(255,255,255,.9);backdrop-filter:saturate(180%) blur(8px)}
    html[data-theme=dark] .topbar{background:rgba(17,18,20,.8)}
    .brand-chip{display:flex;align-items:center;gap:.6rem}
    .brand-chip .logo{width:40px;height:40px;border-radius:.75rem;display:grid;place-items:center;background:linear-gradient(135deg,var(--brand),var(--brand-700));color:#fff}
    .layout{display:grid;grid-template-columns:260px 1fr;gap:1rem}
    @media (max-width:1024px){.layout{grid-template-columns:1fr} .sidebar{position:sticky;top:64px}}
    .card{background:#fff;border:1px solid var(--line);border-radius:.9rem}
    html[data-theme=dark] .card{background:#111214}
    .btn{display:inline-flex;align-items:center;gap:.5rem;font-weight:600;border-radius:.8rem}
    .btn-primary{background:var(--brand);color:#fff;padding:.6rem 1rem}
    .btn-ghost{border:1px solid var(--line);padding:.5rem .9rem}
    .nav a{display:flex;align-items:center;gap:.6rem;padding:.6rem .8rem;border-radius:.6rem}
    .nav a:hover{background:rgba(220,38,38,.06)}
    .nav a.active{background:linear-gradient(180deg,rgba(220,38,38,.12),rgba(245,158,11,.12));border:1px solid rgba(220,38,38,.25)}
    .link-muted{color:var(--muted)}
    /* HERO */
    .hero{border-radius:1rem; overflow:hidden}
    .hero-bg{background:linear-gradient(135deg, rgba(220,38,38,1), rgba(245,158,11,1));}
    .hero .glass{background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.25)}
    html[data-theme=dark] .hero .glass{background:rgba(0,0,0,.2); border-color:rgba(255,255,255,.15)}
  </style>';
  echo '<script>
  (function(){
    var key="ff-theme";
    function apply(t){document.documentElement.setAttribute("data-theme",t);}
    apply(localStorage.getItem(key)||"light");
    document.addEventListener("click",e=>{var b=e.target.closest("[data-action=toggle-theme]"); if(!b) return; var t=(localStorage.getItem(key)||"light")==="light"?"dark":"light"; localStorage.setItem(key,t); apply(t);});
    if("serviceWorker" in navigator){ window.addEventListener("load",()=>navigator.serviceWorker.register("sw.js").catch(()=>{})); }
    var deferred=null, btn=null;
    window.addEventListener("beforeinstallprompt",e=>{e.preventDefault();deferred=e;btn=document.querySelector("[data-action=install-app]"); btn&&btn.classList.remove("hidden");});
    document.addEventListener("click",async e=>{var b=e.target.closest("[data-action=install-app]"); if(!b||!deferred) return; deferred.prompt(); try{await deferred.userChoice;}catch(_){ } deferred=null; b.classList.add("hidden");});
  })();
  </script>';
  echo '</head><body>';

  // Topbar
  echo '<div class="topbar sticky top-0 z-40 border-b border-[var(--line)]">';
  echo '  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between gap-3">';
  echo '    <div class="brand-chip">';
  echo '      <div class="logo"><i class="fa-solid fa-capsules"></i></div>';
  echo '      <div><div class="font-bold leading-tight">'.sanitize_html($storeName).'</div><div class="text-xs link-muted">Painel Administrativo</div></div>';
  echo '    </div>';
  echo '    <div class="flex items-center gap-2">';
  echo '      <button class="btn btn-ghost hidden" data-action="install-app" title="Adicionar à tela inicial"><i class="fa-solid fa-mobile-screen-button"></i><span class="hidden sm:inline">Instalar</span></button>';
  echo '      <button class="btn btn-ghost" data-action="toggle-theme" title="Alternar tema"><i class="fa-solid fa-circle-half-stroke"></i></button>';
  echo '      <a class="btn btn-ghost" href="index.php" target="_blank"><i class="fa-solid fa-up-right-from-square"></i><span class="hidden sm:inline">Loja</span></a>';
  echo '      <a class="btn btn-ghost" href="admin.php?route=logout"><i class="fa-solid fa-right-from-bracket"></i><span class="hidden sm:inline">Sair</span></a>';
  echo '    </div>';
  echo '  </div>';
  echo '</div>';

  // Abre layout completo (sidebar+main) ou só container simples
  if ($withLayout){
    echo '<div class="max-w-7xl mx-auto p-4 layout">';
    echo '  <aside class="sidebar"><nav class="card p-3 nav">';
    $active = function($targets) use($currentScript,$route){
      foreach ((array)$targets as $t) {
        if ($t==='settings' && $route==='settings') return 'active';
        if (strcasecmp($currentScript,$t)===0) return 'active';
      }
      return '';
    };
    echo '    <a class="'.$active(['dashboard.php']).'" href="dashboard.php"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>';
    echo '    <a class="'.$active(['orders.php']).'" href="orders.php"><i class="fa-solid fa-receipt"></i><span>Pedidos</span></a>';
    echo '    <a class="'.$active(['products.php']).'" href="products.php"><i class="fa-solid fa-pills"></i><span>Produtos</span></a>';
    echo '    <a class="'.$active(['categories.php']).'" href="categories.php"><i class="fa-solid fa-tags"></i><span>Categorias</span></a>';
    echo '    <a class="'.$active(['customers.php']).'" href="customers.php"><i class="fa-solid fa-users"></i><span>Clientes</span></a>';
    echo '    <a class="'.$active(['users.php']).'" href="users.php"><i class="fa-solid fa-user-shield"></i><span>Usuários</span></a>';
    echo '    <a class="'.$active(['settings']).'" href="admin.php?route=settings"><i class="fa-solid fa-gear"></i><span>Configurações</span></a>';
    echo '  </nav></aside>';
    echo '  <main>';
  } else {
    echo '<div class="max-w-7xl mx-auto p-4">';
  }
}

/* HERO reutilizável no admin (estilo do index) */
function admin_hero($title, $subtitle='Gerencie sua loja com rapidez', $showQuickActions=true) {
  echo '<section class="hero mb-6">';
  echo '  <div class="hero-bg p-1">';
  echo '    <div class="rounded-2xl bg-white/10 text-white px-5 py-6">';
  echo '      <div class="flex items-start justify-between gap-4 flex-wrap">';
  echo '        <div>';
  echo '          <h1 class="text-2xl md:text-3xl font-bold">'.sanitize_html($title).'</h1>';
  echo '          <p class="text-white/90 mt-1">'.sanitize_html($subtitle).'</p>';
  echo '        </div>';
  echo '        <div class="flex items-center gap-2">';
  echo '          <a href="orders.php" class="glass px-4 py-2 rounded-xl inline-flex items-center gap-2"><i class="fa-solid fa-receipt"></i> Pedidos</a>';
  echo '          <a href="products.php" class="glass px-4 py-2 rounded-xl inline-flex items-center gap-2"><i class="fa-solid fa-pills"></i> Produtos</a>';
  echo '          <a href="admin.php?route=settings" class="glass px-4 py-2 rounded-xl inline-flex items-center gap-2"><i class="fa-solid fa-gear"></i> Configurações</a>';
  echo '          <a href="index.php" target="_blank" class="glass px-4 py-2 rounded-xl inline-flex items-center gap-2"><i class="fa-solid fa-store"></i> Ver loja</a>';
  echo '        </div>';
  echo '      </div>';
  if ($showQuickActions) {
    echo '    <div class="mt-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-3">';
    echo '      <a href="orders.php" class="glass rounded-xl p-4 block">';
    echo '        <div class="text-sm opacity-90">Hoje</div>';
    echo '        <div class="flex items-center justify-between mt-1">';
    echo '          <div class="text-lg font-semibold">Pedidos</div>';
    echo '          <i class="fa-solid fa-arrow-right-long"></i>';
    echo '        </div>';
    echo '      </a>';
    echo '      <a href="products.php" class="glass rounded-xl p-4 block">';
    echo '        <div class="text-sm opacity-90">Catálogo</div>';
    echo '        <div class="flex items-center justify-between mt-1">';
    echo '          <div class="text-lg font-semibold">Produtos</div>';
    echo '          <i class="fa-solid fa-arrow-right-long"></i>';
    echo '        </div>';
    echo '      </a>';
    echo '      <a href="categories.php" class="glass rounded-xl p-4 block">';
    echo '        <div class="text-sm opacity-90">Organização</div>';
    echo '        <div class="flex items-center justify-between mt-1">';
    echo '          <div class="text-lg font-semibold">Categorias</div>';
    echo '          <i class="fa-solid fa-arrow-right-long"></i>';
    echo '        </div>';
    echo '      </a>';
    echo '      <a href="customers.php" class="glass rounded-xl p-4 block">';
    echo '        <div class="text-sm opacity-90">Base</div>';
    echo '        <div class="flex items-center justify-between mt-1">';
    echo '          <div class="text-lg font-semibold">Clientes</div>';
    echo '          <i class="fa-solid fa-arrow-right-long"></i>';
    echo '        </div>';
    echo '      </a>';
    echo '    </div>';
  }
  echo '    </div>';
  echo '  </div>';
  echo '</section>';
}

function admin_footer(){
  $withLayout = $GLOBALS['_ADMIN_WITH_LAYOUT'] ?? true;
  if ($withLayout){
    echo '  </main></div>';
  } else {
    echo '</div>';
  }
  echo '</body></html>';
}

/* ===== Rotas ===== */
try{

  /* ===== Login ===== */
  if ($route==='login') {
    if (is_admin()) { header('Location: dashboard.php'); exit; }

    if ($_SERVER['REQUEST_METHOD']==='POST') {
      if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
      $email = trim($_POST['email'] ?? '');
      $pass  = (string)($_POST['password'] ?? '');
      $ok=false;
      if (defined('ADMIN_EMAIL') && defined('ADMIN_PASS_HASH') && $email===ADMIN_EMAIL && password_verify($pass, ADMIN_PASS_HASH)) {
        $ok=true;
      } else {
        try {
          $st = db()->prepare("SELECT password_hash FROM users WHERE email=? LIMIT 1");
          $st->execute([$email]);
          $hash = $st->fetchColumn();
          if ($hash && password_verify($pass,$hash)) $ok=true;
        } catch (Throwable $e) {}
      }
      if ($ok){ $_SESSION['admin_id']=1; $_SESSION['admin_email']=$email; header('Location: dashboard.php'); exit; }
      $err='Credenciais inválidas';
    }

    // Cabeçalho sem layout (sem sidebar), com hero
    admin_header('Login - Admin', false);

    // HERO
    admin_hero('Bem-vindo ao Painel', 'Acesse para gerenciar pedidos, produtos e configurações', false);

    // Formulário central
    echo '<div class="grid place-items-center pb-10">';
    echo '  <form method="post" class="card p-6 w-full max-w-md">';
    echo '    <h2 class="text-xl font-semibold mb-1">Acessar painel</h2>';
    echo '    <p class="text-sm link-muted mb-4">Use suas credenciais administrativas.</p>';
    if (!empty($err)) {
      echo '  <div class="mb-3 p-3 rounded bg-red-50 text-red-700 border border-red-200 text-sm"><i class="fa-solid fa-circle-exclamation mr-2"></i>'.sanitize_html($err).'</div>';
    }
    echo '    <input type="hidden" name="csrf" value="'.csrf_token().'">';
    echo '    <label class="block text-sm mb-1">E-mail</label>';
    echo '    <input class="w-full border rounded px-3 py-2 mb-3" type="email" name="email" required>';
    echo '    <label class="block text-sm mb-1">Senha</label>';
    echo '    <input class="w-full border rounded px-3 py-2 mb-4" type="password" name="password" required>';
    echo '    <button class="btn btn-primary w-full" type="submit"><i class="fa-solid fa-right-to-bracket mr-2"></i>Entrar</button>';
    echo '    <div class="mt-3 text-center"><a class="btn btn-ghost" href="index.php" target="_blank"><i class="fa-solid fa-store mr-1"></i>Ver loja</a></div>';
    echo '  </form>';
    echo '</div>';

    admin_footer();
    exit;
  }

  /* ===== Logout ===== */
  if ($route==='logout') {
    $_SESSION=[]; if (ini_get('session.use_cookies')){
      $p=session_get_cookie_params();
      setcookie(session_name(),' ',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
    }
    session_destroy(); header('Location: admin.php?route=login'); exit;
  }

  /* ===== Dashboard ===== */
  if ($route==='dashboard') { require_admin(); header('Location: dashboard.php'); exit; }

  /* ===== Settings (com HERO no topo) ===== */
  if ($route==='settings') {
    require_admin();
    $cfg  = cfg();
    $name_current    = setting_get('store_name',    $cfg['store']['name'] ?? 'Farma Fácil');
    $email_current   = setting_get('store_email',   $cfg['store']['support_email'] ?? '');
    $phone_current   = setting_get('store_phone',   $cfg['store']['phone'] ?? '');
    $address_current = setting_get('store_address', $cfg['store']['address'] ?? '');
    $logo_current    = find_logo_path();

    if ($_SERVER['REQUEST_METHOD']==='POST') {
      if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
      $name    = sanitize_html($_POST['store_name'] ?? '');
      $email   = sanitize_html($_POST['store_email'] ?? '');
      $phone   = sanitize_html($_POST['store_phone'] ?? '');
      $address = sanitize_html($_POST['store_address'] ?? '');
      setting_set('store_name',$name);
      if (validate_email($email)) setting_set('store_email',$email);
      setting_set('store_phone',$phone);
      setting_set('store_address',$address);
      if (!empty($_FILES['store_logo']['name']) && $_FILES['store_logo']['error']===UPLOAD_ERR_OK) {
        $dir = __DIR__.'/storage/logo'; @mkdir($dir,0775,true);
        $ext = strtolower(pathinfo($_FILES['store_logo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext,['png','jpg','jpeg','webp'])) $ext='png';
        $fname = 'logo_'.time().'.'.$ext;
        if (move_uploaded_file($_FILES['store_logo']['tmp_name'], $dir.'/'.$fname)) {
          $rel='storage/logo/'.$fname; setting_set('store_logo_url',$rel); $logo_current=$rel;
        }
      }
      header('Location: admin.php?route=settings&saved=1'); exit;
    }

    admin_header('Configurações');
    admin_hero('Configurações da Loja', 'Atualize dados, logo e informações de contato');

    if (isset($_GET['saved'])) echo '<div class="mb-4 p-3 border border-green-200 bg-green-50 text-green-700 rounded">Salvo com sucesso.</div>';
    echo '<form class="card p-5 space-y-4" method="post" enctype="multipart/form-data" action="admin.php?route=settings">';
    echo '  <input type="hidden" name="csrf" value="'.csrf_token().'">';
    echo '  <div class="grid md:grid-cols-2 gap-4">';
    echo '    <div><label class="block text-sm mb-1">Nome do negócio</label><input class="w-full border rounded px-3 py-2" name="store_name" value="'.sanitize_html($name_current).'" required></div>';
    echo '    <div><label class="block text-sm mb-1">E-mail de suporte</label><input class="w-full border rounded px-3 py-2" name="store_email" type="email" value="'.sanitize_html($email_current).'"></div>';
    echo '    <div><label class="block text-sm mb-1">Telefone</label><input class="w-full border rounded px-3 py-2" name="store_phone" value="'.sanitize_html($phone_current).'"></div>';
    echo '    <div><label class="block text-sm mb-1">Endereço</label><input class="w-full border rounded px-3 py-2" name="store_address" value="'.sanitize_html($address_current).'"></div>';
    echo '  </div>';
    echo '  <div><label class="block text-sm mb-1">Logo (PNG/JPG/WEBP)</label>';
    if ($logo_current) echo '    <div class="mb-2"><img src="'.sanitize_html($logo_current).'" alt="logo atual" style="height:48px;border-radius:.5rem"></div>';
    echo '    <input type="file" name="store_logo" accept=".png,.jpg,.jpeg,.webp"></div>';
    echo '  <div class="pt-2 flex gap-2"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i><span> Salvar</span></button>';
    echo '  <a class="btn btn-ghost" target="_blank" href="index.php"><i class="fa-solid fa-store"></i><span> Loja</span></a></div>';
    echo '</form>';
    admin_footer(); exit;
  }

  /* ===== Ping ===== */
  if ($route==='api_ping') {
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>true,'ts'=>time()]); exit;
  }

  header('Location: dashboard.php'); exit;

} catch (Throwable $e) {
  admin_header('Erro');
  admin_hero('Erro no Sistema', 'Algo inesperado aconteceu', false);
  echo '<div class="card p-5 border border-red-200 bg-red-50 text-red-700">';
  echo '  <div class="font-semibold mb-2">Detalhes</div>';
  echo '  <pre class="text-sm whitespace-pre-wrap">'.sanitize_html($e->getMessage()).'</pre>';
  echo '</div>';
  admin_footer();
}
