<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/bootstrap.php';
require __DIR__.'/config.php';
require __DIR__.'/lib/db.php';
require __DIR__.'/lib/utils.php';
require __DIR__.'/admin_layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!function_exists('require_admin')) {
  function require_admin(){
    if (empty($_SESSION['admin_id'])) {
      header('Location: admin.php?route=login');
      exit;
    }
  }
}
if (!function_exists('csrf_token')) {
  function csrf_token(){ if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
}
if (!function_exists('csrf_check')) {
  function csrf_check($token){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$token); }
}

require_admin();

$pdo = db();
$canEditSettings = admin_can('manage_settings');
$canManagePayments = admin_can('manage_payment_methods');
$canManageBuilder = admin_can('manage_builder');
$isSuperAdmin = is_super_admin();

function pm_sanitize($value, $max = 255) {
  $value = trim((string)$value);
  if (mb_strlen($value) > $max) {
    $value = mb_substr($value, 0, $max);
  }
  return $value;
}

function pm_clip_text($value, $max = 8000) {
  $value = (string)$value;
  if (mb_strlen($value) > $max) {
    $value = mb_substr($value, 0, $max);
  }
  return $value;
}

function pm_slug($text) {
  $text = strtolower($text);
  $text = preg_replace('/[^a-z0-9\-]+/i', '-', $text);
  $text = trim($text, '-');
  return $text ?: 'metodo';
}

function pm_decode_settings($row) {
  $settings = [];
  if (!empty($row['settings'])) {
    $json = json_decode($row['settings'], true);
    if (is_array($json)) {
      $settings = $json;
    }
  }
  return $settings;
}

function pm_collect_settings($type, array $data) {
  $settings = [
    'type' => $type,
    'account_label' => pm_sanitize($data['account_label'] ?? '', 120),
    'account_value' => pm_sanitize($data['account_value'] ?? '', 255),
    'button_bg' => pm_sanitize($data['button_bg'] ?? '#dc2626', 20),
    'button_text' => pm_sanitize($data['button_text'] ?? '#ffffff', 20),
    'button_hover_bg' => pm_sanitize($data['button_hover_bg'] ?? '#b91c1c', 20),
  ];

  switch ($type) {
    case 'pix':
      $settings['pix_key'] = pm_sanitize($data['pix_key'] ?? '', 140);
      $settings['merchant_name'] = pm_sanitize($data['pix_merchant_name'] ?? '', 120);
      $settings['merchant_city'] = pm_sanitize($data['pix_merchant_city'] ?? '', 60);
      break;
    case 'zelle':
      $settings['recipient_name'] = pm_sanitize($data['zelle_recipient_name'] ?? '', 120);
      break;
    case 'venmo':
      $settings['venmo_link'] = pm_sanitize($data['venmo_link'] ?? '', 255);
      break;
    case 'paypal':
      $settings['business'] = pm_sanitize($data['paypal_business'] ?? '', 180);
      $settings['currency'] = strtoupper(pm_sanitize($data['paypal_currency'] ?? 'USD', 3));
      $settings['return_url'] = pm_sanitize($data['paypal_return_url'] ?? '', 255);
      $settings['cancel_url'] = pm_sanitize($data['paypal_cancel_url'] ?? '', 255);
      break;
    case 'square':
      $settings['mode'] = pm_sanitize($data['square_mode'] ?? 'square_product_link', 60);
      $settings['open_new_tab'] = !empty($data['square_open_new_tab']);
      $settings['redirect_url'] = pm_sanitize($data['square_redirect_url'] ?? '', 255);
      break;
    case 'stripe':
      $settings['mode'] = pm_sanitize($data['stripe_mode'] ?? 'stripe_product_link', 60);
      $settings['open_new_tab'] = !empty($data['stripe_open_new_tab']);
      $settings['redirect_url'] = pm_sanitize($data['stripe_redirect_url'] ?? '', 255);
      break;
    default:
      $settings['mode'] = pm_sanitize($data['custom_mode'] ?? 'manual', 60);
      $settings['redirect_url'] = pm_sanitize($data['custom_redirect_url'] ?? '', 255);
      break;
  }

  return $settings;
}

function pm_upload_icon(array $file) {
  if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
    return [true, null];
  }
  $validation = validate_file_upload($file, ['image/jpeg','image/png','image/webp','image/svg+xml'], 1024 * 1024);
  if (!$validation['success']) {
    return [false, $validation['message'] ?? 'Arquivo inválido'];
  }
  $dir = __DIR__.'/storage/payment_icons';
  @mkdir($dir, 0775, true);
  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','webp','svg'], true)) {
    $map = [
      'image/jpeg' => 'jpg',
      'image/png' => 'png',
      'image/webp' => 'webp',
      'image/svg+xml' => 'svg'
    ];
    $ext = $map[$validation['mime_type'] ?? ''] ?? 'png';
  }
  $filename = 'pm_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
  $dest = $dir.'/'.$filename;
  if (!@move_uploaded_file($file['tmp_name'], $dest)) {
    return [false, 'Falha ao salvar ícone'];
  }
  return [true, 'storage/payment_icons/'.$filename];
}

$action = $_GET['action'] ?? 'list';
$tab = $_GET['tab'] ?? 'general';
if (!in_array($tab, ['general','payments','builder'], true)) {
  $tab = 'general';
}

if ($action === 'reorder' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  require_admin_capability('manage_payment_methods');
  header('Content-Type: application/json; charset=utf-8');
  $payload = json_decode(file_get_contents('php://input'), true);
  $csrf = $payload['csrf'] ?? '';
  if (!csrf_check($csrf)) {
    echo json_encode(['ok' => false, 'error' => 'invalid_csrf']);
    exit;
  }
  $ids = $payload['ids'] ?? [];
  if (!is_array($ids) || !$ids) {
    echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
    exit;
  }
  $order = 0;
  $st = $pdo->prepare('UPDATE payment_methods SET sort_order = ? WHERE id = ?');
  foreach ($ids as $id) {
    $order += 10;
    $st->execute([$order, (int)$id]);
  }
  echo json_encode(['ok' => true]);
  exit;
}

if ($action === 'toggle' && isset($_GET['id'])) {
  if (!csrf_check($_GET['csrf'] ?? '')) die('CSRF');
  require_admin_capability('manage_payment_methods');
  $id = (int)$_GET['id'];
  $pdo->prepare('UPDATE payment_methods SET is_active = IF(is_active=1,0,1) WHERE id=?')->execute([$id]);
  header('Location: settings.php?tab=payments');
  exit;
}

if ($action === 'delete' && isset($_GET['id'])) {
  if (!csrf_check($_GET['csrf'] ?? '')) die('CSRF');
  require_super_admin();
  $id = (int)$_GET['id'];
  $pdo->prepare('DELETE FROM payment_methods WHERE id=?')->execute([$id]);
  header('Location: settings.php?tab=payments');
  exit;
}

if (($action === 'create' || $action === 'update') && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
  require_admin_capability('manage_payment_methods');

  $id = (int)($_GET['id'] ?? 0);
  $name = pm_sanitize($_POST['name'] ?? '');
  if ($name === '') {
    die('Nome obrigatório');
  }
  $codeInput = pm_sanitize($_POST['code'] ?? '', 50);
  $type = pm_sanitize($_POST['method_type'] ?? 'custom', 50);
  $code = $codeInput ?: pm_slug($name);
  if ($type !== 'custom') {
    $code = $type;
  }

  $description = pm_sanitize($_POST['description'] ?? '', 500);
  $instructions = trim((string)($_POST['instructions'] ?? ''));
  $isActive = isset($_POST['is_active']) ? 1 : 0;
  $requireReceipt = isset($_POST['require_receipt']) ? 1 : 0;

  $settings = pm_collect_settings($type, $_POST);

  $iconPath = null;
  if ($action === 'update') {
    $st = $pdo->prepare('SELECT icon_path FROM payment_methods WHERE id=?');
    $st->execute([$id]);
    $iconPath = $st->fetchColumn();
  }

  if (!empty($_FILES['icon']['name'])) {
    [$ok, $result] = pm_upload_icon($_FILES['icon']);
    if (!$ok) {
      die('Erro no upload de ícone: '.$result);
    }
    if ($iconPath && file_exists(__DIR__.'/'.$iconPath)) {
      @unlink(__DIR__.'/'.$iconPath);
    }
    $iconPath = $result;
  }

  if ($action === 'create') {
    $check = $pdo->prepare('SELECT COUNT(*) FROM payment_methods WHERE code = ?');
    $check->execute([$code]);
    if ($check->fetchColumn()) {
      die('Código já utilizado por outro método.');
    }
    $ins = $pdo->prepare('INSERT INTO payment_methods(code,name,description,instructions,settings,icon_path,is_active,require_receipt,sort_order) VALUES (?,?,?,?,?,?,?,?,?)');
    $sortOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM payment_methods')->fetchColumn();
    $ins->execute([
      $code,
      $name,
      $description,
      $instructions,
      json_encode($settings, JSON_UNESCAPED_UNICODE),
      $iconPath,
      $isActive,
      $requireReceipt,
      $sortOrder
    ]);
  } else {
    $dup = $pdo->prepare('SELECT COUNT(*) FROM payment_methods WHERE code = ? AND id <> ?');
    $dup->execute([$code, $id]);
    if ($dup->fetchColumn()) {
      die('Outro método já utiliza este código.');
    }
    $upd = $pdo->prepare('UPDATE payment_methods SET code=?, name=?, description=?, instructions=?, settings=?, icon_path=?, is_active=?, require_receipt=?, updated_at=NOW() WHERE id=?');
    $upd->execute([
      $code,
      $name,
      $description,
      $instructions,
      json_encode($settings, JSON_UNESCAPED_UNICODE),
      $iconPath,
      $isActive,
      $requireReceipt,
      $id
    ]);
  }

  header('Location: settings.php?tab=payments');
  exit;
}

if ($action === 'save_general' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
  require_admin_capability('manage_settings');
  $errors = [];

  $storeName = pm_sanitize($_POST['store_name'] ?? '', 120);
  if ($storeName === '') {
    $errors[] = 'Informe o nome da loja.';
  } else {
    setting_set('store_name', $storeName);
  }

  $storeEmail = trim((string)($_POST['store_email'] ?? ''));
  if ($storeEmail !== '') {
    if (validate_email($storeEmail)) {
      setting_set('store_email', $storeEmail);
    } else {
      $errors[] = 'E-mail de suporte inválido.';
    }
  } else {
    setting_set('store_email', '');
  }

  $storePhone = pm_sanitize($_POST['store_phone'] ?? '', 60);
  setting_set('store_phone', $storePhone);

  $storeAddress = pm_sanitize($_POST['store_address'] ?? '', 240);
  setting_set('store_address', $storeAddress);

  $metaTitle = pm_sanitize($_POST['store_meta_title'] ?? '', 160);
  if ($metaTitle === '') {
    $metaTitle = ($storeName ?: 'Farma Fácil').' | Loja';
  }
  setting_set('store_meta_title', $metaTitle);

  $pwaName = pm_sanitize($_POST['pwa_name'] ?? '', 80);
  if ($pwaName === '') {
    $pwaName = $storeName ?: 'Farma Fácil';
  }
  setting_set('pwa_name', $pwaName);

  $pwaShort = pm_sanitize($_POST['pwa_short_name'] ?? '', 40);
  if ($pwaShort === '') {
    $pwaShort = $pwaName;
  }
  setting_set('pwa_short_name', $pwaShort);

  if (!empty($_FILES['store_logo']['name'])) {
    $upload = save_logo_upload($_FILES['store_logo']);
    if (!empty($upload['success'])) {
      setting_set('store_logo_url', $upload['path']);
      setting_set('store_logo', $upload['path']);
    } else {
      $errors[] = $upload['message'] ?? 'Falha ao enviar logo.';
    }
  }

  $heroTitle = pm_sanitize($_POST['home_hero_title'] ?? '', 160);
  $heroSubtitle = pm_sanitize($_POST['home_hero_subtitle'] ?? '', 240);
  if ($heroTitle === '') {
    $heroTitle = 'Tudo para sua saúde';
  }
  if ($heroSubtitle === '') {
    $heroSubtitle = 'Experiência de app, rápida e segura.';
  }
  setting_set('home_hero_title', $heroTitle);
  setting_set('home_hero_subtitle', $heroSubtitle);

  $featuredEnabled = isset($_POST['home_featured_enabled']) ? '1' : '0';
  $featuredTitle = pm_sanitize($_POST['home_featured_title'] ?? '', 80);
  $featuredSubtitle = pm_sanitize($_POST['home_featured_subtitle'] ?? '', 200);
  if ($featuredTitle === '') {
    $featuredTitle = 'Ofertas em destaque';
  }
  if ($featuredSubtitle === '') {
    $featuredSubtitle = 'Seleção especial com preços imperdíveis.';
  }
  setting_set('home_featured_enabled', $featuredEnabled);
  setting_set('home_featured_title', $featuredTitle);
  setting_set('home_featured_subtitle', $featuredSubtitle);

  $emailDefaultSet = email_template_defaults($storeName ?: (cfg()['store']['name'] ?? 'Sua Loja'));
  $emailCustomerSubject = pm_sanitize($_POST['email_customer_subject'] ?? '', 180);
  if ($emailCustomerSubject === '') {
    $emailCustomerSubject = $emailDefaultSet['customer_subject'];
  }
  setting_set('email_customer_subject', $emailCustomerSubject);

  $emailCustomerBody = pm_clip_text($_POST['email_customer_body'] ?? '', 8000);
  if ($emailCustomerBody === '') {
    $emailCustomerBody = $emailDefaultSet['customer_body'];
  }
  setting_set('email_customer_body', $emailCustomerBody);

  $emailAdminSubject = pm_sanitize($_POST['email_admin_subject'] ?? '', 180);
  if ($emailAdminSubject === '') {
    $emailAdminSubject = $emailDefaultSet['admin_subject'];
  }
  setting_set('email_admin_subject', $emailAdminSubject);

  $emailAdminBody = pm_clip_text($_POST['email_admin_body'] ?? '', 8000);
  if ($emailAdminBody === '') {
    $emailAdminBody = $emailDefaultSet['admin_body'];
  }
  setting_set('email_admin_body', $emailAdminBody);

  $whatsEnabled = isset($_POST['whatsapp_enabled']) ? '1' : '0';
  $whatsNumberRaw = pm_sanitize($_POST['whatsapp_number'] ?? '', 40);
  $whatsNumber = preg_replace('/\D+/', '', $whatsNumberRaw);
  $whatsButtonText = pm_sanitize($_POST['whatsapp_button_text'] ?? '', 80);
  $whatsMessage = pm_sanitize($_POST['whatsapp_message'] ?? '', 400);
  if ($whatsButtonText === '') {
    $whatsButtonText = 'Fale com a gente';
  }
  if ($whatsMessage === '') {
    $whatsMessage = 'Olá! Gostaria de tirar uma dúvida sobre os produtos.';
  }
  setting_set('whatsapp_enabled', $whatsEnabled);
  setting_set('whatsapp_number', $whatsNumber);
  setting_set('whatsapp_button_text', $whatsButtonText);
  setting_set('whatsapp_message', $whatsMessage);

  if (!empty($_FILES['pwa_icon']['name'])) {
    $pwaUpload = save_pwa_icon_upload($_FILES['pwa_icon']);
    if (empty($pwaUpload['success'])) {
      $errors[] = $pwaUpload['message'] ?? 'Falha ao atualizar o ícone do app.';
    }
  }
  $themeColor = pm_sanitize($_POST['theme_color'] ?? '#2060C8', 20);
  if (!preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', $themeColor)) {
    $themeColor = '#2060C8';
  }
  setting_set('theme_color', strtoupper($themeColor));
  $headerSublineNew = pm_sanitize($_POST['header_subline'] ?? '', 120);
  if ($headerSublineNew === '') $headerSublineNew = 'Farmácia Online';
  setting_set('header_subline', $headerSublineNew);
  $footerTitleNew = pm_sanitize($_POST['footer_title'] ?? '', 80);
  if ($footerTitleNew === '') $footerTitleNew = 'FarmaFixed';
  setting_set('footer_title', $footerTitleNew);
  $footerDescriptionNew = pm_sanitize($_POST['footer_description'] ?? '', 160);
  if ($footerDescriptionNew === '') $footerDescriptionNew = 'Sua farmácia online com experiência de app.';
  setting_set('footer_description', $footerDescriptionNew);

  if ($errors) {
    $_SESSION['settings_general_error'] = implode(' ', $errors);
    header('Location: settings.php?tab=general&error=1');
    exit;
  }

  header('Location: settings.php?tab=general&saved=1');
  exit;
}

try {
  $methods = $pdo->query('SELECT * FROM payment_methods ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $methods = [];
}

$editRow = null;
$editSettings = [];
if ($action === 'edit' && isset($_GET['id'])) {
  $id = (int)$_GET['id'];
  $st = $pdo->prepare('SELECT * FROM payment_methods WHERE id=?');
  $st->execute([$id]);
  $editRow = $st->fetch(PDO::FETCH_ASSOC);
  if ($editRow) {
    $editSettings = pm_decode_settings($editRow);
    $tab = 'payments';
  }
}

$draftStmt = $pdo->prepare("SELECT content, styles FROM page_layouts WHERE page_slug=? AND status='draft' LIMIT 1");
$draftStmt->execute(['home']);
$draftRow = $draftStmt->fetch(PDO::FETCH_ASSOC);

$publishedStmt = $pdo->prepare("SELECT content, styles FROM page_layouts WHERE page_slug=? AND status='published' LIMIT 1");
$publishedStmt->execute(['home']);
$publishedRow = $publishedStmt->fetch(PDO::FETCH_ASSOC);

$layoutData = [
  'draft' => $draftRow ?: null,
  'published' => $publishedRow ?: null,
  'csrf' => csrf_token(),
];
$layoutJson = json_encode($layoutData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$storeCfg = cfg()['store'] ?? [];
$storeNameCurrent = setting_get('store_name', $storeCfg['name'] ?? 'Farma Fácil');
$storeEmailCurrent = setting_get('store_email', $storeCfg['support_email'] ?? 'contato@example.com');
$storePhoneCurrent = setting_get('store_phone', $storeCfg['phone'] ?? '');
$storeAddressCurrent = setting_get('store_address', $storeCfg['address'] ?? '');
$storeLogoCurrent = get_logo_path();
$generalError = $_SESSION['settings_general_error'] ?? '';
unset($_SESSION['settings_general_error']);

$sections = [
  [
    'key' => 'general',
    'title' => 'Dados da loja',
    'description' => 'Nome, endereço, telefone, e-mail e logo exibidos para os clientes.',
    'icon' => 'fa-store'
  ],
  [
    'key' => 'payments',
    'title' => 'Pagamentos',
    'description' => 'Configure métodos ativos, instruções personalizadas e ordem de exibição.',
    'icon' => 'fa-credit-card'
  ],
  [
    'key' => 'builder',
    'title' => 'Editor da Home',
    'description' => 'Personalize a página inicial com o editor visual (drag-and-drop).',
    'icon' => 'fa-paintbrush'
  ],
];

admin_header('Configurações');
?>
<section class="space-y-6">
  <div class="dashboard-hero">
    <div class="flex flex-col gap-3">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold">Configurações da plataforma</h1>
        <p class="text-white/90 text-sm md:text-base mt-1">Ajuste rapidamente informações da loja, pagamentos e layout da home.</p>
      </div>
      <div class="quick-links">
        <a class="quick-link" href="settings.php?tab=general">
          <span class="icon"><i class="fa-solid fa-store"></i></span>
          <span><div class="font-semibold">Dados gerais</div><div class="text-xs opacity-80">Logo, contatos e textos da vitrine</div></span>
        </a>
        <a class="quick-link" href="settings.php?tab=payments">
          <span class="icon"><i class="fa-solid fa-credit-card"></i></span>
          <span><div class="font-semibold">Pagamentos</div><div class="text-xs opacity-80">Formas de pagamento e instruções</div></span>
        </a>
        <a class="quick-link" href="settings.php?tab=builder">
          <span class="icon"><i class="fa-solid fa-paintbrush"></i></span>
          <span><div class="font-semibold">Editor da home</div><div class="text-xs opacity-80">Monte a página inicial em tempo real</div></span>
        </a>
        <a class="quick-link" href="dashboard.php">
          <span class="icon"><i class="fa-solid fa-gauge-high"></i></span>
          <span><div class="font-semibold">Voltar ao dashboard</div><div class="text-xs opacity-80">Resumo da operação e pedidos</div></span>
        </a>
      </div>
    </div>
  </div>

  <div class="tab-controls">
    <?php foreach ($sections as $section): ?>
      <a href="settings.php?tab=<?= $section['key']; ?>" class="<?= $tab === $section['key'] ? 'active' : ''; ?>">
        <i class="fa-solid <?= $section['icon']; ?> mr-2"></i><?= sanitize_html($section['title']); ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="settings-grid">
  <div data-tab-panel="general" class="card <?= $tab === 'general' ? '' : 'hidden'; ?>">
    <div class="card-body settings-form">
    <h2 class="text-lg font-semibold mb-1">Informações da Loja</h2>
    <?php if (isset($_GET['saved'])): ?>
      <div class="alert alert-success">
        <i class="fa-solid fa-circle-check"></i>
        <span>Configurações atualizadas com sucesso.</span>
      </div>
    <?php endif; ?>
    <?php if ($generalError): ?>
      <div class="alert alert-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <span><?= sanitize_html($generalError); ?></span>
      </div>
    <?php endif; ?>
    <?php
      $heroTitleCurrent = setting_get('home_hero_title', 'Tudo para sua saúde');
      $heroSubtitleCurrent = setting_get('home_hero_subtitle', 'Experiência de app, rápida e segura.');
$featuredEnabledCurrent = (int)setting_get('home_featured_enabled', '0');
$featuredTitleCurrent = setting_get('home_featured_title', 'Ofertas em destaque');
$featuredSubtitleCurrent = setting_get('home_featured_subtitle', 'Seleção especial com preços imperdíveis.');
$emailDefaults = email_template_defaults($storeNameCurrent ?: ($storeCfg['name'] ?? ''));
$emailCustomerSubjectCurrent = setting_get('email_customer_subject', $emailDefaults['customer_subject']);
$emailCustomerBodyCurrent = setting_get('email_customer_body', $emailDefaults['customer_body']);
$emailAdminSubjectCurrent = setting_get('email_admin_subject', $emailDefaults['admin_subject']);
$emailAdminBodyCurrent = setting_get('email_admin_body', $emailDefaults['admin_body']);
$whatsappEnabled = (int)setting_get('whatsapp_enabled', '0');
$whatsappNumber = setting_get('whatsapp_number', '');
$whatsappButtonText = setting_get('whatsapp_button_text', 'Fale com a gente');
$whatsappMessage = setting_get('whatsapp_message', 'Olá! Gostaria de tirar uma dúvida sobre os produtos.');
$headerSublineCurrent = setting_get('header_subline', 'Farmácia Online');
$footerTitleCurrent = setting_get('footer_title', 'FarmaFixed');
$footerDescriptionCurrent = setting_get('footer_description', 'Sua farmácia online com experiência de app.');
$themeColorCurrent = setting_get('theme_color', '#2060C8');
$heroBackgroundCurrent = setting_get('hero_background', 'gradient');
$heroAccentColorCurrent = setting_get('hero_accent_color', '#F59E0B');
$metaTitleCurrent = setting_get('store_meta_title', ($storeNameCurrent ?: 'Farma Fácil').' | Loja');
$pwaNameCurrent = setting_get('pwa_name', $storeNameCurrent ?: 'Farma Fácil');
$pwaShortNameCurrent = setting_get('pwa_short_name', $pwaNameCurrent);
$pwaIcons = get_pwa_icon_paths();
$pwaIconPreview = pwa_icon_url(192);
    ?>
    <form method="post" enctype="multipart/form-data" action="settings.php?tab=general&action=save_general">
      <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
      <?php if (!$canEditSettings): ?>
        <div class="alert alert-warning">
          <i class="fa-solid fa-triangle-exclamation"></i>
          <span>Você não tem permissão para editar estas configurações. Os campos estão bloqueados para leitura.</span>
        </div>
      <?php endif; ?>
      <fieldset class="space-y-6" <?= $canEditSettings ? '' : 'disabled'; ?>>
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Nome da loja</label>
          <input class="input w-full" name="store_name" value="<?= sanitize_html($storeNameCurrent); ?>" maxlength="120" required>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">E-mail de suporte</label>
          <input class="input w-full" name="store_email" type="email" value="<?= sanitize_html($storeEmailCurrent); ?>" maxlength="160" placeholder="contato@minhaloja.com">
          <p class="hint mt-1">Utilizado em notificações e exibição para o cliente.</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Telefone</label>
          <input class="input w-full" name="store_phone" value="<?= sanitize_html($storePhoneCurrent); ?>" maxlength="60" placeholder="+1 (305) 555-0123">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Endereço</label>
          <textarea class="textarea w-full" name="store_address" rows="2" maxlength="240" placeholder="Rua, bairro, cidade, estado"><?= sanitize_html($storeAddressCurrent); ?></textarea>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Logo da loja (PNG/JPG/WEBP · máx 2MB)</label>
          <?php if ($storeLogoCurrent): ?>
            <div class="mb-3"><img src="<?= sanitize_html($storeLogoCurrent); ?>" alt="Logo atual" class="h-16 object-contain rounded-md border border-gray-200 p-2 bg-white"></div>
          <?php else: ?>
            <p class="hint mb-2">Nenhuma logo encontrada. Você pode enviar uma agora.</p>
          <?php endif; ?>
          <input class="block w-full text-sm text-gray-600" type="file" name="store_logo" accept=".png,.jpg,.jpeg,.webp">
        </div>
      </div>

      <hr class="border-gray-200">

      <h3 class="text-md font-semibold">Texto do destaque na Home</h3>
      <div class="grid md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Título principal</label>
          <input class="input w-full" name="home_hero_title" maxlength="160" value="<?= sanitize_html($heroTitleCurrent); ?>" required>
          <p class="text-xs text-gray-500 mt-1">Texto destacado exibido em negrito (ex.: "Tudo para sua saúde").</p>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Subtítulo</label>
          <textarea class="textarea w-full" name="home_hero_subtitle" rows="2" maxlength="240" required><?= sanitize_html($heroSubtitleCurrent); ?></textarea>
          <p class="text-xs text-gray-500 mt-1">Linha de apoio exibida logo abaixo do título.</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Texto curto abaixo do logo</label>
          <input class="input w-full" name="header_subline" maxlength="120" value="<?= sanitize_html($headerSublineCurrent); ?>" placeholder="Farmácia Online">
          <p class="hint mt-1">Exibido no topo, ao lado da logo.</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Cor primária (theme-color)</label>
          <input class="input w-full" type="color" name="theme_color" value="<?= sanitize_html($themeColorCurrent); ?>">
          <p class="hint mt-1">Usada em navegadores móveis e barras de título.</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Título do rodapé</label>
          <input class="input w-full" name="footer_title" maxlength="80" value="<?= sanitize_html($footerTitleCurrent); ?>">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Descrição do rodapé</label>
          <textarea class="textarea w-full" name="footer_description" rows="2" maxlength="160"><?= sanitize_html($footerDescriptionCurrent); ?></textarea>
        </div>
      </div>

      <hr class="border-gray-200">

      <h3 class="text-md font-semibold">Vitrine de destaques</h3>
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Exibir seção na home</label>
          <select class="select" name="home_featured_enabled">
            <option value="0" <?= !$featuredEnabledCurrent ? 'selected' : ''; ?>>Ocultar</option>
            <option value="1" <?= $featuredEnabledCurrent ? 'selected' : ''; ?>>Mostrar</option>
          </select>
          <p class="hint mt-1">Quando ativa, aparece antes da lista principal com os produtos marcados como “Destaque”.</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Título da seção</label>
          <input class="input w-full" name="home_featured_title" maxlength="80" value="<?= sanitize_html($featuredTitleCurrent); ?>" placeholder="Ofertas em destaque">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Descrição de apoio</label>
          <textarea class="textarea w-full" name="home_featured_subtitle" rows="2" maxlength="200"><?= sanitize_html($featuredSubtitleCurrent); ?></textarea>
          <p class="hint mt-1">Ex.: “Seleção especial com preços imperdíveis — de X por Y”.</p>
        </div>
      </div>

      <div class="md:col-span-2 border border-gray-200 rounded-xl p-4 bg-white space-y-4">
        <div class="flex flex-col gap-1">
          <h3 class="text-md font-semibold flex items-center gap-2"><i class="fa-solid fa-envelope text-brand-600"></i> Templates de e-mail</h3>
          <p class="text-xs text-gray-500">Personalize os e-mails enviados para o cliente e para a equipe. Placeholders disponíveis: <code>{{store_name}}</code>, <code>{{order_id}}</code>, <code>{{customer_name}}</code>, <code>{{customer_email}}</code>, <code>{{customer_phone}}</code>, <code>{{order_total}}</code>, <code>{{order_subtotal}}</code>, <code>{{order_shipping}}</code>, <code>{{payment_method}}</code>, <code>{{order_items}}</code>, <code>{{track_link}}</code>, <code>{{track_url}}</code>, <code>{{support_email}}</code>, <code>{{shipping_address}}</code>, <code>{{order_notes}}</code>, <code>{{admin_order_url}}</code>.</p>
        </div>
        <div class="grid md:grid-cols-2 gap-4">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">Assunto (cliente)</label>
            <input class="input w-full" name="email_customer_subject" maxlength="180" value="<?= htmlspecialchars($emailCustomerSubjectCurrent, ENT_QUOTES, 'UTF-8'); ?>">
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">Conteúdo (cliente)</label>
            <textarea class="textarea w-full font-mono text-sm h-44" name="email_customer_body"><?= htmlspecialchars($emailCustomerBodyCurrent, ENT_QUOTES, 'UTF-8'); ?></textarea>
            <p class="hint mt-1">Você pode usar HTML básico. Ex.: &lt;p&gt;, &lt;strong&gt;, &lt;ul&gt;.</p>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">Assunto (admin)</label>
            <input class="input w-full" name="email_admin_subject" maxlength="180" value="<?= htmlspecialchars($emailAdminSubjectCurrent, ENT_QUOTES, 'UTF-8'); ?>">
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">Conteúdo (admin)</label>
            <textarea class="textarea w-full font-mono text-sm h-44" name="email_admin_body"><?= htmlspecialchars($emailAdminBodyCurrent, ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>
        </div>
      </div>

      <div class="md:col-span-2 border border-gray-200 rounded-xl p-4 bg-white">
        <h3 class="text-md font-semibold mb-2 flex items-center gap-2"><i class="fa-brands fa-whatsapp text-[#25D366]"></i> WhatsApp Flutuante</h3>
        <p class="text-xs text-gray-500 mb-3">Defina o número e a mensagem exibida no botão flutuante da loja. O link abre a conversa direto no WhatsApp.</p>
        <div class="grid md:grid-cols-2 gap-4">
          <label class="inline-flex items-center gap-2 text-sm font-medium">
            <input type="checkbox" name="whatsapp_enabled" value="1" <?= $whatsappEnabled ? 'checked' : ''; ?>>
            Exibir botão flutuante
          </label>
          <div>
            <label class="block text-sm font-medium mb-1">Número com DDI e DDD</label>
            <input class="input w-full" name="whatsapp_number" value="<?= sanitize_html($whatsappNumber); ?>" placeholder="ex.: 1789101122" maxlength="30">
            <p class="hint mt-1">Informe apenas números (ex.: 1789101122 para +1 789 101 122).</p>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Texto do botão</label>
            <input class="input w-full" name="whatsapp_button_text" value="<?= sanitize_html($whatsappButtonText); ?>" maxlength="80" placeholder="Fale com nossa equipe">
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">Mensagem inicial enviada no WhatsApp</label>
            <textarea class="textarea w-full" name="whatsapp_message" rows="3" maxlength="400"><?= sanitize_html($whatsappMessage); ?></textarea>
            <p class="hint mt-1">Será preenchida automaticamente quando o cliente abrir a conversa.</p>
          </div>
        </div>
      </div>

      <div class="md:col-span-2 border border-gray-200 rounded-xl p-4 bg-white">
        <h3 class="text-md font-semibold mb-2 flex items-center gap-2"><i class="fa-solid fa-mobile-screen-button text-brand-600"></i> Identidade do App/PWA</h3>
        <p class="text-xs text-gray-500 mb-3">Personalize o título da aba, o nome exibido quando instalado e o ícone utilizado pelo aplicativo.</p>
        <div class="grid md:grid-cols-2 gap-4">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">Título da aba (meta title)</label>
            <input class="input w-full" name="store_meta_title" maxlength="160" value="<?= sanitize_html($metaTitleCurrent); ?>">
            <p class="hint mt-1">Aparece em <code>&lt;title&gt;</code> e no histórico do navegador. Ex.: "<?= sanitize_html($storeNameCurrent ?: 'Farma Fácil'); ?> | Loja".</p>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Nome do app (PWA)</label>
            <input class="input w-full" name="pwa_name" maxlength="80" value="<?= sanitize_html($pwaNameCurrent); ?>" required>
            <p class="hint mt-1">Nome completo exibido ao instalar o app.</p>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Nome curto</label>
            <input class="input w-full" name="pwa_short_name" maxlength="40" value="<?= sanitize_html($pwaShortNameCurrent); ?>" required>
            <p class="hint mt-1">Usado em ícones e notificações. Máximo recomendado: 12 caracteres.</p>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Ícone do app (PNG fundo transparente)</label>
            <?php if ($pwaIconPreview): ?>
              <div class="flex items-center gap-4 mb-2">
                <img src="<?= sanitize_html($pwaIconPreview); ?>" alt="Ícone atual" class="h-16 w-16 rounded-lg border bg-white p-2">
                <span class="text-xs text-gray-500 leading-snug">Tamanhos gerados automaticamente (512x512, 192x192 e 180x180).</span>
              </div>
            <?php endif; ?>
            <input class="block w-full text-sm text-gray-600" type="file" name="pwa_icon" accept=".png">
            <p class="hint mt-1">Envie uma imagem quadrada, preferencialmente 512x512 px, em formato PNG.</p>
          </div>
        </div>
      </div>

      </fieldset>
      <div class="flex justify-end gap-3">
        <?php if ($canEditSettings): ?>
          <button type="submit" class="btn btn-primary px-5 py-2"><i class="fa-solid fa-floppy-disk mr-2"></i>Salvar alterações</button>
        <?php endif; ?>
        <a href="index.php" target="_blank" class="btn btn-ghost px-5 py-2"><i class="fa-solid fa-up-right-from-square mr-2"></i>Ver loja</a>
      </div>
    </form>
    </div>
  </div>

  <div data-tab-panel="payments" class="space-y-4 <?= $tab === 'payments' ? '' : 'hidden'; ?>">
    <div class="card p-6">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h2 class="text-lg font-semibold">Métodos de pagamento</h2>
          <p class="text-sm text-gray-500">Arraste para reordenar e clique para editar ou ativar/desativar.</p>
        </div>
        <?php if ($canManagePayments): ?>
          <a class="btn btn-primary" href="settings.php?tab=payments&action=new"><i class="fa-solid fa-plus mr-2"></i>Novo método</a>
        <?php endif; ?>
      </div>
      <?php if (!$methods): ?>
        <p class="text-center text-gray-500 mt-6">Nenhum método cadastrado.</p>
      <?php else: ?>
        <ul id="pm-sortable" class="divide-y divide-gray-200 mt-4" data-sortable-enabled="<?= $canManagePayments ? '1' : '0'; ?>">
          <?php foreach ($methods as $pm): $settings = pm_decode_settings($pm); ?>
            <li class="flex items-center justify-between gap-4 px-4 py-3 bg-white" data-id="<?= (int)$pm['id']; ?>">
              <div class="flex items-center gap-3">
                <span class="cursor-move text-gray-400"><i class="fa-solid fa-grip-lines"></i></span>
                <?php if (!empty($pm['icon_path'])): ?>
                  <img src="<?= sanitize_html($pm['icon_path']); ?>" class="h-8 w-8 rounded" alt="icon">
                <?php else: ?>
                  <div class="h-8 w-8 rounded flex items-center justify-center" style="background:rgba(32,96,200,.08);color:var(--brand-700);">
                    <i class="fa-solid fa-credit-card"></i>
                  </div>
                <?php endif; ?>
                <div>
                  <div class="font-semibold"><?= sanitize_html($pm['name']); ?></div>
                  <div class="text-xs text-gray-500">Código: <?= sanitize_html($pm['code']); ?></div>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <?= ((int)$pm['is_active'] === 1) ? '<span class="badge ok">Ativo</span>' : '<span class="badge danger">Inativo</span>'; ?>
                <?= !empty($pm['require_receipt']) ? '<span class="badge warn">Comprovante</span>' : ''; ?>
              </div>
              <div class="flex items-center gap-2">
                <?php if ($canManagePayments): ?>
                  <a class="btn btn-ghost" href="settings.php?tab=payments&action=edit&id=<?= (int)$pm['id']; ?>" title="Editar"><i class="fa-solid fa-pen"></i></a>
                  <a class="btn btn-ghost" href="settings.php?tab=payments&action=toggle&id=<?= (int)$pm['id']; ?>&csrf=<?= csrf_token(); ?>" title="Ativar/Inativar"><i class="fa-solid fa-power-off"></i></a>
                <?php else: ?>
                  <span class="text-xs text-gray-400">Somente leitura</span>
                <?php endif; ?>
                <?php if ($isSuperAdmin): ?>
                  <a class="btn btn-ghost text-red-600" href="settings.php?tab=payments&action=delete&id=<?= (int)$pm['id']; ?>&csrf=<?= csrf_token(); ?>" onclick="return confirm('Remover este método?')" title="Excluir"><i class="fa-solid fa-trash"></i></a>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="card p-6">
      <h3 class="text-md font-semibold mb-3"><?= $editRow ? 'Editar método' : 'Novo método'; ?></h3>
      <?php
        $formRow = $editRow ?: [];
        $formSettings = $editSettings ?: ['type' => 'custom'];
        $idForForm = (int)($formRow['id'] ?? 0);
        $formAction = $editRow ? 'update&id='.$idForForm : 'create';
      ?>
      <form class="space-y-4" method="post" enctype="multipart/form-data" action="settings.php?tab=payments&action=<?= $formAction; ?>">
        <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
        <?php if (!$canManagePayments): ?>
          <div class="alert alert-warning">
            <i class="fa-solid fa-circle-info"></i>
            <span>Você não possui permissão para alterar métodos de pagamento.</span>
          </div>
        <?php endif; ?>
        <fieldset class="space-y-4" <?= $canManagePayments ? '' : 'disabled'; ?>>
        <div class="grid md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Nome</label>
            <input class="input w-full" name="name" value="<?= sanitize_html($formRow['name'] ?? ''); ?>" required>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Código</label>
            <?php $isDefaultCode = in_array($formRow['code'] ?? '', ['pix','zelle','venmo','paypal','square','stripe'], true); ?>
            <input class="input w-full" name="code" value="<?= sanitize_html($formRow['code'] ?? ''); ?>" <?= $isDefaultCode ? 'readonly' : ''; ?> placeholder="ex.: square">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Tipo</label>
            <?php $currentType = $isDefaultCode ? ($formRow['code'] ?? 'custom') : ($formSettings['type'] ?? 'custom'); ?>
            <select class="select w-full" name="method_type" <?= $isDefaultCode ? 'disabled' : ''; ?>>
              <?php $types = ['pix'=>'Pix','zelle'=>'Zelle','venmo'=>'Venmo','paypal'=>'PayPal','square'=>'Square','stripe'=>'Stripe','custom'=>'Personalizado']; ?>
              <?php foreach ($types as $value => $label): ?>
                <option value="<?= $value; ?>" <?= $currentType === $value ? 'selected' : ''; ?>><?= $label; ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($isDefaultCode): ?><input type="hidden" name="method_type" value="<?= sanitize_html($currentType); ?>"><?php endif; ?>
          </div>
          <div class="flex items-center gap-4">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" <?= (!isset($formRow['is_active']) || (int)$formRow['is_active'] === 1) ? 'checked' : ''; ?>> Ativo</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="require_receipt" value="1" <?= !empty($formRow['require_receipt']) ? 'checked' : ''; ?>> Exigir comprovante</label>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Descrição interna</label>
          <input class="input w-full" name="description" value="<?= sanitize_html($formRow['description'] ?? ''); ?>" placeholder="Visível apenas no painel">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Instruções (placeholders: {valor_pedido}, {numero_pedido}, {email_cliente}, {account_label}, {account_value}, {stripe_link})</label>
          <textarea class="textarea w-full" name="instructions" rows="4"><?= htmlspecialchars($formRow['instructions'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Legenda do campo</label>
            <input class="input w-full" name="account_label" value="<?= sanitize_html($formSettings['account_label'] ?? ''); ?>">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Valor/Conta</label>
            <input class="input w-full" name="account_value" value="<?= sanitize_html($formSettings['account_value'] ?? ''); ?>">
          </div>
        </div>

        <div class="grid md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Cor do botão</label>
            <input class="input w-full" type="color" name="button_bg" value="<?= sanitize_html($formSettings['button_bg'] ?? '#dc2626'); ?>">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Cor do texto</label>
            <input class="input w-full" type="color" name="button_text" value="<?= sanitize_html($formSettings['button_text'] ?? '#ffffff'); ?>">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Cor ao hover</label>
            <input class="input w-full" type="color" name="button_hover_bg" value="<?= sanitize_html($formSettings['button_hover_bg'] ?? '#b91c1c'); ?>">
          </div>
        </div>

        <div id="type-fields" class="grid md:grid-cols-2 gap-4">
          <div data-type="pix">
            <label class="block text-sm font-medium mb-1">Chave Pix</label>
            <input class="input w-full" name="pix_key" value="<?= sanitize_html($formSettings['pix_key'] ?? ''); ?>">
          </div>
          <div data-type="pix">
            <label class="block text-sm font-medium mb-1">Nome do recebedor</label>
            <input class="input w-full" name="pix_merchant_name" value="<?= sanitize_html($formSettings['merchant_name'] ?? ''); ?>">
          </div>
          <div data-type="pix">
            <label class="block text-sm font-medium mb-1">Cidade</label>
            <input class="input w-full" name="pix_merchant_city" value="<?= sanitize_html($formSettings['merchant_city'] ?? ''); ?>">
          </div>

          <div data-type="zelle">
            <label class="block text-sm font-medium mb-1">Nome do recebedor</label>
            <input class="input w-full" name="zelle_recipient_name" value="<?= sanitize_html($formSettings['recipient_name'] ?? ''); ?>">
          </div>

          <div data-type="venmo">
            <label class="block text-sm font-medium mb-1">Link/Usuário do Venmo</label>
            <input class="input w-full" name="venmo_link" value="<?= sanitize_html($formSettings['venmo_link'] ?? ''); ?>">
          </div>

          <div data-type="paypal">
            <label class="block text-sm font-medium mb-1">Conta PayPal / Email</label>
            <input class="input w-full" name="paypal_business" value="<?= sanitize_html($formSettings['business'] ?? ''); ?>">
          </div>
          <div data-type="paypal">
            <label class="block text-sm font-medium mb-1">Moeda</label>
            <input class="input w-full" name="paypal_currency" value="<?= sanitize_html($formSettings['currency'] ?? 'USD'); ?>">
          </div>
          <div data-type="paypal">
            <label class="block text-sm font-medium mb-1">Return URL</label>
            <input class="input w-full" name="paypal_return_url" value="<?= sanitize_html($formSettings['return_url'] ?? ''); ?>">
          </div>
          <div data-type="paypal">
            <label class="block text-sm font-medium mb-1">Cancel URL</label>
            <input class="input w-full" name="paypal_cancel_url" value="<?= sanitize_html($formSettings['cancel_url'] ?? ''); ?>">
          </div>

          <div data-type="square">
            <label class="block text-sm font-medium mb-1">Modo</label>
            <?php $squareMode = $formSettings['mode'] ?? 'square_product_link'; ?>
            <select class="select w-full" name="square_mode">
              <option value="square_product_link" <?= $squareMode === 'square_product_link' ? 'selected' : ''; ?>>Link definido por produto</option>
              <option value="direct_url" <?= $squareMode === 'direct_url' ? 'selected' : ''; ?>>URL fixa</option>
            </select>
          </div>
          <div data-type="square">
            <label class="block text-sm font-medium mb-1">Abrir em nova aba?</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="square_open_new_tab" value="1" <?= !empty($formSettings['open_new_tab']) ? 'checked' : ''; ?>> Nova aba</label>
          </div>
          <div data-type="square">
            <label class="block text-sm font-medium mb-1">URL fixa (opcional)</label>
            <input class="input w-full" name="square_redirect_url" value="<?= sanitize_html($formSettings['redirect_url'] ?? ''); ?>" placeholder="https://">
          </div>

          <div data-type="stripe">
            <label class="block text-sm font-medium mb-1">Modo</label>
            <?php $stripeMode = $formSettings['mode'] ?? 'stripe_product_link'; ?>
            <select class="select w-full" name="stripe_mode">
              <option value="stripe_product_link" <?= $stripeMode === 'stripe_product_link' ? 'selected' : ''; ?>>Link definido por produto</option>
              <option value="direct_url" <?= $stripeMode === 'direct_url' ? 'selected' : ''; ?>>URL fixa</option>
            </select>
          </div>
          <div data-type="stripe">
            <label class="block text-sm font-medium mb-1">Abrir em nova aba?</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="stripe_open_new_tab" value="1" <?= !empty($formSettings['open_new_tab']) ? 'checked' : ''; ?>> Nova aba</label>
          </div>
          <div data-type="stripe">
            <label class="block text-sm font-medium mb-1">URL fixa (opcional)</label>
            <input class="input w-full" name="stripe_redirect_url" value="<?= sanitize_html($formSettings['redirect_url'] ?? ''); ?>" placeholder="https://">
          </div>

          <div data-type="custom">
            <label class="block text-sm font-medium mb-1">Modo</label>
            <input class="input w-full" name="custom_mode" value="<?= sanitize_html($formSettings['mode'] ?? 'manual'); ?>">
          </div>
          <div data-type="custom">
            <label class="block text-sm font-medium mb-1">URL de redirecionamento</label>
            <input class="input w-full" name="custom_redirect_url" value="<?= sanitize_html($formSettings['redirect_url'] ?? ''); ?>">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Ícone (PNG/SVG opcional)</label>
          <input type="file" name="icon" accept="image/png,image/jpeg,image/webp,image/svg+xml">
          <?php if (!empty($formRow['icon_path'])): ?>
            <div class="mt-2"><img src="<?= sanitize_html($formRow['icon_path']); ?>" alt="ícone" class="h-10"></div>
          <?php endif; ?>
        </div>
        </fieldset>

        <div class="flex items-center gap-2 pt-2">
          <?php if ($canManagePayments): ?>
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk mr-2"></i>Salvar</button>
          <?php endif; ?>
          <a class="btn btn-ghost" href="settings.php?tab=payments">Cancelar</a>
        </div>
      </form>
    </div>
  </div>

  <div data-tab-panel="builder" class="card p-6 <?= $tab === 'builder' ? '' : 'hidden'; ?>">
    <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
      <div>
        <h2 class="text-lg font-semibold">Editor visual da home</h2>
        <p class="text-sm text-gray-500">Arraste blocos, edite textos e publique a nova página inicial.</p>
      </div>
      <div class="flex gap-2">
        <?php if ($canManageBuilder): ?>
          <button id="btn-preview" class="btn btn-ghost"><i class="fa-solid fa-eye mr-2"></i>Preview</button>
          <button id="btn-save" class="btn btn-ghost"><i class="fa-solid fa-floppy-disk mr-2"></i>Salvar rascunho</button>
          <button id="btn-publish" class="btn btn-primary"><i class="fa-solid fa-rocket mr-2"></i>Publicar</button>
        <?php else: ?>
          <span class="text-xs text-gray-400">Somente leitura</span>
        <?php endif; ?>
      </div>
    </div>
    <div id="builder-alert" class="hidden px-4 py-3 rounded-lg text-sm"></div>
    <div class="border border-gray-200 rounded-xl overflow-hidden">
      <?php if ($canManageBuilder): ?>
        <div id="gjs" style="min-height:600px;background:#f5f5f5;"></div>
      <?php else: ?>
        <div class="p-6 text-sm text-gray-500 bg-white">Você não possui permissão para editar o layout. Solicite a um administrador com acesso.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
</section>

<script>
  document.querySelectorAll('[data-tab-panel]').forEach(panel => {
    panel.classList.toggle('hidden', panel.getAttribute('data-tab-panel') !== '<?= $tab; ?>');
  });
  document.querySelectorAll('[href^="settings.php?tab="]').forEach(link => {
    link.addEventListener('click', function(e){
      if (this.pathname === window.location.pathname && this.search === window.location.search) {
        e.preventDefault();
      }
    });
  });
</script>

<?php if ($tab === 'payments'): ?>
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
  <script>
    const list = document.getElementById("pm-sortable");
    if (list && list.dataset.sortableEnabled === '1') {
      new Sortable(list, {
        animation: 150,
        handle: ".fa-grip-lines",
        onEnd: function(){
          const ids = Array.from(list.querySelectorAll("li[data-id]")).map(el => el.dataset.id);
          fetch("settings.php?action=reorder", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({ ids, csrf: '<?= csrf_token(); ?>' })
          });
        }
      });
    }
    const typeSelect = document.querySelector("select[name=method_type]");
    const groups = document.querySelectorAll("#type-fields [data-type]");
    function toggleTypeFields(){
      const current = typeSelect ? typeSelect.value : 'custom';
      groups.forEach(el => {
        el.style.display = (el.dataset.type === current) ? 'block' : 'none';
      });
    }
    if (typeSelect) {
      typeSelect.addEventListener('change', toggleTypeFields);
    }
    toggleTypeFields();
  </script>
<?php endif; ?>

<?php if ($tab === 'builder' && $canManageBuilder): ?>
  <link rel="stylesheet" href="https://unpkg.com/grapesjs@0.21.6/dist/css/grapes.min.css">
  <script src="https://unpkg.com/grapesjs@0.21.6/dist/grapes.min.js"></script>
  <script src="https://unpkg.com/grapesjs-blocks-basic@0.1.9/dist/grapesjs-blocks-basic.min.js"></script>
  <script>
    const API_URL = 'admin_api_layouts.php';
    const PAGE_SLUG = 'home';
    const CSRF_TOKEN = <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES); ?>;
    const EXISTING_LAYOUT = <?= $layoutJson; ?>;

    function showMessage(msg, type='info') {
      const alertBox = document.getElementById('builder-alert');
      alertBox.textContent = msg;
      alertBox.className = '';
      alertBox.classList.add('px-4','py-3','rounded-lg','text-sm');
      if (type === 'success') alertBox.classList.add('bg-emerald-100','text-emerald-800');
      else if (type === 'warning') alertBox.classList.add('bg-amber-100','text-amber-800');
      else if (type === 'error') alertBox.classList.add('bg-red-100','text-red-800');
      else alertBox.classList.add('bg-gray-100','text-gray-800');
      alertBox.classList.remove('hidden');
      setTimeout(()=>alertBox.classList.add('hidden'), 7000);
    }

    const editor = grapesjs.init({
      container: '#gjs',
      height: '100%','storageManager': false,
      plugins: ['gjs-blocks-basic'],
      pluginsOpts: { 'gjs-blocks-basic': { flexGrid: true } },
      blockManager: { appendTo: '.gjs-pn-blocks-container' },
      selectorManager: { appendTo: '.gjs-sm-sectors' },
      styleManager: {
        appendTo: '.gjs-style-manager',
        sectors: [
          { name: 'Layout', open: true, buildProps: ['display','position','width','height','margin','padding'] },
          { name: 'Tipografia', open: false, buildProps: ['font-family','font-size','font-weight','letter-spacing','color','line-height','text-align'] },
          { name: 'Decoração', open: false, buildProps: ['background-color','background','border-radius','box-shadow'] }
        ]
      },
      canvas: { styles: ['https://cdn.tailwindcss.com'] }
    });

    function addCustomBlocks(){
      const bm = editor.BlockManager;
      bm.add('hero-banner', {
        category: 'Seções',
        label: '<i class="fa-solid fa-image mr-2"></i>Hero Banner',
        content: `
          <section class="hero-section" style="padding:60px 20px;background:linear-gradient(135deg,#dc2626,#f59e0b);color:#fff;text-align:center;">
            <div style="max-width:700px;margin:0 auto;">
              <h1 style="font-size:42px;font-weight:700;margin-bottom:20px;">Título chamativo para sua campanha</h1>
              <p style="font-size:19px;opacity:.9;margin-bottom:30px;">Conte ao cliente o benefício principal da loja e adicione um call-to-action para o produto mais importante.</p>
              <a href="#" class="cta-btn" style="display:inline-block;padding:14px 28px;background:#fff;color:#dc2626;font-weight:600;border-radius:999px;text-decoration:none;">Comprar agora</a>
            </div>
          </section>
        `
      });
      bm.add('product-grid', {
        category: 'Seções',
        label: '<i class="fa-solid fa-table-cells mr-2"></i>Grade de Produtos',
        content: `
          <section style="padding:50px 20px;background:#f9fafb;">
            <div style="max-width:1100px;margin:0 auto;">
              <h2 style="font-size:32px;text-align:center;margin-bottom:24px;">Destaques da semana</h2>
              <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;">
                <div class="product-card" style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 10px 30px rgba(15,23,42,.08);text-align:center;">
                  <div style="height:160px;background:#f1f5f9;border-radius:12px;margin-bottom:16px;"></div>
                  <h3 style="font-size:18px;font-weight:600;margin-bottom:8px;">Nome do Produto</h3>
                  <p style="color:#475569;font-size:14px;margin-bottom:12px;">Descrição breve do produto e benefícios.</p>
                  <strong style="font-size:20px;color:#dc2626;">$ 29,90</strong>
                </div>
                <div class="product-card" style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 10px 30px rgba(15,23,42,.08);text-align:center;">
                  <div style="height:160px;background:#f1f5f9;border-radius:12px;margin-bottom:16px;"></div>
                  <h3 style="font-size:18px;font-weight:600;margin-bottom:8px;">Nome do Produto</h3>
                  <p style="color:#475569;font-size:14px;margin-bottom:12px;">Descrição breve do produto e benefícios.</p>
                  <strong style="font-size:20px;color:#dc2626;">$ 39,90</strong>
                </div>
                <div class="product-card" style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 10px 30px rgba(15,23,42,.08);text-align:center;">
                  <div style="height:160px;background:#f1f5f9;border-radius:12px;margin-bottom:16px;"></div>
                  <h3 style="font-size:18px;font-weight:600;margin-bottom:8px;">Nome do Produto</h3>
                  <p style="color:#475569;font-size:14px;margin-bottom:12px;">Descrição breve do produto e benefícios.</p>
                  <strong style="font-size:20px;color:#dc2626;">$ 19,90</strong>
                </div>
              </div>
            </div>
          </section>
        `
      });
      bm.add('testimonial', {
        category: 'Seções',
        label: '<i class="fa-solid fa-comment-dots mr-2"></i>Depoimentos',
        content: `
          <section style="padding:60px 20px;">
            <div style="max-width:900px;margin:0 auto;text-align:center;">
              <h2 style="font-size:32px;margin-bottom:30px;">O que nossos clientes dizem</h2>
              <div style="display:grid;gap:20px;">
                <blockquote style="background:#fff;border-radius:16px;padding:30px;box-shadow:0 10px 40px rgba(15,23,42,.08);">
                  <p style="font-style:italic;color:#475569;">“Excelente atendimento, entrega rápida e produtos de qualidade. Recomendo muito!”</p>
                  <footer style="margin-top:18px;font-weight:600;">Maria Andrade — Fort Lauderdale</footer>
                </blockquote>
                <blockquote style="background:#fff;border-radius:16px;padding:30px;box-shadow:0 10px 40px rgba(15,23,42,.08);">
                  <p style="font-style:italic;color:#475569;">“A loja online é super intuitiva e o suporte me ajudou rapidamente com minhas dúvidas.”</p>
                  <footer style="margin-top:18px;font-weight:600;">João Silva — Orlando</footer>
                </blockquote>
              </div>
            </div>
          </section>
        `
      });
    }
    addCustomBlocks();

    const DEFAULT_TEMPLATE = `
      <section style="padding:80px 20px;background:linear-gradient(135deg,#dc2626,#f59e0b);color:#fff;text-align:center;">
        <div style="max-width:760px;margin:0 auto;">
          <h1 style="font-size:48px;font-weight:700;margin-bottom:18px;">Tudo para sua saúde em poucos cliques</h1>
          <p style="font-size:20px;opacity:0.92;margin-bottom:28px;">Entrega rápida, atendimento humano e os melhores medicamentos do Brasil para os Estados Unidos.</p>
          <a href="#catalogo" style="display:inline-block;padding:16px 36px;border-radius:999px;background:#fff;color:#dc2626;font-weight:600;text-decoration:none;">Ver catálogo</a>
        </div>
      </section>
      <section id="catalogo" style="padding:60px 20px;background:#f9fafb;">
        <div style="max-width:1100px;margin:0 auto;">
          <h2 style="font-size:34px;font-weight:700;text-align:center;margin-bottom:16px;">Categorias em destaque</h2>
          <p style="text-align:center;color:#475569;margin-bottom:36px;">Escolha a linha de produtos que melhor atende à sua necessidade e receba tudo no conforto da sua casa.</p>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:24px;">
            <div style="background:#fff;border-radius:18px;padding:26px;box-shadow:0 10px 40px rgba(15,23,42,.08);">
              <h3 style="font-size:20px;font-weight:600;margin-bottom:6px;">Medicamentos</h3>
              <p style="color:#64748b;font-size:15px;margin-bottom:14px;">Genéricos, manipulados e medicamentos de alto custo com procedência garantida.</p>
              <a href="?route=home&category=1" style="color:#dc2626;font-weight:600;">Ver produtos →</a>
            </div>
            <div style="background:#fff;border-radius:18px;padding:26px;box-shadow:0 10px 40px rgba(15,23,42,.08);">
              <h3 style="font-size:20px;font-weight:600;margin-bottom:6px;">Suplementos</h3>
              <p style="color:#64748b;font-size:15px;margin-bottom:14px;">Vitaminas, minerais e boosters energéticos selecionados por especialistas.</p>
              <a href="?route=home&category=4" style="color:#dc2626;font-weight:600;">Ver suplementos →</a>
            </div>
            <div style="background:#fff;border-radius:18px;padding:26px;box-shadow:0 10px 40px rgba(15,23,42,.08);">
              <h3 style="font-size:20px;font-weight:600;margin-bottom:6px;">Dermocosméticos</h3>
              <p style="color:#64748b;font-size:15px;margin-bottom:14px;">Tratamentos faciais, linhas anti-idade e cuidados específicos para a pele.</p>
              <a href="?route=home&category=8" style="color:#dc2626;font-weight:600;">Ver dermocosméticos →</a>
            </div>
          </div>
        </div>
      </section>
      <section style="padding:60px 20px;">
        <div style="max-width:960px;margin:0 auto;border-radius:24px;background:linear-gradient(135deg,#22c55e,#14b8a6);padding:50px;color:#fff;">
          <h2 style="font-size:36px;font-weight:700;margin-bottom:18px;">Atendimento humano e entrega garantida</h2>
          <ul style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;font-size:16px;">
            <li>✔️ Pagamentos por Pix, Zelle, Venmo, PayPal ou Square</li>
            <li>✔️ Equipe especializada para auxiliar na compra e prescrição</li>
            <li>✔️ Acompanhamento do pedido em tempo real pelo painel</li>
            <li>✔️ Entregas expressas em todo território norte-americano</li>
          </ul>
        </div>
      </section>
    `;
    const DEFAULT_STYLES = ``;

    function loadDraft(){
      try {
        const data = EXISTING_LAYOUT || {};
        let loaded = false;
        if (data.draft && data.draft.content) {
          editor.setComponents(data.draft.content);
          if (data.draft.styles) editor.setStyle(data.draft.styles);
          loaded = true;
        } else if (data.published && data.published.content) {
          showMessage('Nenhum rascunho encontrado. Carregando versão publicada.', 'warning');
          editor.setComponents(data.published.content);
          if (data.published.styles) editor.setStyle(data.published.styles);
          loaded = true;
        }
        if (!loaded) {
          editor.setComponents(DEFAULT_TEMPLATE);
          editor.setStyle(DEFAULT_STYLES);
          showMessage('Layout padrão carregado. Publique para substituir a home atual.', 'info');
        }
      } catch (err) {
        console.error(err);
        showMessage('Não foi possível carregar o layout: '+err.message, 'error');
        editor.setComponents(DEFAULT_TEMPLATE);
        editor.setStyle(DEFAULT_STYLES);
      }
    }

    function getPayload(){
      return {
        page: PAGE_SLUG,
        content: editor.getHtml({ componentFirst: true }),
        styles: editor.getCss(),
        meta: {
          updated_by: <?= json_encode($_SESSION['admin_email'] ?? 'admin', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
          updated_at: new Date().toISOString()
        },
        csrf: CSRF_TOKEN
      };
    }

    async function saveDraft(){
      showMessage('Salvando rascunho...', 'info');
      const payload = getPayload();
      try {
        const res = await fetch(API_URL+'?action=save', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
          credentials: 'same-origin'
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Erro ao salvar');
        showMessage('Rascunho salvo com sucesso!', 'success');
      } catch (err) {
        showMessage('Erro ao salvar: '+err.message, 'error');
      }
    }

    async function publishDraft(){
      showMessage('Publicando alterações...', 'info');
      await saveDraft();
      try {
        const res = await fetch(API_URL+'?action=publish', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ page: PAGE_SLUG, csrf: CSRF_TOKEN }),
          credentials: 'same-origin'
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Erro ao publicar');
        showMessage('Página publicada! As mudanças já estão na home.', 'success');
      } catch (err) {
        showMessage('Erro ao publicar: '+err.message, 'error');
      }
    }

    function previewDraft(){
      const html = editor.getHtml({ componentFirst: true });
      const css = editor.getCss();
      const win = window.open('', '_blank');
      const doc = win.document;
      doc.open();
      doc.write(`
        <!doctype html>
        <html lang="pt-br">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width,initial-scale=1">
          <title>Preview - Home personalizada</title>
          <style>${css}</style>
        </head>
        <body>${html}</body>
        </html>
      `);
      doc.close();
    }

    document.getElementById('btn-save').addEventListener('click', saveDraft);
    document.getElementById('btn-publish').addEventListener('click', publishDraft);
    document.getElementById('btn-preview').addEventListener('click', previewDraft);

    if (editor.isReady) {
      loadDraft();
    } else {
      editor.on('load', loadDraft);
    }
  </script>
<?php endif; ?>
<?php
admin_footer();
