<?php
// index.php — Loja FarmaFixed com UI estilo app (responsiva + PWA + categorias + carrinho/checkout)
// Versão com: tema vermelho/âmbar, cache-busting, endpoint CSRF ao vivo, CSRF em header, e fetch com credenciais.
// Requisitos: config.php, lib/db.php, lib/utils.php, (opcional) bootstrap.php para no-cache e asset_url()

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Anti-cache + sessão estável (recomendado)
if (file_exists(__DIR__.'/bootstrap.php')) require __DIR__.'/bootstrap.php';

require __DIR__ . '/config.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/utils.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/* ======================
   Idioma & Config
   ====================== */
if (isset($_GET['lang'])) set_lang($_GET['lang']);
$d   = lang();
$cfg = cfg();

/* ======================
   Router
   ====================== */
$route = $_GET['route'] ?? 'home';

/* ======================
   Endpoint CSRF ao vivo (não-cacheável)
   ====================== */
if ($route === 'csrf') {
  header('Content-Type: application/json; charset=utf-8');
  if (!headers_sent()) {
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
  }
  echo json_encode(['csrf' => csrf_token(), 'sid' => session_id()]);
  exit;
}

/* ======================
   Helpers — Header / Footer
   ====================== */
function store_logo_path() {
  $opt = setting_get('store_logo_url');
  if ($opt && file_exists(__DIR__.'/'.$opt)) return $opt;
  foreach (['storage/logo/logo.png','storage/logo/logo.jpg','storage/logo/logo.jpeg','storage/logo/logo.webp','assets/logo.png'] as $c) {
    if (file_exists(__DIR__ . '/' . $c)) return $c;
  }
  return null;
}

/* === Proxy de Imagem (apenas esta adição para contornar hotlink) === */
function proxy_img($url) {
  $url = (string)$url;
  if ($url === '') return $url;
  // Se for link http/https absoluto, passa pelo proxy local img.php
  if (preg_match('~^https?://~i', $url)) {
    return '/img.php?u=' . rawurlencode($url);
  }
  // Caso contrário (arquivo local), retorna como está
  return $url;
}

if (!function_exists('sanitize_builder_output')) {
  function sanitize_builder_output($html) {
    if ($html === '' || $html === null) {
      return '';
    }
    $clean = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', (string)$html);
    $clean = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $clean);
    $clean = preg_replace('/javascript\s*:/i', '', $clean);
    return $clean;
  }
}

function load_payment_methods(PDO $pdo, array $cfg): array {
  static $cache = null;
  if ($cache !== null) {
    return $cache;
  }

  $cache = [];
  try {
    $rows = $pdo->query("SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
      $settings = [];
      if (!empty($row['settings'])) {
        $decoded = json_decode($row['settings'], true);
        if (is_array($decoded)) {
          $settings = $decoded;
        }
      }
      if (!isset($settings['type'])) {
        $settings['type'] = $row['code'];
      }
      if (!isset($settings['account_label'])) {
        $settings['account_label'] = '';
      }
      if (!isset($settings['account_value'])) {
        $settings['account_value'] = '';
      }
      $cache[] = [
        'id' => (int)$row['id'],
        'code' => (string)$row['code'],
        'name' => (string)$row['name'],
        'description' => (string)($row['description'] ?? ''),
        'instructions' => (string)($row['instructions'] ?? ''),
        'settings' => $settings,
        'icon_path' => $row['icon_path'] ?? null,
        'require_receipt' => (int)($row['require_receipt'] ?? 0),
      ];
    }
  } catch (Throwable $e) {
    $cache = [];
  }

  if (!$cache) {
    $paymentsCfg = $cfg['payments'] ?? [];
    $defaults = [];
    if (!empty($paymentsCfg['pix']['enabled'])) {
      $defaults[] = [
        'code' => 'pix',
        'name' => 'Pix',
        'instructions' => "Use o Pix para pagar seu pedido. Valor: {valor_pedido}.\nChave: {pix_key}",
        'settings' => [
          'type' => 'pix',
          'account_label' => 'Chave Pix',
          'account_value' => $paymentsCfg['pix']['pix_key'] ?? '',
          'pix_key' => $paymentsCfg['pix']['pix_key'] ?? '',
          'merchant_name' => $paymentsCfg['pix']['merchant_name'] ?? '',
          'merchant_city' => $paymentsCfg['pix']['merchant_city'] ?? ''
        ],
        'require_receipt' => 0
      ];
    }
    if (!empty($paymentsCfg['zelle']['enabled'])) {
      $defaults[] = [
        'code' => 'zelle',
        'name' => 'Zelle',
        'instructions' => "Envie {valor_pedido} via Zelle para {account_value}.",
        'settings' => [
          'type' => 'zelle',
          'account_label' => 'Conta Zelle',
          'account_value' => $paymentsCfg['zelle']['recipient_email'] ?? '',
          'recipient_name' => $paymentsCfg['zelle']['recipient_name'] ?? ''
        ],
        'require_receipt' => (int)($paymentsCfg['zelle']['require_receipt_upload'] ?? 1)
      ];
    }
    if (!empty($paymentsCfg['venmo']['enabled'])) {
      $defaults[] = [
        'code' => 'venmo',
        'name' => 'Venmo',
        'instructions' => "Pague {valor_pedido} no Venmo. Link: {venmo_link}.",
        'settings' => [
          'type' => 'venmo',
          'account_label' => 'Link Venmo',
          'account_value' => $paymentsCfg['venmo']['handle'] ?? '',
          'venmo_link' => $paymentsCfg['venmo']['handle'] ?? ''
        ],
        'require_receipt' => 1
      ];
    }
    if (!empty($paymentsCfg['paypal']['enabled'])) {
      $defaults[] = [
        'code' => 'paypal',
        'name' => 'PayPal',
        'instructions' => "Após finalizar, você será direcionado ao PayPal com o valor {valor_pedido}.",
        'settings' => [
          'type' => 'paypal',
          'account_label' => 'Conta PayPal',
          'account_value' => $paymentsCfg['paypal']['business'] ?? '',
          'business' => $paymentsCfg['paypal']['business'] ?? '',
          'currency' => $paymentsCfg['paypal']['currency'] ?? 'USD',
          'return_url' => $paymentsCfg['paypal']['return_url'] ?? '',
          'cancel_url' => $paymentsCfg['paypal']['cancel_url'] ?? ''
        ],
        'require_receipt' => 0
      ];
    }
    if (!empty($paymentsCfg['square']['enabled'])) {
      $defaults[] = [
        'code' => 'square',
        'name' => 'Square',
        'instructions' => $paymentsCfg['square']['instructions'] ?? 'Abriremos o checkout Square em uma nova aba.',
        'settings' => [
          'type' => 'square',
          'account_label' => 'Pagamento Square',
          'account_value' => '',
          'mode' => 'square_product_link',
          'open_new_tab' => !empty($paymentsCfg['square']['open_new_tab'])
        ],
        'require_receipt' => 0
      ];
    }
    $cache = $defaults;
  }

  return $cache;
}

function payment_placeholders(array $method, float $totalValue, ?int $orderId = null, ?string $customerEmail = null): array {
  $settings = $method['settings'] ?? [];
  $placeholders = [
    '{valor_pedido}' => format_currency($totalValue, cfg()['store']['currency'] ?? 'USD'),
    '{numero_pedido}' => $orderId ? (string)$orderId : '',
    '{email_cliente}' => $customerEmail ?? '',
    '{account_label}' => $settings['account_label'] ?? '',
    '{account_value}' => $settings['account_value'] ?? '',
    '{pix_key}' => $settings['pix_key'] ?? ($settings['account_value'] ?? ''),
    '{pix_merchant_name}' => $settings['merchant_name'] ?? '',
    '{pix_merchant_city}' => $settings['merchant_city'] ?? '',
    '{venmo_link}' => $settings['venmo_link'] ?? ($settings['account_value'] ?? ''),
    '{paypal_business}' => $settings['business'] ?? '',
  ];
  return $placeholders;
}

function render_payment_instructions(string $template, array $placeholders): string {
  if ($template === '') {
    return '';
  }
  $text = strtr($template, $placeholders);
  return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
}

function app_header() {
  global $d, $cfg;

  $lang = $d['_lang'] ?? 'pt';
  $logo = store_logo_path();

  // Count carrinho
  $cart_count = 0;
  if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = array_sum($_SESSION['cart']);
  }

  echo '<!doctype html><html lang="'.htmlspecialchars($lang).'"><head>';
  echo '  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
  echo '  <title>'.htmlspecialchars($d['title'] ?? 'Farma Fácil').' | Loja</title>';
  echo '  <meta name="description" content="FarmaFixed — sua farmácia online com experiência de app: rápida, responsiva, segura.">';
  echo '  <link rel="manifest" href="/manifest.webmanifest">';
  echo '  <meta name="theme-color" content="#B91C1C">';

  // ====== iOS PWA (suporte ao Add to Home Screen) ======
  echo '  <link rel="apple-touch-icon" href="/assets/icons/farma-180.png">';
  echo '  <meta name="apple-mobile-web-app-capable" content="yes">';
  echo '  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">';
  echo '  <meta name="apple-mobile-web-app-title" content="Farma Fácil">';

  echo '  <script src="https://cdn.tailwindcss.com"></script>';
  echo '  <script>tailwind.config = { theme: { extend: { colors: { brand: { DEFAULT:"#DC2626", 50:"#FEF2F2", 100:"#FEE2E2", 200:"#FECACA", 300:"#FCA5A5", 400:"#F87171", 500:"#EF4444", 600:"#DC2626", 700:"#B91C1C", 800:"#991B1B", 900:"#7F1D1D" }, amber: { 400:"#F59E0B" }}}}};</script>';
  echo '  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">';
  echo '  <script defer src="/assets/js/a2hs.js?v=3"></script>';

  // CSS do tema com cache-busting se disponível
  if (function_exists('asset_url')) {
    echo '  <link href="'.asset_url('assets/theme.css').'" rel="stylesheet">';
  } else {
    echo '  <link href="assets/theme.css" rel="stylesheet">';
  }
  echo '  <style>
          .btn{transition:all .2s}
          .btn:active{transform:translateY(1px)}
          .badge{min-width:1.5rem; height:1.5rem}
          .card{background:var(--bg, #fff)}
          .blur-bg{backdrop-filter: blur(12px)}
          .a2hs-btn{border:1px solid rgba(185,28,28,.25)}
          .chip{border:1px solid #e5e7eb}
          .line-clamp-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
          .product-card:hover img{transform: scale(1.05)}
        </style>';
  echo '</head><body class="bg-gray-50 text-gray-800 min-h-screen">';

  // Topbar (estilo app) — sticky + blur
  echo '<header class="sticky top-0 z-40 border-b bg-white/90 blur-bg">';
  echo '  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between gap-3">';
  echo '    <a href="?route=home" class="flex items-center gap-3">';
  if ($logo) {
    echo '      <img src="'.htmlspecialchars($logo).'" class="w-10 h-10 rounded-lg object-cover" alt="logo">';
  } else {
    echo '      <div class="w-10 h-10 rounded-lg bg-brand-700 text-white grid place-items-center"><i class="fas fa-pills"></i></div>';
  }
  echo '      <div>';
  $store_name = setting_get('store_name', $cfg['store']['name'] ?? 'Farma Fácil');
  echo '        <div class="font-semibold leading-tight">'.htmlspecialchars($store_name).'</div>';
  echo '        <div class="text-xs text-gray-500">Farmácia Online</div>';
  echo '      </div>';
  echo '    </a>';

  echo '    <div class="flex items-center gap-2">';
  // Botão A2HS (Add to Home Screen)
  echo '      <button id="btnA2HS" class="a2hs-btn hidden px-3 py-2 rounded-lg text-brand-700 bg-brand-50 hover:bg-brand-100 text-sm"><i class="fa-solid fa-mobile-screen-button mr-1"></i> Adicionar</button>';

  // Troca de idioma
  echo '      <div class="relative">';
  echo '        <select onchange="changeLanguage(this.value)" class="px-3 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-brand-500 text-sm">';
  $languages = ['pt'=>'🇧🇷 PT','en'=>'🇺🇸 EN','es'=>'🇪🇸 ES'];
  foreach ($languages as $code=>$label) {
    $selected = (($d["_lang"] ?? "pt") === $code) ? "selected" : "";
    echo '          <option value="'.$code.'" '.$selected.'>'.$label.'</option>';
  }
  echo '        </select>';
  echo '      </div>';

  // Carrinho
  echo '      <a href="?route=cart" class="relative">';
  echo '        <div class="btn flex items-center gap-2 px-3 py-2 rounded-lg bg-brand-700 text-white hover:bg-brand-800">';
  echo '          <i class="fas fa-shopping-cart"></i>';
  echo '          <span class="hidden sm:inline">'.htmlspecialchars($d['cart'] ?? 'Carrinho').'</span>';
  if ($cart_count > 0) {
    echo '        <span id="cart-badge" class="badge absolute -top-2 -right-2 rounded-full bg-red-500 text-white text-xs grid place-items-center px-1">'.(int)$cart_count.'</span>';
  } else {
    echo '        <span id="cart-badge" class="badge hidden absolute -top-2 -right-2 rounded-full bg-red-500 text-white text-xs grid place-items-center px-1">0</span>';
  }
  echo '        </div>';
  echo '      </a>';
  echo '    </div>';
  echo '  </div>';
  echo '</header>';

  echo '<main>';
}

function app_footer() {
  echo '</main>';

  // Footer enxuto tipo app
  echo '<footer class="mt-12 bg-white border-t">';
  echo '  <div class="max-w-7xl mx-auto px-4 py-8 grid md:grid-cols-4 gap-8 text-sm">';
  echo '    <div>';
  echo '      <div class="font-semibold mb-2">FarmaFixed</div>';
  echo '      <p class="text-gray-500">Sua farmácia online com experiência de app.</p>';
  echo '    </div>';
  echo '    <div>';
  echo '      <div class="font-semibold mb-2">Links</div>';
  echo '      <ul class="space-y-2 text-gray-600">';
  echo '        <li><a class="hover:text-brand-700" href="?route=home">Início</a></li>';
  echo '        <li><a class="hover:text-brand-700" href="?route=cart">Carrinho</a></li>';
  echo '        <li><a class="hover:text-brand-700" href="#" onclick="installPrompt()"><i class="fa-solid fa-mobile-screen-button mr-1"></i> Adicionar ao celular</a></li>';
  echo '      </ul>';
  echo '    </div>';
  echo '    <div>';
  echo '      <div class="font-semibold mb-2">Contato</div>';
  echo '      <ul class="space-y-2 text-gray-600">';
  echo '        <li><i class="fa-solid fa-envelope mr-2"></i>'.htmlspecialchars(setting_get('store_email', $GLOBALS['cfg']['store']['support_email'] ?? 'contato@farmafacil.com')).'</li>';
  echo '        <li><i class="fa-solid fa-phone mr-2"></i>'.htmlspecialchars(setting_get('store_phone', $GLOBALS['cfg']['store']['phone'] ?? '(82) 99999-9999')).'</li>';
  echo '        <li><i class="fa-solid fa-location-dot mr-2"></i>'.htmlspecialchars(setting_get('store_address', $GLOBALS['cfg']['store']['address'] ?? 'Maceió - AL')).'</li>';
  echo '      </ul>';
  echo '    </div>';
  echo '    <div>';
  echo '      <div class="font-semibold mb-2">Idioma</div>';
  echo '      <div class="flex gap-2">';
  echo '        <button class="chip px-3 py-1 rounded" onclick="changeLanguage(\'pt\')">🇧🇷 PT</button>';
  echo '        <button class="chip px-3 py-1 rounded" onclick="changeLanguage(\'en\')">🇺🇸 EN</button>';
  echo '        <button class="chip px-3 py-1 rounded" onclick="changeLanguage(\'es\')">🇪🇸 ES</button>';
  echo '      </div>';
  echo '    </div>';
  echo '  </div>';
  echo '  <div class="text-center text-xs text-gray-500 py-4 border-t">&copy; '.date('Y').' FarmaFixed. Todos os direitos reservados.</div>';
  echo '</footer>';

  /* === Banner Add To Home Screen (A2HS) — Android + iOS === */
  echo '<div id="a2hsBanner" class="fixed bottom-4 left-1/2 -translate-x-1/2 bg-white shadow-lg rounded-xl px-4 py-3 flex items-center gap-3 border hidden z-50">';
  echo '  <span class="text-sm">📲 Instale o app para uma experiência melhor</span>';
  echo '  <button id="a2hsInstall" class="px-3 py-2 rounded-lg bg-brand-700 text-white text-sm">Instalar</button>';
  echo '  <button id="a2hsClose" class="ml-1 text-gray-500 text-lg leading-none">&times;</button>';
  echo '</div>';

  // Scripts (A2HS + helpers + carrinho AJAX com CSRF dinâmico)
  echo '<script>
    // ========= Utilidades A2HS =========
    let deferredPrompt = null;

    const isIOS = () => /iphone|ipad|ipod/i.test(navigator.userAgent);
    const isStandalone = () => window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;

    function ensureBtn() {
      const b = document.getElementById("btnA2HS");
      if (b) b.classList.remove("hidden");
      return b;
    }

    function showBanner() {
      document.getElementById("a2hsBanner")?.classList.remove("hidden");
    }
    function hideBanner() {
      document.getElementById("a2hsBanner")?.classList.add("hidden");
    }

    function showIOSInstallHelp() {
      // overlay simples com instruções para iPhone
      document.getElementById("ios-a2hs-overlay")?.remove();
      const overlay = document.createElement("div");
      overlay.id = "ios-a2hs-overlay";
      overlay.className = "fixed inset-0 bg-black/50 z-[1000] grid place-items-center p-4";
      overlay.innerHTML = `
        <div class="max-w-sm w-full bg-white rounded-2xl shadow-xl p-5">
          <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-lg bg-brand-700 text-white grid place-items-center">
              <i class="fa-solid fa-mobile-screen-button"></i>
            </div>
            <div class="flex-1">
              <div class="font-semibold text-lg mb-1">Adicionar à Tela de Início</div>
              <p class="text-sm text-gray-600">
                No iPhone, toque em <b>Compartilhar</b>
                (ícone <i class="fa-solid fa-arrow-up-from-bracket"></i>)
                e depois em <b>Adicionar à Tela de Início</b>.
              </p>
              <ol class="text-sm text-gray-600 list-decimal ml-5 mt-3 space-y-1">
                <li>Toque em <b>Compartilhar</b> na barra inferior.</li>
                <li>Role as opções e toque em <b>Adicionar à Tela de Início</b>.</li>
                <li>Confirme com <b>Adicionar</b>.</li>
              </ol>
            </div>
          </div>
          <div class="mt-4 text-right">
            <button id="ios-a2hs-close" class="px-4 py-2 rounded-lg border hover:bg-gray-50">Fechar</button>
          </div>
        </div>
      `;
      document.body.appendChild(overlay);
      document.getElementById("ios-a2hs-close")?.addEventListener("click", () => overlay.remove());
      overlay.addEventListener("click", (e) => { if (e.target === overlay) overlay.remove(); });
    }

    // Chrome/Android/desktop: evento nativo
    window.addEventListener("beforeinstallprompt", (e) => {
      e.preventDefault();
      deferredPrompt = e;
      ensureBtn();
      showBanner();
    });

    function installPrompt() {
      if (isIOS() && !isStandalone()) {
        // iOS não tem beforeinstallprompt — mostra instruções
        showIOSInstallHelp();
        hideBanner();
        return;
      }
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      deferredPrompt.userChoice.finally(() => { deferredPrompt = null; });
      hideBanner();
    }

    document.getElementById("btnA2HS")?.addEventListener("click", installPrompt);
    document.getElementById("a2hsInstall")?.addEventListener("click", installPrompt);
    document.getElementById("a2hsClose")?.addEventListener("click", hideBanner);

    // Ao carregar: em iOS que não está instalado, exibe o banner e o botão
    window.addEventListener("load", () => {
      if (isIOS() && !isStandalone()) {
        ensureBtn();
        showBanner();
      }
      // registra SW com versão (evita cache antigo)
      if ("serviceWorker" in navigator) {
        try { navigator.serviceWorker.register("sw.js?v=2"); } catch(e){}
      }
    });

    // ========= Resto (helpers) =========
    function changeLanguage(code){
      const url = new URL(window.location);
      url.searchParams.set("lang", code);
      window.location.href = url.toString();
    }

    function toast(msg, kind="success"){
      const div = document.createElement("div");
      div.className = "fixed bottom-4 left-1/2 -translate-x-1/2 z-50 px-4 py-3 rounded-lg text-white "+(kind==="error"?"bg-red-600":"bg-green-600");
      div.textContent = msg;
      document.body.appendChild(div);
      setTimeout(()=>div.remove(), 2500);
    }

    function updateCartBadge(val){
      const b = document.getElementById("cart-badge");
      if (!b) return;
      if (val>0) { b.textContent = val; b.classList.remove("hidden"); }
      else { b.classList.add("hidden"); }
    }

    // === CSRF dinâmico ===
    async function getCsrf() {
      const r = await fetch("?route=csrf", {
        method: "GET",
        credentials: "same-origin",
        cache: "no-store",
        headers: { "X-Requested-With": "XMLHttpRequest" }
      });
      if (!r.ok) throw new Error("Falha ao obter CSRF");
      const j = await r.json();
      return j.csrf;
    }
    async function postWithCsrf(url, formData) {
      const token = await getCsrf();
      formData = formData || new FormData();
      if (!formData.has("csrf")) formData.append("csrf", token);
      const r = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        cache: "no-store",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-Token": token
        },
        body: formData
      });
      return r;
    }

    async function addToCart(productId, productName){
      const form = new FormData();
      form.append("id",productId);
      try {
        const r = await postWithCsrf("?route=add_cart", form);
        if(!r.ok){ throw new Error("Erro no servidor"); }
        const j = await r.json();
        if(j.success){
          toast(productName+" adicionado!", "success");
          updateCartBadge(j.cart_count || 0);
        }else{
          toast(j.error || "Falha ao adicionar", "error");
        }
      } catch(e){
        toast("Erro ao adicionar ao carrinho", "error");
      }
    }

    async function updateQuantity(id, delta){
      const form = new FormData();
      form.append("id", id);
      form.append("delta", delta);
      try {
        const r = await postWithCsrf("?route=update_cart", form);
        if(r.ok){ location.reload(); }
        else { toast("Erro ao atualizar carrinho", "error"); }
      } catch(e){
        toast("Erro ao atualizar carrinho", "error");
      }
    }
  </script>';

  echo '</body></html>';
}

/* ======================
   ROUTES
   ====================== */

// HOME — busca + categorias + listagem
if ($route === 'home') {
  app_header();
  $pdo = db();

  $builderHtml = '';
  $builderCss  = '';
  try {
    $stLayout = $pdo->prepare("SELECT content, styles FROM page_layouts WHERE page_slug = ? AND status = 'published' LIMIT 1");
    $stLayout->execute(['home']);
    $layoutRow = $stLayout->fetch(PDO::FETCH_ASSOC);
    if ($layoutRow) {
      $builderHtml = sanitize_builder_output($layoutRow['content'] ?? '');
      $builderCss  = trim((string)($layoutRow['styles'] ?? ''));
    }
  } catch (Throwable $e) {
    $builderHtml = '';
    $builderCss = '';
  }
  $hasCustomLayout = ($builderHtml !== '');

  $q = trim((string)($_GET['q'] ?? ''));
  $category_id = (int)($_GET['category'] ?? 0);

  // categorias ativas
  $categories = [];
  try {
    $categories = $pdo->query("SELECT * FROM categories WHERE active=1 ORDER BY sort_order, name")->fetchAll();
  } catch (Throwable $e) { /* sem categorias ainda */ }

  if ($hasCustomLayout) {
    if ($builderCss !== '') {
      echo '<style id="home-builder-css">'.$builderCss.'</style>';
    }
    echo '<section class="home-custom-layout">'.$builderHtml.'</section>';
    echo '<section class="max-w-7xl mx-auto px-4 pt-6 pb-4">';
    echo '  <form method="get" class="bg-white rounded-2xl shadow px-4 py-4 flex flex-col lg:flex-row gap-3 items-stretch">';
    echo '    <input type="hidden" name="route" value="home">';
    echo '    <input class="flex-1 rounded-xl px-4 py-3 border border-gray-200" name="q" value="'.htmlspecialchars($q).'" placeholder="'.htmlspecialchars($d['search'] ?? 'Buscar').'...">';
    echo '    <button class="px-5 py-3 rounded-xl bg-brand-700 text-white font-semibold hover:bg-brand-800"><i class="fa-solid fa-search mr-2"></i>'.htmlspecialchars($d['search'] ?? 'Buscar').'</button>';
    echo '  </form>';
    echo '</section>';
  } else {
    echo '<section class="bg-gradient-to-br from-brand-700 to-amber-400 text-white py-10 mb-8">';
    echo '  <div class="max-w-7xl mx-auto px-4 text-center">';
    echo '    <h2 class="text-3xl md:text-5xl font-bold mb-3">Tudo para sua saúde</h2>';
    echo '    <p class="text-white/90 text-lg mb-6">Experiência de app, rápida e segura</p>';
    echo '    <form method="get" class="max-w-2xl mx-auto flex gap-2">';
    echo '      <input type="hidden" name="route" value="home">';
    echo '      <input class="flex-1 rounded-xl px-4 py-3 text-gray-900 placeholder-gray-500 focus:ring-4 focus:ring-white/30" name="q" value="'.htmlspecialchars($q).'" placeholder="'.htmlspecialchars($d['search'] ?? 'Buscar').'...">';
    echo '      <button class="px-5 py-3 rounded-xl bg-white text-brand-700 font-semibold hover:bg-brand-50"><i class="fa-solid fa-search mr-2"></i>'.htmlspecialchars($d['search'] ?? 'Buscar').'</button>';
    echo '    </form>';
    echo '  </div>';
    echo '</section>';
  }

  // Filtros de categoria (chips)
  echo '<section class="max-w-7xl mx-auto px-4">';
  echo '  <div class="flex items-center gap-3 flex-wrap mb-6">';
  echo '    <button onclick="window.location.href=\'?route=home\'" class="chip px-4 py-2 rounded-full '.($category_id===0?'bg-brand-700 text-white border-brand-700':'bg-white').'">Todas</button>';
  foreach ($categories as $cat) {
    $active = ($category_id === (int)$cat['id']);
    echo '    <button onclick="window.location.href=\'?route=home&category='.(int)$cat['id'].'\'" class="chip px-4 py-2 rounded-full '.($active?'bg-brand-700 text-white border-brand-700':'bg-white').'">'.htmlspecialchars($cat['name']).'</button>';
  }
  echo '  </div>';
  echo '</section>';

  // Busca produtos
  $where = ["p.active=1"];
  $params = [];
  if ($q !== '') {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ? )";
    $like = "%$q%"; $params[]=$like; $params[]=$like; $params[]=$like;
  }
  if ($category_id > 0) {
    $where[] = "p.category_id = ?"; $params[] = $category_id;
  }
  $whereSql = 'WHERE '.implode(' AND ', $where);

  $sql = "SELECT p.*, c.name AS category_name
          FROM products p
          LEFT JOIN categories c ON c.id = p.category_id
          $whereSql
          ORDER BY p.featured DESC, p.created_at DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $products = $stmt->fetchAll();

  echo '<section class="max-w-7xl mx-auto px-4 pb-12">';
  if ($q || $category_id) {
    echo '<div class="mb-4 text-sm text-gray-600">'.count($products).' resultado(s)';
    if ($q) echo ' • busca: <span class="font-medium text-brand-700">'.htmlspecialchars($q).'</span>';
    echo '</div>';
  }

  if (!$products) {
    echo '<div class="text-center py-16">';
    echo '  <i class="fa-solid fa-magnifying-glass text-5xl text-gray-300 mb-4"></i>';
    echo '  <div class="text-lg text-gray-600">Nenhum produto encontrado</div>';
    echo '  <a href="?route=home" class="inline-block mt-6 px-6 py-3 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Voltar</a>';
    echo '</div>';
  } else {
    echo '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">';
    foreach ($products as $p) {
      $img = $p['image_path'] ?: 'assets/no-image.png';
      $img = proxy_img($img); // passa pelo proxy se for URL absoluta
      $in_stock = ((int)$p['stock'] > 0);
      echo '<div class="product-card card rounded-2xl shadow hover:shadow-lg transition overflow-hidden">';
      echo '  <div class="relative h-48 overflow-hidden">';
      echo '    <img src="'.htmlspecialchars($img).'" class="w-full h-full object-cover transition-transform duration-300" alt="'.htmlspecialchars($p['name']).'">';
      if (!empty($p['category_name'])) {
        echo '  <div class="absolute top-3 right-3 text-xs bg-white/90 rounded-full px-2 py-1 text-brand-700">'.htmlspecialchars($p['category_name']).'</div>';
      }
      if (!empty($p['featured'])) {
        echo '  <div class="absolute top-3 left-3 text-[10px] bg-amber-400 text-white rounded-full px-2 py-1 font-bold">DESTAQUE</div>';
      }
      echo '  </div>';
      echo '  <div class="p-4 space-y-2">';
      echo '    <div class="text-sm text-gray-500">SKU: '.htmlspecialchars($p['sku']).'</div>';
      echo '    <div class="font-semibold">'.htmlspecialchars($p['name']).'</div>';
      echo '    <div class="text-sm text-gray-600 line-clamp-2">'.htmlspecialchars($p['description']).'</div>';
      echo '    <div class="flex items-center justify-between pt-2">';
      echo '      <div class="text-2xl font-bold text-gray-900">$ '.number_format((float)$p['price'], 2, ',', '.').'</div>';
      echo '      <div class="text-xs '.($in_stock?'text-green-600':'text-red-600').'">'.($in_stock?'Em estoque':'Indisponível').'</div>';
      echo '    </div>';
      echo '    <div class="pt-2">';
      if ($in_stock) {
        echo '    <button class="w-full px-4 py-3 rounded-xl bg-brand-700 text-white hover:bg-brand-800 btn" onclick="addToCart('.(int)$p['id'].', \''.htmlspecialchars($p['name']).'\')"><i class="fa-solid fa-cart-plus mr-2"></i>Adicionar</button>';
      } else {
        echo '    <button class="w-full px-4 py-3 rounded-xl bg-gray-300 text-gray-600 cursor-not-allowed"><i class="fa-solid fa-ban mr-2"></i>Indisponível</button>';
      }
      echo '    </div>';
      echo '  </div>';
      echo '</div>';
    }
    echo '</div>';
  }

  echo '</section>';
  app_footer();
  exit;
}

// ADD TO CART (AJAX)
if ($route === 'add_cart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');

  // Aceita CSRF do body ou do header
  $csrfIncoming = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
  if (!csrf_check($csrfIncoming)) {
    echo json_encode(['success'=>false, 'error'=>'CSRF inválido']); exit;
  }

  $pdo = db();
  $id  = (int)($_POST['id'] ?? 0);

  $st = $pdo->prepare("SELECT id, name, stock, active FROM products WHERE id=? AND active=1");
  $st->execute([$id]);
  $prod = $st->fetch();
  if (!$prod) { echo json_encode(['success'=>false,'error'=>'Produto não encontrado']); exit; }
  if ((int)$prod['stock'] <= 0) { echo json_encode(['success'=>false,'error'=>'Produto fora de estoque']); exit; }

  if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
  $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + 1;

  // notificação (opcional)
  send_notification('cart_add','Produto ao carrinho', $prod['name'], ['product_id'=>$id]);

  echo json_encode([
    'success'=>true,
    'cart_count'=> array_sum($_SESSION['cart'])
  ]);
  exit;
}

// CART
if ($route === 'cart') {
  app_header();
  $pdo = db();
  $cart = $_SESSION['cart'] ?? [];
  $ids  = array_keys($cart);
  $items = []; $subtotal = 0.0;

  if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT * FROM products WHERE id IN ($in) AND active=1");
    $st->execute($ids);
    foreach ($st as $p) {
      $qty = (int)($cart[$p['id']] ?? 0);
      $line = (float)$p['price'] * $qty;
      $subtotal += $line;
      $items[] = [
        'id'=>(int)$p['id'],
        'sku'=>$p['sku'],
        'name'=>$p['name'],
        'price'=>(float)$p['price'],
        'qty'=>$qty,
        'image'=>$p['image_path'],
        'stock'=>(int)$p['stock']
      ];
    }
  }

  echo '<section class="max-w-5xl mx-auto px-4 py-8">';
  echo '  <h2 class="text-2xl font-bold mb-6"><i class="fa-solid fa-bag-shopping mr-2 text-brand-700"></i>'.htmlspecialchars($d['cart'] ?? 'Carrinho').'</h2>';

  if (!$items) {
    echo '<div class="text-center py-16">';
    echo '  <i class="fa-solid fa-cart-shopping text-6xl text-gray-300 mb-4"></i>';
    echo '  <div class="text-gray-600 mb-6">Seu carrinho está vazio</div>';
    echo '  <a href="?route=home" class="px-6 py-3 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Continuar comprando</a>';
    echo '</div>';
  } else {
    echo '<div class="bg-white rounded-2xl shadow overflow-hidden">';
    echo '  <div class="divide-y">';
    foreach ($items as $it) {
      $img = $it['image'] ?: 'assets/no-image.png';
      $img = proxy_img($img); // passa pelo proxy se for URL absoluta
      echo '  <div class="p-4 flex items-center gap-4">';
      echo '    <img src="'.htmlspecialchars($img).'" class="w-20 h-20 object-cover rounded-lg" alt="produto">';
      echo '    <div class="flex-1">';
      echo '      <div class="font-semibold">'.htmlspecialchars($it['name']).'</div>';
      echo '      <div class="text-xs text-gray-500">SKU: '.htmlspecialchars($it['sku']).'</div>';
      echo '      <div class="text-brand-700 font-bold mt-1">$ '.number_format($it['price'],2,',','.').'</div>';
      echo '    </div>';
      echo '    <div class="flex items-center gap-2">';
      echo '      <button class="w-8 h-8 rounded-full bg-gray-200" onclick="updateQuantity('.(int)$it['id'].', -1)">-</button>';
      echo '      <span class="w-10 text-center font-semibold">'.(int)$it['qty'].'</span>';
      echo '      <button class="w-8 h-8 rounded-full bg-gray-200" onclick="updateQuantity('.(int)$it['id'].', 1)">+</button>';
      echo '    </div>';
      echo '    <div class="text-right w-28 font-semibold">$ '.number_format($it['price']*$it['qty'],2,',','.').'</div>';
      echo '    <a class="text-red-500 text-sm ml-2" href="?route=remove_cart&id='.(int)$it['id'].'&csrf='.csrf_token().'">Remover</a>';
      echo '  </div>';
    }
    echo '  </div>';
    echo '  <div class="p-4 bg-gray-50 flex items-center justify-between">';
    echo '    <div class="text-lg font-semibold">Subtotal</div>';
    echo '    <div class="text-2xl font-bold text-brand-700">$ '.number_format($subtotal,2,',','.').'</div>';
    echo '  </div>';
    echo '  <div class="p-4 flex gap-3">';
    echo '    <a href="?route=home" class="px-5 py-3 rounded-lg border">Continuar comprando</a>';
    echo '    <a href="?route=checkout" class="flex-1 px-5 py-3 rounded-lg bg-brand-700 text-white text-center hover:bg-brand-800">'.htmlspecialchars($d["checkout"] ?? "Finalizar Compra").'</a>';
    echo '  </div>';
    echo '</div>';
  }

  echo '</section>';
  app_footer();
  exit;
}

// REMOVE FROM CART
if ($route === 'remove_cart') {
  if (!csrf_check($_GET['csrf'] ?? '')) die('CSRF inválido');
  $id = (int)($_GET['id'] ?? 0);
  if ($id > 0 && isset($_SESSION['cart'][$id])) unset($_SESSION['cart'][$id]);
  header('Location: ?route=cart');
  exit;
}

// UPDATE CART (AJAX)
if ($route === 'update_cart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  $csrfIncoming = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
  if (!csrf_check($csrfIncoming)) { echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }
  $id = (int)($_POST['id'] ?? 0);
  $delta = (int)($_POST['delta'] ?? 0);
  if ($id <= 0 || $delta === 0) { echo json_encode(['ok'=>false]); exit; }
  $cart = $_SESSION['cart'] ?? [];
  $new = max(0, (int)($cart[$id] ?? 0) + $delta);
  if ($new === 0) { unset($cart[$id]); }
  else {
    $pdo = db();
    $st = $pdo->prepare("SELECT stock FROM products WHERE id=? AND active=1");
    $st->execute([$id]);
    $stock = (int)($st->fetchColumn() ?: 0);
    if ($stock > 0) $new = min($new, $stock);
    $cart[$id] = $new;
  }
  $_SESSION['cart'] = $cart;
  echo json_encode(['ok'=>true,'qty'=>($cart[$id] ?? 0)]); exit;
}

// CHECKOUT
if ($route === 'checkout') {
  $cart = $_SESSION['cart'] ?? [];
  if (empty($cart)) { header('Location: ?route=cart'); exit; }

  app_header();

  $pdo = db();
  $ids = array_keys($cart);
  $in  = implode(',', array_fill(0, count($ids), '?'));
  $st  = $pdo->prepare("SELECT id, name, price, stock FROM products WHERE id IN ($in) AND active=1");
  $st->execute($ids);
  $items = []; $subtotal = 0.0;
  foreach ($st as $p) {
    $qty = (int)($cart[$p['id']] ?? 0);
    $items[] = ['id'=>(int)$p['id'], 'name'=>$p['name'], 'price'=>(float)$p['price'], 'qty'=>$qty];
    $subtotal += (float)$p['price'] * $qty;
  }
  $shipping = 7.00;
  $total = $subtotal + $shipping;

  // Métodos de pagamento dinâmicos
  $paymentMethods = load_payment_methods($pdo, $cfg);

  echo '<section class="max-w-6xl mx-auto px-4 py-8">';
  echo '  <h2 class="text-2xl font-bold mb-6"><i class="fa-solid fa-lock mr-2 text-brand-700"></i>'.htmlspecialchars($d['checkout'] ?? 'Finalizar Compra').'</h2>';
  echo '  <form method="post" action="?route=place_order" enctype="multipart/form-data" class="grid lg:grid-cols-2 gap-6">';
  echo '    <input type="hidden" name="csrf" value="'.csrf_token().'">';

  // Coluna 1 — Dados
  echo '    <div class="space-y-4">';
  echo '      <div class="bg-white rounded-2xl shadow p-5">';
  echo '        <div class="font-semibold mb-3"><i class="fa-solid fa-user mr-2 text-brand-700"></i>'.htmlspecialchars($d["customer_info"] ?? "Dados do Cliente").'</div>';
  echo '        <div class="grid md:grid-cols-2 gap-3">';

  // === Campos pedidos (Nome, Endereço, CEP, E-mail, Telefone) ===
  echo '          <input class="px-4 py-3 border rounded-lg md:col-span-2" name="name" placeholder="Nome *" required>';
  echo '          <textarea class="px-4 py-3 border rounded-lg md:col-span-2" name="address" placeholder="Endereço *" required></textarea>';
  echo '          <input class="px-4 py-3 border rounded-lg" name="zipcode" placeholder="CEP *" required>';
  echo '          <input class="px-4 py-3 border rounded-lg" name="email" type="email" placeholder="E-mail *" required>';
  echo '          <input class="px-4 py-3 border rounded-lg md:col-span-2" name="phone" placeholder="Telefone *" required>';

  echo '        </div>';

  // Pagamento
  echo '      <div class="bg-white rounded-2xl shadow p-5">';
  echo '        <div class="font-semibold mb-3"><i class="fa-solid fa-credit-card mr-2 text-brand-700"></i>'.htmlspecialchars($d["payment_info"] ?? "Pagamento").'</div>';
  if (!$paymentMethods) {
    echo '        <p class="text-sm text-red-600">Nenhum método de pagamento disponível. Atualize as configurações no painel.</p>';
  } else {
    echo '        <div class="grid grid-cols-2 gap-3">';
    foreach ($paymentMethods as $pm) {
      $code = htmlspecialchars($pm['code']);
      $label = htmlspecialchars($pm['name']);
      $icon = 'fa-credit-card';
      switch ($pm['settings']['type'] ?? $pm['code']) {
        case 'pix': $icon = 'fa-qrcode'; break;
        case 'zelle': $icon = 'fa-university'; break;
        case 'venmo': $icon = 'fa-mobile-screen-button'; break;
        case 'paypal': $icon = 'fa-paypal'; break;
        case 'square': $icon = 'fa-arrow-up-right-from-square'; break;
      }
      echo '  <label class="border rounded-xl p-4 cursor-pointer hover:border-brand-300 flex flex-col items-center gap-2">';
      echo '    <input type="radio" name="payment" value="'.$code.'" class="sr-only" required data-code="'.$code.'">';
      if (!empty($pm['icon_path'])) {
        echo '    <img src="'.htmlspecialchars($pm['icon_path']).'" alt="'.$label.'" class="h-10">';
      } else {
        echo '    <i class="fa-solid '.$icon.' text-2xl text-brand-700"></i>';
      }
      echo '    <div class="font-medium">'.$label.'</div>';
      echo '  </label>';
    }
    echo '        </div>';

    foreach ($paymentMethods as $pm) {
      $code = htmlspecialchars($pm['code']);
      $settings = $pm['settings'] ?? [];
      $accountLabel = htmlspecialchars($settings['account_label'] ?? '');
      $accountValue = htmlspecialchars($settings['account_value'] ?? '');
      $placeholders = payment_placeholders($pm, $total);
      $instructionsHtml = render_payment_instructions($pm['instructions'] ?? '', $placeholders);
      echo '  <div data-payment-info="'.$code.'" class="hidden mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg text-sm text-gray-700">';
      if ($accountLabel || $accountValue) {
        echo '    <p class="mb-2"><strong>'.$accountLabel.'</strong>: '.$accountValue.'</p>';
      }
      if ($instructionsHtml !== '') {
        echo '    <p>'.$instructionsHtml.'</p>';
      }
      echo '  </div>';
      if (!empty($pm['require_receipt'])) {
        echo '  <div data-payment-receipt="'.$code.'" class="hidden mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">';
        echo '    <label class="block text-sm font-medium mb-2">Enviar Comprovante (JPG/PNG/PDF)</label>';
        echo '    <input class="w-full px-3 py-2 border rounded" type="file" name="payment_receipt" accept=".jpg,.jpeg,.png,.pdf">';
        echo '    <p class="text-xs text-gray-500 mt-2">Anexe o comprovante após realizar o pagamento.</p>';
        echo '  </div>';
      }
    }
  }

echo '      </div>';
  echo '    </div>';

  // Coluna 2 — Resumo
  echo '    <div>';
  echo '      <div class="bg-white rounded-2xl shadow p-5 sticky top-24">';
  echo '        <div class="font-semibold mb-3"><i class="fa-solid fa-clipboard-list mr-2 text-brand-700"></i>'.htmlspecialchars($d["order_details"] ?? "Resumo do Pedido").'</div>';
  foreach ($items as $it) {
    echo '        <div class="flex items-center justify-between py-2 border-b">';
    echo '          <div class="text-sm"><div class="font-medium">'.htmlspecialchars($it['name']).'</div><div class="text-gray-500">Qtd: '.(int)$it['qty'].'</div></div>';
    echo '          <div class="font-medium">$ '.number_format($it['price']*$it['qty'],2,',','.').'</div>';
    echo '        </div>';
  }
  echo '        <div class="mt-4 space-y-1">';
  echo '          <div class="flex justify-between"><span>'.htmlspecialchars($d["subtotal"] ?? "Subtotal").'</span><span>$ '.number_format($subtotal,2,',','.').'</span></div>';
  echo '          <div class="flex justify-between text-green-600"><span>Frete</span><span>$ 7.00</span></div>';
  echo '          <div class="flex justify-between text-lg font-bold border-t pt-2"><span>Total</span><span class="text-brand-700">$ '.number_format($total,2,',','.').'</span></div>';
  echo '        </div>';
  echo '        <button type="submit" class="w-full mt-5 px-6 py-4 rounded-xl bg-brand-700 text-white hover:bg-brand-800 font-semibold"><i class="fa-solid fa-lock mr-2"></i>'.htmlspecialchars($d["place_order"] ?? "Finalizar Pedido").'</button>';
  echo '      </div>';
  echo '    </div>';

  echo '  </form>';
  echo '</section>';

  echo "<script>
    const paymentRadios = document.querySelectorAll(\"input[name='payment']\");
    const infoBlocks = document.querySelectorAll('[data-payment-info]');
    const receiptBlocks = document.querySelectorAll('[data-payment-receipt]');
    paymentRadios.forEach(radio => {
      radio.addEventListener('change', () => {
        document.querySelectorAll('.border-brand-300').forEach(el => el.classList.remove('border-brand-300'));
        const card = radio.closest('label');
        if (card) card.classList.add('border-brand-300');
        const code = radio.dataset.code;
        infoBlocks.forEach(block => {
          block.classList.toggle('hidden', block.getAttribute('data-payment-info') !== code);
        });
        receiptBlocks.forEach(block => {
          block.classList.toggle('hidden', block.getAttribute('data-payment-receipt') !== code);
        });
      });
    });
  </script>";

  app_footer();
  exit;
}

// PLACE ORDER
if ($route === 'place_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF inválido');

  $pdo = db();
  $cart = $_SESSION['cart'] ?? [];
  if (!$cart) die('Carrinho vazio');

  $name = sanitize_string($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = sanitize_string($_POST['phone'] ?? '');
  $address = sanitize_string($_POST['address'] ?? '');
  $city = sanitize_string($_POST['city'] ?? '');
  $state = sanitize_string($_POST['state'] ?? '');
  $zipcode = sanitize_string($_POST['zipcode'] ?? '');
  $payment_method = $_POST['payment'] ?? '';

  if (!$name || !validate_email($email) || !$phone || !$address) {
    die('Dados inválidos');
  }

  $ids = array_keys($cart);
  $in  = implode(',', array_fill(0, count($ids), '?'));
  $st  = $pdo->prepare("SELECT * FROM products WHERE id IN ($in) AND active=1");
  $st->execute($ids);

  $items = []; $subtotal = 0.0;
  foreach ($st as $p) {
    $qty = (int)($cart[$p['id']] ?? 0);
    if ((int)$p['stock'] < $qty) die('Produto '.$p['name'].' sem estoque');
    $items[] = [
      'id'=>(int)$p['id'],
      'name'=>$p['name'],
      'price'=>(float)$p['price'],
      'qty'=>$qty,
      'sku'=>$p['sku'],
      'square_link'=> trim((string)($p['square_payment_link'] ?? ''))
    ];
    $subtotal += (float)$p['price'] * $qty;
  }
  $shipping = 7.00; $total = $subtotal + $shipping;

  $methods = load_payment_methods($pdo, $cfg);
  $methodMap = [];
  foreach ($methods as $m) {
    $methodMap[$m['code']] = $m;
  }
  if (!isset($methodMap[$payment_method])) {
    die('Método de pagamento inválido');
  }
  $selectedMethod = $methodMap[$payment_method];
  $methodSettings = $selectedMethod['settings'] ?? [];
  $methodType = $methodSettings['type'] ?? $selectedMethod['code'];

  $squareRedirectUrl = null;
  $squareWarning = null;
  if ($methodType === 'square' && ($methodSettings['mode'] ?? 'square_product_link') === 'square_product_link') {
    $squareLinks = [];
    $squareMissing = [];
    foreach ($items as $itemInfo) {
      $link = $itemInfo['square_link'] ?? '';
      if ($link === '') {
        $squareMissing[] = $itemInfo['name'];
      } else {
        $squareLinks[$link] = true;
      }
    }
    if (!empty($squareMissing)) {
      $cleanNames = array_map(function($name){ return sanitize_string($name ?? '', 80); }, $squareMissing);
      $squareWarning = 'Pagamento Square pendente para: '.implode(', ', $cleanNames);
    } elseif (count($squareLinks) > 1) {
      $squareWarning = 'Mais de um link Square encontrado no carrinho. Ajuste os produtos para usar o mesmo link.';
    } elseif (!empty($squareLinks)) {
      $keys = array_keys($squareLinks);
      $squareRedirectUrl = $keys[0];
    }
  }

  $hasUploadedReceipt = !empty($_FILES['payment_receipt']['name']) || !empty($_FILES['zelle_receipt']['name']);
  if (!empty($selectedMethod['require_receipt']) && !$hasUploadedReceipt) {
    die('Envie o comprovante de pagamento para concluir o pedido.');
  }

  $payRef = '';
  switch ($methodType) {
    case 'pix':
      $pixKey = $methodSettings['pix_key'] ?? ($methodSettings['account_value'] ?? '');
      $merchantName = $methodSettings['merchant_name'] ?? 'Farma Fácil';
      $merchantCity = $methodSettings['merchant_city'] ?? 'MACEIO';
      if ($pixKey) {
        $payRef = pix_payload($pixKey, $merchantName, $merchantCity, $total);
      }
      break;
    case 'zelle':
      $payRef = $methodSettings['account_value'] ?? '';
      break;
    case 'venmo':
      $payRef = $methodSettings['venmo_link'] ?? ($methodSettings['account_value'] ?? '');
      break;
    case 'paypal':
      $business = $methodSettings['business'] ?? '';
      $currency = $methodSettings['currency'] ?? 'USD';
      $returnUrl = $methodSettings['return_url'] ?? '';
      $cancelUrl = $methodSettings['cancel_url'] ?? '';
      if ($business) {
        $payRef = 'https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business='.
                  rawurlencode($business).
                  '&currency_code='.rawurlencode($currency).
                  '&amount='.number_format($total, 2, '.', '').
                  '&item_name=Pedido%20Farma%20Facil&return='.
                  rawurlencode($returnUrl).
                  '&cancel_return='.
                  rawurlencode($cancelUrl);
      }
      break;
    case 'square':
      $mode = $methodSettings['mode'] ?? 'square_product_link';
      if ($mode === 'square_product_link') {
        $payRef = $squareRedirectUrl ?: 'SQUARE:pendente';
      } elseif (!empty($methodSettings['redirect_url'])) {
        $payRef = $methodSettings['redirect_url'];
      }
      break;
    default:
      $payRef = $methodSettings['redirect_url'] ?? ($methodSettings['account_value'] ?? '');
      break;
  }

  try {
    $pdo->beginTransaction();
    // cliente
    $cst = $pdo->prepare("INSERT INTO customers(name,email,phone,address,city,state,zipcode) VALUES(?,?,?,?,?,?,?)");
    $cst->execute([$name,$email,$phone,$address,$city,$state,$zipcode]);
    $customer_id = (int)$pdo->lastInsertId();

    // comprovante zelle (opcional)
    $receiptPath = null;
    
    // Recebe comprovante (qualquer método)
    $receiptPath = null;
    if (!empty($_FILES['payment_receipt']['name'])) {
      $up = $_FILES['payment_receipt'];
      if ($up['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($up['name'], PATHINFO_EXTENSION));
        $destDir = cfg()['paths']['zelle_receipts'] ?? (__DIR__.'/storage/zelle_receipts');
        @mkdir($destDir, 0775, true);
        $fname = 'receipt_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
        $dest = rtrim($destDir,'/').'/'.$fname;
        if (@move_uploaded_file($up['tmp_name'], $dest)) {
          $receiptPath = $fname;
        }
      }
    } elseif (!empty($_FILES['zelle_receipt']['name'])) {
      // retrocompatibilidade
      $up = $_FILES['zelle_receipt'];
      if ($up['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($up['name'], PATHINFO_EXTENSION));
        $destDir = cfg()['paths']['zelle_receipts'] ?? (__DIR__.'/storage/zelle_receipts');
        @mkdir($destDir, 0775, true);
        $fname = 'receipt_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
        $dest = rtrim($destDir,'/').'/'.$fname;
        if (@move_uploaded_file($up['tmp_name'], $dest)) {
          $receiptPath = $fname;
        }
      }
    }
    if ($methodType === 'zelle' && !empty($_FILES["zelle_receipt"]["name"])) {
      $val = validate_file_upload($_FILES["zelle_receipt"], ["image/jpeg","image/png","application/pdf"]);
      if ($val["success"]) {
        $dir = __DIR__ . "/storage/zelle_receipts";
        @mkdir($dir, 0775, true);
        $ext = pathinfo($_FILES["zelle_receipt"]["name"], PATHINFO_EXTENSION);
        $fname = "zelle_".time()."_".bin2hex(random_bytes(4)).".".$ext;
        if (move_uploaded_file($_FILES["zelle_receipt"]["tmp_name"], $dir."/".$fname)) {
          $receiptPath = "storage/zelle_receipts/".$fname;
        }
      }
    }

  // pedido
  // Verifica se coluna 'track_token' existe (compat com DBs sem migração)
  $hasTrack = false;
  try {
    $chk = $pdo->query("SHOW COLUMNS FROM orders LIKE 'track_token'");
    $hasTrack = (bool)($chk && $chk->fetch());
  } catch (Throwable $e) { $hasTrack = false; }

  if ($hasTrack) {
    $o = $pdo->prepare("INSERT INTO orders(customer_id, items_json, subtotal, shipping_cost, total, payment_method, payment_ref, status, zelle_receipt, track_token) VALUES(?,?,?,?,?,?,?,?,?,?)");
    $track = bin2hex(random_bytes(16));
    $o->execute([$customer_id, json_encode($items, JSON_UNESCAPED_UNICODE), $subtotal, $shipping, $total, $payment_method, $payRef, "pending", $receiptPath, $track]);
  } else {
    $o = $pdo->prepare("INSERT INTO orders(customer_id, items_json, subtotal, shipping_cost, total, payment_method, payment_ref, status, zelle_receipt) VALUES(?,?,?,?,?,?,?,?,?)");
    $o->execute([$customer_id, json_encode($items, JSON_UNESCAPED_UNICODE), $subtotal, $shipping, $total, $payment_method, $payRef, "pending", $receiptPath]);
  }

    // >>> CORREÇÃO CRÍTICA: definir $order_id ANTES do commit <<<
    $order_id = (int)$pdo->lastInsertId();
    $pdo->commit();

    send_notification("new_order","Novo Pedido","Pedido #$order_id de ".sanitize_html($name),["order_id"=>$order_id,"total"=>$total,"payment_method"=>$payment_method]);
    $_SESSION["cart"] = [];
    send_order_confirmation($order_id, $email);
    if ($methodType === 'square') {
      $_SESSION['square_redirect_url'] = $squareRedirectUrl;
      $_SESSION['square_redirect_warning'] = $squareWarning;
    } else {
      unset($_SESSION['square_redirect_url'], $_SESSION['square_redirect_warning']);
    }

    header("Location: ?route=order_success&id=".$order_id);
    exit;

  } catch (Throwable $e) {
    $pdo->rollBack();
    die("Erro ao processar pedido: ".$e->getMessage());
  }
}


// ORDER SUCCESS
if ($route === 'order_success') {
  $order_id = (int)($_GET['id'] ?? 0);
  if (!$order_id) { header('Location: ?route=home'); exit; }
  app_header();

  // fetch tracking token (safe)
  $track_code = '';
  try {
    $pdo = db();
    $q = $pdo->query("SELECT track_token FROM orders WHERE id=".(int)$order_id);
    if ($q) { $track_code = (string)$q->fetchColumn(); }
  } catch (Throwable $e) {}
  $squareRedirectSession = $_SESSION['square_redirect_url'] ?? null;
  $squareWarningSession = $_SESSION['square_redirect_warning'] ?? null;
  unset($_SESSION['square_redirect_url'], $_SESSION['square_redirect_warning']);

  echo '<section class="max-w-3xl mx-auto px-4 py-16 text-center">';
  echo '  <div class="bg-white rounded-2xl shadow p-8">';
  echo '    <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4"><i class="fa-solid fa-check text-2xl"></i></div>';
  echo '    <h2 class="text-2xl font-bold mb-2">'.htmlspecialchars($d["thank_you_order"] ?? "Obrigado pelo seu pedido!").'</h2>';
  echo '    <p class="text-gray-600 mb-2">Pedido #'.$order_id.' recebido. Enviamos um e-mail com os detalhes.</p>';
  if (!empty($squareWarningSession)) {
    echo '    <div class="mt-4 p-4 rounded-lg border border-amber-200 bg-amber-50 text-amber-800"><i class="fa-solid fa-triangle-exclamation mr-2"></i>'.htmlspecialchars($squareWarningSession, ENT_QUOTES, "UTF-8").'</div>';
  }
  if (!empty($squareRedirectSession)) {
    $safeSquare = htmlspecialchars($squareRedirectSession, ENT_QUOTES, "UTF-8");
    $squareJs = json_encode($squareRedirectSession, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo '    <div class="mt-4 p-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800">';
    echo '      <i class="fa-solid fa-arrow-up-right-from-square mr-2"></i> Abrimos o checkout Square em uma nova aba. Se não aparecer, <a class="underline" href="'.$safeSquare.'" target="_blank" rel="noopener">clique aqui para pagar</a>.';
    echo '    </div>';
    echo '    <script>
      window.addEventListener("load", function(){
        const key = "square_redirect_'.$order_id.'";
        if (!window.sessionStorage.getItem(key)) {
          window.open('.$squareJs.', "_blank");
          window.sessionStorage.setItem(key, "1");
        }
      });
    </script>';
  }
  if ($track_code !== '') {
    echo '    <p class="mb-6">Acompanhe seu pedido: <a class="text-brand-700 underline" href="?route=track&code='.htmlspecialchars($track_code, ENT_QUOTES, "UTF-8").'">clique aqui</a></p>';
  }
  echo '    <a href="?route=home" class="px-6 py-3 rounded-lg bg-brand-700 text-white hover:bg-brand-800">Voltar à loja</a>';
  echo '  </div>';
  echo '</section>';

  app_footer();
  exit;
}





// TRACK ORDER (public)
if ($route === 'track') {
  $code = isset($_GET['code']) ? (string)$_GET['code'] : '';
  app_header();
  echo '<section class="container mx-auto p-6">';
  echo '<div class="max-w-2xl mx-auto bg-white rounded-xl shadow p-6">';
  echo '<h2 class="text-2xl font-bold mb-4">Acompanhar Pedido</h2>';
  if ($code === '') {
    echo '<p class="text-gray-600">Código inválido.</p>';
  } else {
    try {
      $pdo = db();
      $st = $pdo->prepare("SELECT id, status, created_at, total FROM orders WHERE track_token = ?");
      $st->execute([substr($code, 0, 64)]);
      $ord = $st->fetch(PDO::FETCH_ASSOC);
      if (!$ord) {
        echo '<p class="text-gray-600">Pedido não encontrado.</p>';
      } else {
        $id     = (int)($ord['id'] ?? 0);
        $status = htmlspecialchars((string)($ord['status'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $total  = format_currency((float)($ord['total'] ?? 0), (cfg()['store']['currency'] ?? 'USD'));
        $created= htmlspecialchars((string)($ord['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');

        echo '<p class="mb-2">Pedido #'.strval($id).'</p>';
        echo '<p class="mb-2">Status: <span class="font-semibold">'.$status.'</span></p>';
        echo '<p class="mb-2">Total: <span class="font-semibold">'.$total.'</span></p>';
        echo '<p class="text-sm text-gray-500">Criado em: '.$created.'</p>';
      }
    } catch (Throwable $e) {
      echo '<p class="text-red-600">Erro: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'</p>';
    }
  }
  echo '</div></section>';
  app_footer();
  exit;
}

// Fallback — volta pra home

header('Location: ?route=home');
exit;
