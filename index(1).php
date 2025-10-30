<?php
// index.php — Loja FarmaFixed com UI estilo app (quente vermelho→amarelo) + PWA Android/iOS + A2HS
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/config.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/utils.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

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
   Helpers — Header / Footer
   ====================== */
function store_logo_path() {
  $opt = setting_get('store_logo_url');
  if ($opt && file_exists(__DIR__.'/'.$opt)) return $opt;
  foreach (['storage/logo/logo.png','storage/logo/logo.jpg','storage/logo/logo.jpeg','storage/logo/logo.webp','assets/logo.png'] as $c) {
    if (file_exists(__DIR__.'/'.$c)) return $c;
  }
  return null;
}

function app_header() {
  global $d, $cfg;

  $lang = $d['_lang'] ?? 'pt';
  $logo = store_logo_path();

  $cart_count = 0;
  if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = array_sum($_SESSION['cart']);
  }

  // Tema quente (vermelho→amarelo)
  echo '<!doctype html><html lang="'.htmlspecialchars($lang).'"><head>';
  echo '  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
  echo '  <title>'.htmlspecialchars($d["title"] ?? "Farma Fácil").' | Loja</title>';
  echo '  <meta name="description" content="FarmaFixed — experiência tipo app: rápida, responsiva e segura.">';
  echo '  <link rel="manifest" href="/manifest.webmanifest">';

  // Android (barra do navegador)
  echo '  <meta name="theme-color" content="#B91C1C">';

  // iOS (suporte webapp-fullscreen e status-bar)
  echo '  <meta name="apple-mobile-web-app-capable" content="yes">';
  echo '  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">';
  echo '  <meta name="apple-mobile-web-app-title" content="FarmaFixed">';
  // ícone iOS (use o 180x180 do manifest também)
  echo '  <link rel="apple-touch-icon" href="assets/icons/icon-180.png">';

  echo '  <script src="https://cdn.tailwindcss.com"></script>';
  echo '  <script>
          tailwind.config = {
            theme: {
              extend: {
                colors: {
                  brand: {
                    DEFAULT:"#DC2626",  // red-600
                    50:"#FEF2F2",
                    100:"#FEE2E2",
                    200:"#FECACA",
                    300:"#FCA5A5",
                    400:"#F87171",
                    500:"#EF4444",
                    600:"#DC2626",
                    700:"#B91C1C",
                    800:"#991B1B",
                    900:"#7F1D1D"
                  },
                  accent: {
                    DEFAULT:"#F59E0B", // amber-500
                    600:"#D97706",
                    700:"#B45309"
                  }
                },
                gradientColorStops: {
                  "brand-from":"#B91C1C",
                  "brand-to":"#7F1D1D"
                }
              }
            }
          }
        </script>';
  echo '  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">';
  echo '  <link href="assets/theme.css" rel="stylesheet">';
  echo '  <style>
          :root { --brand:#DC2626; --brand-dark:#B91C1C; --accent:#F59E0B; }
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
    echo '      <div class="w-10 h-10 rounded-lg bg-brand-600 text-white grid place-items-center"><i class="fas fa-pills"></i></div>';
  }
  echo '      <div>';
  $store_name = setting_get('store_name', $cfg['store']['name'] ?? 'Farma Fácil');
  echo '        <div class="font-semibold leading-tight">'.htmlspecialchars($store_name).'</div>';
  echo '        <div class="text-xs text-gray-500">Farmácia Online</div>';
  echo '      </div>';
  echo '    </a>';

  echo '    <div class="flex items-center gap-2">';

  // Botão A2HS (Android) — aparece via beforeinstallprompt
  echo '      <button id="btnA2HS" class="a2hs-btn hidden px-3 py-2 rounded-lg text-brand-700 bg-brand-50 hover:bg-brand-100 text-sm">
              <i class="fa-solid fa-mobile-screen-button mr-1"></i> Instalar app
            </button>';

  // Botão guia iOS (pois iOS não dispara o prompt)
  echo '      <button id="btnIOS" class="px-3 py-2 rounded-lg text-brand-700 bg-brand-50 hover:bg-brand-100 text-sm hidden">
              <i class="fa-brands fa-apple mr-1"></i> Instalar no iPhone
            </button>';

  // Troca de idioma
  echo '      <div class="relative">';
  echo '        <select onchange="changeLanguage(this.value)" class="px-3 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-brand-600 text-sm">';
  foreach (['pt'=>'🇧🇷 PT','en'=>'🇺🇸 EN','es'=>'🇪🇸 ES'] as $code=>$label) {
    $selected = ($lang === $code) ? 'selected' : '';
    echo '          <option value="'.$code.'" '.$selected.'>'.$label.'</option>';
  }
  echo '        </select>';
  echo '      </div>';

  // Carrinho
  echo '      <a href="?route=cart" class="relative">';
  echo '        <div class="btn flex items-center gap-2 px-3 py-2 rounded-lg bg-brand-600 text-white hover:bg-brand-700">';
  echo '          <i class="fas fa-shopping-cart"></i>';
  echo '          <span class="hidden sm:inline">'.htmlspecialchars($d['cart'] ?? 'Carrinho').'</span>';
  if ($cart_count > 0) {
    echo '        <span id="cart-badge" class="badge absolute -top-2 -right-2 rounded-full bg-accent text-white text-xs grid place-items-center px-1">'.(int)$cart_count.'</span>';
  } else {
    echo '        <span id="cart-badge" class="badge hidden absolute -top-2 -right-2 rounded-full bg-accent text-white text-xs grid place-items-center px-1">0</span>';
  }
  echo '        </div>';
  echo '      </a>';
  echo '    </div>';
  echo '  </div>';
  echo '</header>';

  // Popover iOS instruções
  echo '<div id="iosPopover" class="fixed inset-0 z-[60] hidden">
          <div class="absolute inset-0 bg-black/40"></div>
          <div class="absolute inset-x-4 bottom-6 rounded-2xl bg-white shadow-xl p-4">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-lg bg-brand-600 text-white grid place-items-center">
                <i class="fa-brands fa-apple"></i>
              </div>
              <div class="flex-1">
                <div class="font-semibold mb-1">Instalar no iPhone</div>
                <p class="text-sm text-gray-600">
                  1) Toque no botão <span class="font-semibold">Compartilhar</span> do Safari.<br>
                  2) Escolha <span class="font-semibold">Adicionar à Tela de Início</span> para criar o atalho do app.
                </p>
              </div>
              <button onclick="hideIOS()" class="text-gray-500 hover:text-gray-700"><i class="fa-solid fa-xmark"></i></button>
            </div>
          </div>
        </div>';

  echo '<main>';
}

function app_footer() {
  echo '</main>';

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

  echo '<script>
    // ----------------------------
    // PWA: Service Worker + A2HS
    // ----------------------------
    if ("serviceWorker" in navigator) {
      window.addEventListener("load", () => {
        navigator.serviceWorker.register("sw.js").catch(()=>{});
      });
    }

    let deferredPrompt = null;
    const btnA2HS = document.getElementById("btnA2HS");
    const btnIOS  = document.getElementById("btnIOS");

    // Detecta iOS (Safari)
    const isIOS = () => {
      const ua = window.navigator.userAgent;
      const iOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
      const isSafari = /^((?!chrome|android).)*safari/i.test(ua);
      return iOS && isSafari;
    };

    // Android (beforeinstallprompt)
    window.addEventListener("beforeinstallprompt", (e) => {
      e.preventDefault();
      deferredPrompt = e;
      if (btnA2HS) btnA2HS.classList.remove("hidden");
    });

    function installPrompt() {
      if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.finally(() => { deferredPrompt = null; btnA2HS?.classList.add("hidden"); });
      } else if (isIOS()) {
        showIOS();
      } else {
        // navegadores sem evento
        toast("Para instalar, use o menu do navegador e escolha Adicionar à tela inicial");
      }
    }
    document.getElementById("btnA2HS")?.addEventListener("click", installPrompt);

    // iOS: mostra botão dedicado + popover de instruções
    if (isIOS() && btnIOS) {
      btnIOS.classList.remove("hidden");
      btnIOS.addEventListener("click", showIOS);
    }
    function showIOS(){ document.getElementById("iosPopover")?.classList.remove("hidden"); }
    function hideIOS(){ document.getElementById("iosPopover")?.classList.add("hidden"); }

    // Linguagem
    function changeLanguage(code){
      const url = new URL(window.location);
      url.searchParams.set("lang", code);
      window.location.href = url.toString();
    }

    // Toast
    function toast(msg, kind="success"){
      const div = document.createElement("div");
      div.className = "fixed bottom-4 left-1/2 -translate-x-1/2 z-50 px-4 py-3 rounded-lg text-white "+(kind==="error"?"bg-brand-700":"bg-green-600");
      div.textContent = msg;
      document.body.appendChild(div);
      setTimeout(()=>div.remove(), 2500);
    }

    // Badge carrinho
    function updateCartBadge(val){
      const b = document.getElementById("cart-badge");
      if (!b) return;
      if (val>0) { b.textContent = val; b.classList.remove("hidden"); }
      else { b.classList.add("hidden"); }
    }

    // Carrinho AJAX
    async function addToCart(productId, productName){
      const form = new FormData();
      form.append("csrf","'.csrf_token().'");
      form.append("id",productId);
      try {
        const r = await fetch("?route=add_cart", { method:"POST", body:form });
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
      form.append("csrf","'.csrf_token().'");
      form.append("id", id);
      form.append("delta", delta);
      const r = await fetch("?route=update_cart", {method:"POST", body:form});
      if(r.ok){ location.reload(); }
    }
    window.installPrompt = installPrompt; // para link no footer
    window.changeLanguage = changeLanguage;
    window.updateQuantity = updateQuantity;
    window.addToCart = addToCart;
    window.hideIOS = hideIOS;
  </script>';

  echo '</body></html>';
}

/* ======================
   ROUTES
   ====================== */

if ($route === 'home') {
  app_header();
  $pdo = db();

  $q = trim((string)($_GET['q'] ?? ''));
  $category_id = (int)($_GET['category'] ?? 0);

  // categorias ativas
  $categories = [];
  try { $categories = $pdo->query("SELECT * FROM categories WHERE active=1 ORDER BY sort_order, name")->fetchAll(); } catch (Throwable $e) {}

  // HERO quente (vermelho->vermelho escuro) + botão branco texto vermelho
  echo '<section class="bg-gradient-to-br from-brand-700 to-brand-900 text-white py-10 mb-8">';
  echo '  <div class="max-w-7xl mx-auto px-4 text-center">';
  echo '    <h2 class="text-3xl md:text-5xl font-extrabold mb-3">Tudo para sua saúde</h2>';
  echo '    <p class="text-white/90 text-lg mb-6">Experiência de app, rápida e segura</p>';
  echo '    <form method="get" class="max-w-2xl mx-auto flex gap-2">';
  echo '      <input type="hidden" name="route" value="home">';
  echo '      <input class="flex-1 rounded-xl px-4 py-3 text-gray-900 placeholder-gray-500 focus:ring-4 focus:ring-accent/40" name="q" value="'.htmlspecialchars($q).'" placeholder="'.htmlspecialchars($d['search'] ?? 'Buscar').'...">';
  echo '      <button class="px-5 py-3 rounded-xl bg-white text-brand-700 font-semibold hover:bg-gray-50"><i class="fa-solid fa-search mr-2"></i>'.htmlspecialchars($d['search'] ?? 'Buscar').'</button>';
  echo '    </form>';
  echo '  </div>';
  echo '</section>';

  // Filtros de categoria (chips)
  echo '<section class="max-w-7xl mx-auto px-4">';
  echo '  <div class="flex items-center gap-3 flex-wrap mb-6">';
  echo '    <button onclick="window.location.href=\'?route=home\'" class="chip px-4 py-2 rounded-full '.($category_id===0?'bg-brand-600 text-white border-brand-600':'bg-white').'">Todas</button>';
  foreach ($categories as $cat) {
    $active = ($category_id === (int)$cat['id']);
    echo '    <button onclick="window.location.href=\'?route=home&category='.(int)$cat['id'].'\'" class="chip px-4 py-2 rounded-full '.($active?'bg-brand-600 text-white border-brand-600':'bg-white').'">'.htmlspecialchars($cat['name']).'</button>';
  }
  echo '  </div>';
  echo '</section>';

  // Busca produtos
  $where = ["p.active=1"];
  $params = [];
  if ($q !== '') {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
    $like = "%$q%"; $params[]=$like; $params[]=$like; $params[]=$like;
  }
  if ($category_id > 0) { $where[] = "p.category_id = ?"; $params[] = $category_id; }
  $whereSql = 'WHERE '.implode(' AND ', $where);

  $sql = "SELECT p.*, c.name AS category_name
          FROM products p
          LEFT JOIN categories c ON c.id = p.category_id
          $whereSql
          ORDER BY p.featured DESC, p.created_at DESC";
  $stmt = db()->prepare($sql);
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
    echo '  <a href="?route=home" class="inline-block mt-6 px-6 py-3 rounded-lg bg-brand-600 text-white hover:bg-brand-700">Voltar</a>';
    echo '</div>';
  } else {
    echo '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">';
    foreach ($products as $p) {
      $img = $p['image_path'] ?: 'assets/no-image.png';
      $in_stock = ((int)$p['stock'] > 0);
      echo '<div class="product-card card rounded-2xl shadow hover:shadow-lg transition overflow-hidden">';
      echo '  <div class="relative h-48 overflow-hidden">';
      echo '    <img src="'.htmlspecialchars($img).'" class="w-full h-full object-cover transition-transform duration-300" alt="'.htmlspecialchars($p['name']).'">';
      if (!empty($p['category_name'])) {
        echo '  <div class="absolute top-3 right-3 text-xs bg-white/90 rounded-full px-2 py-1 text-brand-700">'.htmlspecialchars($p['category_name']).'</div>';
      }
      if (!empty($p['featured'])) {
        echo '  <div class="absolute top-3 left-3 text-[10px] bg-accent text-white rounded-full px-2 py-1 font-bold">DESTAQUE</div>';
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
        echo '    <button class="w-full px-4 py-3 rounded-xl bg-brand-600 text-white hover:bg-brand-700 btn" onclick="addToCart('.(int)$p['id'].', \''.htmlspecialchars($p['name']).'\')"><i class="fa-solid fa-cart-plus mr-2"></i>Adicionar</button>';
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
  if (!csrf_check($_POST['csrf'] ?? '')) { echo json_encode(['success'=>false, 'error'=>'CSRF inválido']); exit; }
  $id  = (int)($_POST['id'] ?? 0);

  $st = db()->prepare("SELECT id, name, stock, active FROM products WHERE id=? AND active=1");
  $st->execute([$id]);
  $prod = $st->fetch();
  if (!$prod) { echo json_encode(['success'=>false,'error'=>'Produto não encontrado']); exit; }
  if ((int)$prod['stock'] <= 0) { echo json_encode(['success'=>false,'error'=>'Produto fora de estoque']); exit; }

  if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
  $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + 1;

  send_notification('cart_add','Produto ao carrinho', $prod['name'], ['product_id'=>$id]);

  echo json_encode(['success'=>true,'cart_count'=> array_sum($_SESSION['cart'])]);
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
        'id'=>(int)$p['id'],'sku'=>$p['sku'],'name'=>$p['name'],
        'price'=>(float)$p['price'],'qty'=>$qty,'image'=>$p['image_path'],
        'stock'=>(int)$p['stock']
      ];
    }
  }

  echo '<section class="max-w-5xl mx-auto px-4 py-8">';
  echo '  <h2 class="text-2xl font-bold mb-6"><i class="fa-solid fa-bag-shopping mr-2 text-brand-600"></i>'.htmlspecialchars($d['cart'] ?? 'Carrinho').'</h2>';

  if (!$items) {
    echo '<div class="text-center py-16">';
    echo '  <i class="fa-solid fa-cart-shopping text-6xl text-gray-300 mb-4"></i>';
    echo '  <div class="text-gray-600 mb-6">Seu carrinho está vazio</div>';
    echo '  <a href="?route=home" class="px-6 py-3 rounded-lg bg-brand-600 text-white hover:bg-brand-700">Continuar comprando</a>';
    echo '</div>';
  } else {
    echo '<div class="bg-white rounded-2xl shadow overflow-hidden">';
    echo '  <div class="divide-y">';
    foreach ($items as $it) {
      $img = $it['image'] ?: 'assets/no-image.png';
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
      echo '    <a class="text-red-600 text-sm ml-2 hover:underline" href="?route=remove_cart&id='.(int)$it['id'].'&csrf='.csrf_token().'">Remover</a>';
      echo '  </div>';
    }
    echo '  </div>';
    echo '  <div class="p-4 bg-gray-50 flex items-center justify-between">';
    echo '    <div class="text-lg font-semibold">Subtotal</div>';
    echo '    <div class="text-2xl font-bold text-brand-700">$ '.number_format($subtotal,2,',','.').'</div>';
    echo '  </div>';
    echo '  <div class="p-4 flex gap-3">';
    echo '    <a href="?route=home" class="px-5 py-3 rounded-lg border">Continuar comprando</a>';
    echo '    <a href="?route=checkout" class="flex-1 px-5 py-3 rounded-lg bg-brand-600 text-white text-center hover:bg-brand-700">'.htmlspecialchars($d["checkout"] ?? "Finalizar Compra").'</a>';
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
  header('Location: ?route=cart'); exit;
}

// UPDATE CART (AJAX)
if ($route === 'update_cart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  if (!csrf_check($_POST['csrf'] ?? '')) { echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }
  $id = (int)($_POST['id'] ?? 0);
  $delta = (int)($_POST['delta'] ?? 0);
  if ($id <= 0 || $delta === 0) { echo json_encode(['ok'=>false]); exit; }
  $cart = $_SESSION['cart'] ?? [];
  $new = max(0, (int)($cart[$id] ?? 0) + $delta);
  if ($new === 0) { unset($cart[$id]); }
  else {
    $st = db()->prepare("SELECT stock FROM products WHERE id=? AND active=1");
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

  $ids = array_keys($cart);
  $in  = implode(',', array_fill(0, count($ids), '?'));
  $st  = db()->prepare("SELECT id, name, price, stock FROM products WHERE id IN ($in) AND active=1");
  $st->execute($ids);
  $items = []; $subtotal = 0.0;
  foreach ($st as $p) {
    $qty = (int)($cart[$p['id']] ?? 0);
    $items[] = ['id'=>(int)$p['id'], 'name'=>$p['name'], 'price'=>(float)$p['price'], 'qty'=>$qty];
    $subtotal += (float)$p['price'] * $qty;
  }
  $shipping = 7.00; $total = $subtotal + $shipping;

  $payments = $cfg['payments'] ?? [];

  echo '<section class="max-w-6xl mx-auto px-4 py-8">';
  echo '  <h2 class="text-2xl font-bold mb-6"><i class="fa-solid fa-lock mr-2 text-brand-600"></i>'.htmlspecialchars($d['checkout'] ?? 'Finalizar Compra').'</h2>';
  echo '  <form method="post" action="?route=place_order" enctype="multipart/form-data" class="grid lg:grid-cols-2 gap-6">';
  echo '    <input type="hidden" name="csrf" value="'.csrf_token().'">';

  // Dados cliente
  echo '    <div class="space-y-4">';
  echo '      <div class="bg-white rounded-2xl shadow p-5">';
  echo '        <div class="font-semibold mb-3"><i class="fa-solid fa-user mr-2 text-brand-600"></i>'.htmlspecialchars($d["customer_info"] ?? "Dados do Cliente").'</div>';
  echo '        <div class="grid md:grid-cols-2 gap-3">';
  echo '          <input class="px-4 py-3 border rounded-lg" name="name" placeholder="'.htmlspecialchars($d["name"] ?? "Nome").' *" required>';
  echo '          <input class="px-4 py-3 border rounded-lg" name="email" type="email" placeholder="'.htmlspecialchars($d["email"] ?? "E-mail").' *" required>';
  echo '          <input class="px-4 py-3 border rounded-lg" name="phone" placeholder="'.htmlspecialchars($d["phone"] ?? "Telefone").' *" required>';
  echo '          <input class="px-4 py-3 border rounded-lg" name="zipcode" placeholder="'.htmlspecialchars($d["zipcode"] ?? "CEP").'">';
  echo '        </div>';
  echo '        <textarea class="w-full mt-3 px-4 py-3 border rounded-lg" name="address" rows="2" placeholder="'.htmlspecialchars($d["address"] ?? "Endereço").' *" required></textarea>';
  echo '        <div class="grid md:grid-cols-2 gap-3 mt-3">';
  echo '          <input class="px-4 py-3 border rounded-lg" name="city" placeholder="'.htmlspecialchars($d["city"] ?? "Cidade").'">';
  echo '          <input class="px-4 py-3 border rounded-lg" name="state" placeholder="'.htmlspecialchars($d["state"] ?? "Estado").'">';
  echo '        </div>';
  echo '      </div>';

  // Pagamento
  echo '      <div class="bg-white rounded-2xl shadow p-5">';
  echo '        <div class="font-semibold mb-3"><i class="fa-solid fa-credit-card mr-2 text-brand-600"></i>'.htmlspecialchars($d["payment_info"] ?? "Pagamento").'</div>';
  echo '        <div class="grid grid-cols-2 gap-3">';
  foreach (['pix','zelle','venmo','paypal'] as $pm) {
    if (!empty($payments[$pm]['enabled'])) {
      $icon = ($pm==='pix')?'fa-qrcode':(($pm==='zelle')?'fa-university':(($pm==='venmo')?'fa-mobile-screen-button':'fa-paypal'));
      echo '  <label class="border rounded-xl p-4 cursor-pointer hover:border-accent">';
      echo '    <input type="radio" name="payment" value="'.$pm.'" class="sr-only" required>';
      echo '    <div class="text-center"><i class="fa-solid '.$icon.' text-2xl text-brand-600 mb-2"></i><div class="font-medium">'.htmlspecialchars($d[$pm] ?? strtoupper($pm)).'</div></div>';
      echo '  </label>';
    }
  }
  echo '        </div>';
  if (!empty($payments["zelle"]["enabled"])) {
    echo '  <div id="zelle-upload" class="hidden mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">';
    echo '    <label class="block text-sm font-medium mb-2">'.htmlspecialchars($d["upload_receipt"] ?? "Enviar Comprovante").' (JPG/PNG/PDF)</label>';
    echo '    <input class="w-full px-3 py-2 border rounded" type="file" name="zelle_receipt" accept=".jpg,.jpeg,.png,.pdf">';
    echo '    <p class="text-xs text-gray-600 mt-1">Envie o comprovante de pagamento Zelle</p>';
    echo '  </div>';
  }
  echo '      </div>';
  echo '    </div>';

  // Resumo
  echo '    <div>';
  echo '      <div class="bg-white rounded-2xl shadow p-5 sticky top-24">';
  echo '        <div class="font-semibold mb-3"><i class="fa-solid fa-clipboard-list mr-2 text-brand-600"></i>'.htmlspecialchars($d["order_details"] ?? "Resumo do Pedido").'</div>';
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
  echo '        <button type="submit" class="w-full mt-5 px-6 py-4 rounded-xl bg-brand-600 text-white hover:bg-brand-700 font-semibold"><i class="fa-solid fa-lock mr-2"></i>'.htmlspecialchars($d["place_order"] ?? "Finalizar Pedido").'</button>';
  echo '      </div>';
  echo '    </div>';

  echo '  </form>';
  echo '</section>';

  echo '<script>
    document.querySelectorAll("input[name=payment]").forEach(r => {
      r.addEventListener("change", function(){
        document.querySelectorAll(".hover\\:border-accent").forEach(el=>el.classList.remove("border-accent"));
        const card = this.closest("label");
        if(card) card.classList.add("border-accent");
        const zup = document.getElementById("zelle-upload");
        if(zup) zup.classList.toggle("hidden", this.value !== "zelle");
      });
    });
  </script>';

  app_footer();
  exit;
}

// PLACE ORDER
if ($route === 'place_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF inválido');

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

  if (!$name || !validate_email($email) || !$phone || !$address || !in_array($payment_method, ['pix','zelle','venmo','paypal'], true)) {
    die('Dados inválidos');
  }

  $ids = array_keys($cart);
  $in  = implode(',', array_fill(0, count($ids), '?'));
  $st  = db()->prepare("SELECT * FROM products WHERE id IN ($in) AND active=1");
  $st->execute($ids);

  $items = []; $subtotal = 0.0;
  foreach ($st as $p) {
    $qty = (int)($cart[$p['id']] ?? 0);
    if ((int)$p['stock'] < $qty) die('Produto '.$p['name'].' sem estoque');
    $items[] = ['id'=>(int)$p['id'], 'name'=>$p['name'], 'price'=>(float)$p['price'], 'qty'=>$qty, 'sku'=>$p['sku']];
    $subtotal += (float)$p['price'] * $qty;
  }
  $shipping = 7.00; $total = $subtotal + $shipping;

  try {
    $pdo = db();
    $pdo->beginTransaction();

    $cst = $pdo->prepare("INSERT INTO customers(name,email,phone,address,city,state,zipcode) VALUES(?,?,?,?,?,?,?)");
    $cst->execute([$name,$email,$phone,$address,$city,$state,$zipcode]);
    $customer_id = (int)$pdo->lastInsertId();

    $receiptPath = null;
    if ($payment_method === 'zelle' && !empty($_FILES['zelle_receipt']['name'])) {
      $val = validate_file_upload($_FILES['zelle_receipt'], ['image/jpeg','image/png','application/pdf']);
      if ($val['success']) {
        $dir = __DIR__ . '/storage/zelle_receipts';
        @mkdir($dir, 0775, true);
        $ext = pathinfo($_FILES['zelle_receipt']['name'], PATHINFO_EXTENSION);
        $fname = 'zelle_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
        if (move_uploaded_file($_FILES['zelle_receipt']['tmp_name'], $dir.'/'.$fname)) {
          $receiptPath = 'storage/zelle_receipts/'.$fname;
        }
      }
    }

    $payments = $cfg['payments'] ?? [];
    $payRef = '';
    if ($payment_method === 'pix') {
      $payRef = pix_payload($payments['pix']['pix_key'], $payments['pix']['merchant_name'], $payments['pix']['merchant_city'], $total);
    } elseif ($payment_method === 'venmo') {
      $payRef = "https://venmo.com/u/".$payments['venmo']['handle'];
    } elseif ($payment_method === 'paypal') {
      $payRef = "https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=".$payments['paypal']['business']."&currency_code=".$payments['paypal']['currency']."&amount=".$total."&item_name=Pedido%20Farma%20Facil";
    } elseif ($payment_method === 'zelle') {
      $payRef = $payments['zelle']['recipient_email'];
    }

    $o = $pdo->prepare("INSERT INTO orders(customer_id, items_json, subtotal, shipping_cost, total, payment_method, payment_ref, status, zelle_receipt) VALUES(?,?,?,?,?,?,?,?,?)");
    $o->execute([$customer_id, json_encode($items, JSON_UNESCAPED_UNICODE), $subtotal, $shipping, $total, $payment_method, $payRef, 'pending', $receiptPath]);
    $order_id = (int)$pdo->lastInsertId();

    foreach ($items as $it) {
      $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([$it['qty'], $it['id']]);
    }

    $pdo->commit();

    send_notification('new_order','Novo Pedido',"Pedido #$order_id de ".sanitize_html($name),['order_id'=>$order_id,'total'=>$total,'payment_method'=>$payment_method]);
    $_SESSION['cart'] = [];
    send_order_confirmation($order_id, $email);

    header('Location: ?route=order_success&id='.$order_id); exit;

  } catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    die('Erro ao processar pedido: '.$e->getMessage());
  }
}

// ORDER SUCCESS
if ($route === 'order_success') {
  $order_id = (int)($_GET['id'] ?? 0);
  if (!$order_id) { header('Location: ?route=home'); exit; }
  app_header();

  echo '<section class="max-w-3xl mx-auto px-4 py-16 text-center">';
  echo '  <div class="bg-white rounded-2xl shadow p-8">';
  echo '    <div class="w-16 h-16 rounded-full bg-green-100 text-green-600 grid place-items-center mx-auto mb-4"><i class="fa-solid fa-check text-2xl"></i></div>';
  echo '    <h2 class="text-2xl font-bold mb-2">'.htmlspecialchars($d["thank_you_order"] ?? "Obrigado pelo seu pedido!").'</h2>';
  echo '    <p class="text-gray-600 mb-6">Pedido #'.$order_id.' recebido. Enviamos um e-mail com os detalhes.</p>';
  echo '    <a href="?route=home" class="px-6 py-3 rounded-lg bg-brand-600 text-white hover:bg-brand-700">Voltar à loja</a>';
  echo '  </div>';
  echo '</section>';

  app_footer();
  exit;
}

// Fallback
header('Location: ?route=home'); exit;
