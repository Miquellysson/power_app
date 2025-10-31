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

function pm_sanitize($value, $max = 255) {
  $value = trim((string)$value);
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
  $id = (int)$_GET['id'];
  $pdo->prepare('UPDATE payment_methods SET is_active = IF(is_active=1,0,1) WHERE id=?')->execute([$id]);
  header('Location: settings.php?tab=payments');
  exit;
}

if ($action === 'delete' && isset($_GET['id'])) {
  if (!csrf_check($_GET['csrf'] ?? '')) die('CSRF');
  $id = (int)$_GET['id'];
  $pdo->prepare('DELETE FROM payment_methods WHERE id=?')->execute([$id]);
  header('Location: settings.php?tab=payments');
  exit;
}

if (($action === 'create' || $action === 'update') && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');

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

$cards = [
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
  <div class="card p-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-2xl font-bold mb-1">Configurações da Plataforma</h1>
        <p class="text-sm text-gray-500">Personalize as informações da loja, pagamentos e aparência da vitrine.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a href="index.php" target="_blank" class="btn btn-ghost"><i class="fa-solid fa-up-right-from-square"></i><span>Ver loja</span></a>
        <a href="settings.php?tab=payments" class="btn btn-ghost"><i class="fa-solid fa-credit-card"></i><span>Pagamentos</span></a>
        <a href="settings.php?tab=builder" class="btn btn-ghost"><i class="fa-solid fa-paintbrush"></i><span>Editor da Home</span></a>
      </div>
    </div>
  </div>

  <div class="grid gap-4 md:grid-cols-3">
    <?php foreach ($cards as $card): $active = ($tab === $card['key']); ?>
      <a href="settings.php?tab=<?= $card['key']; ?>" class="card p-4 flex items-start gap-3 border <?= $active ? 'border-brand-500 shadow-lg' : 'border-transparent'; ?>">
        <div class="text-brand-700 text-xl"><i class="fa-solid <?= $card['icon']; ?>"></i></div>
        <div>
          <h2 class="text-lg font-semibold mb-1"><?= sanitize_html($card['title']); ?></h2>
          <p class="text-sm text-gray-500"><?= sanitize_html($card['description']); ?></p>
          <?php if ($active): ?>
            <span class="text-xs font-semibold text-brand-600">ABERTO</span>
          <?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <div data-tab-panel="general" class="card p-6 <?= $tab === 'general' ? '' : 'hidden'; ?>">
    <h2 class="text-lg font-semibold mb-3">Informações da Loja</h2>
    <form class="grid md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Nome da loja</label>
        <input class="input w-full" placeholder="Farma Fácil" value="<?= sanitize_html(setting_get('store_name', cfg()['store']['name'] ?? '')); ?>" disabled>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">E-mail de suporte</label>
        <input class="input w-full" placeholder="contato@" value="<?= sanitize_html(setting_get('store_email', cfg()['store']['support_email'] ?? '')); ?>" disabled>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Telefone</label>
        <input class="input w-full" placeholder="(82) 99999-9999" value="<?= sanitize_html(setting_get('store_phone', cfg()['store']['phone'] ?? '')); ?>" disabled>
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Endereço</label>
        <textarea class="textarea w-full" rows="2" disabled><?= sanitize_html(setting_get('store_address', cfg()['store']['address'] ?? '')); ?></textarea>
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Logo atual</label>
        <?php $logo = get_logo_path(); if ($logo): ?>
          <img src="<?= sanitize_html($logo); ?>" alt="Logo" class="h-16">
        <?php else: ?>
          <p class="text-sm text-gray-500">Sem logo configurada.</p>
        <?php endif; ?>
      </div>
      <p class="text-sm text-gray-500 md:col-span-2">Os dados acima são apenas leitura nesta tela. Atualize-os via painel de administração original ou implemente aqui conforme necessidade.</p>
    </form>
  </div>

  <div data-tab-panel="payments" class="<?= $tab === 'payments' ? '' : 'hidden'; ?> space-y-4">
    <div class="card p-6">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h2 class="text-lg font-semibold">Métodos de pagamento</h2>
          <p class="text-sm text-gray-500">Arraste para reordenar e clique para editar ou ativar/desativar.</p>
        </div>
        <a class="btn btn-primary" href="settings.php?tab=payments&action=new"><i class="fa-solid fa-plus mr-2"></i>Novo método</a>
      </div>
      <?php if (!$methods): ?>
        <p class="text-center text-gray-500 mt-6">Nenhum método cadastrado.</p>
      <?php else: ?>
        <ul id="pm-sortable" class="divide-y divide-gray-200 mt-4">
          <?php foreach ($methods as $pm): $settings = pm_decode_settings($pm); ?>
            <li class="flex items-center justify-between gap-4 px-4 py-3 bg-white" data-id="<?= (int)$pm['id']; ?>">
              <div class="flex items-center gap-3">
                <span class="cursor-move text-gray-400"><i class="fa-solid fa-grip-lines"></i></span>
                <?php if (!empty($pm['icon_path'])): ?>
                  <img src="<?= sanitize_html($pm['icon_path']); ?>" class="h-8 w-8 rounded" alt="icon">
                <?php else: ?>
                  <div class="h-8 w-8 rounded bg-brand-100 text-brand-700 flex items-center justify-center">
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
                <?= !empty($pm['require_receipt']) ? '<span class="badge warning">Comprovante</span>' : ''; ?>
              </div>
              <div class="flex items-center gap-2">
                <a class="btn btn-ghost" href="settings.php?tab=payments&action=edit&id=<?= (int)$pm['id']; ?>"><i class="fa-solid fa-pen"></i></a>
                <a class="btn btn-ghost" href="settings.php?tab=payments&action=toggle&id=<?= (int)$pm['id']; ?>&csrf=<?= csrf_token(); ?>"><i class="fa-solid fa-power-off"></i></a>
                <a class="btn btn-ghost text-red-600" href="settings.php?tab=payments&action=delete&id=<?= (int)$pm['id']; ?>&csrf=<?= csrf_token(); ?>" onclick="return confirm('Remover este método?')"><i class="fa-solid fa-trash"></i></a>
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
        <div class="grid md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Nome</label>
            <input class="input w-full" name="name" value="<?= sanitize_html($formRow['name'] ?? ''); ?>" required>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Código</label>
            <?php $isDefaultCode = in_array($formRow['code'] ?? '', ['pix','zelle','venmo','paypal','square'], true); ?>
            <input class="input w-full" name="code" value="<?= sanitize_html($formRow['code'] ?? ''); ?>" <?= $isDefaultCode ? 'readonly' : ''; ?> placeholder="ex.: square">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Tipo</label>
            <?php $currentType = $isDefaultCode ? ($formRow['code'] ?? 'custom') : ($formSettings['type'] ?? 'custom'); ?>
            <select class="select w-full" name="method_type" <?= $isDefaultCode ? 'disabled' : ''; ?>>
              <?php $types = ['pix'=>'Pix','zelle'=>'Zelle','venmo'=>'Venmo','paypal'=>'PayPal','square'=>'Square','custom'=>'Personalizado']; ?>
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
          <label class="block text-sm font-medium mb-1">Instruções (placeholders: {valor_pedido}, {numero_pedido}, {email_cliente})</label>
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

        <div class="flex items-center gap-2 pt-2">
          <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk mr-2"></i>Salvar</button>
          <a class="btn btn-ghost" href="settings.php?tab=payments">Cancelar</a>
        </div>
      </form>
    </div>
  </div>

  <div data-tab-panel="builder" class="<?= $tab === 'builder' ? '' : 'hidden'; ?> card p-6">
    <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
      <div>
        <h2 class="text-lg font-semibold">Editor visual da home</h2>
        <p class="text-sm text-gray-500">Arraste blocos, edite textos e publique a nova página inicial.</p>
      </div>
      <div class="flex gap-2">
        <button id="btn-preview" class="btn btn-ghost"><i class="fa-solid fa-eye mr-2"></i>Preview</button>
        <button id="btn-save" class="btn btn-ghost"><i class="fa-solid fa-floppy-disk mr-2"></i>Salvar rascunho</button>
        <button id="btn-publish" class="btn btn-primary"><i class="fa-solid fa-rocket mr-2"></i>Publicar</button>
      </div>
    </div>
    <div id="builder-alert" class="hidden px-4 py-3 rounded-lg text-sm"></div>
    <div class="border border-gray-200 rounded-xl overflow-hidden">
      <div id="gjs" style="min-height:600px;background:#f5f5f5;"></div>
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
    if (list) {
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

<?php if ($tab === 'builder'): ?>
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
