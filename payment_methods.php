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
      header('Location: admin.php?route=login'); exit;
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
$action = $_GET['action'] ?? 'list';

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
  header('Location: payment_methods.php');
  exit;
}

if ($action === 'delete' && isset($_GET['id'])) {
  if (!csrf_check($_GET['csrf'] ?? '')) die('CSRF');
  $id = (int)$_GET['id'];
  $pdo->prepare('DELETE FROM payment_methods WHERE id=?')->execute([$id]);
  header('Location: payment_methods.php');
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
    $code = $type; // códigos padrão seguem o tipo
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

  // Verifica duplicidade de código
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

  header('Location: payment_methods.php');
  exit;
}

function pm_form(array $row, array $settings) {
  $id = (int)($row['id'] ?? 0);
  $name = sanitize_html($row['name'] ?? '');
  $code = sanitize_html($row['code'] ?? '');
  $description = sanitize_html($row['description'] ?? '');
  $instructions = htmlspecialchars($row['instructions'] ?? '', ENT_QUOTES, 'UTF-8');
  $csrf = csrf_token();
  $type = $settings['type'] ?? ($code ?: 'custom');
  $isDefaultCode = in_array($code, ['pix','zelle','venmo','paypal','square'], true);

  echo '<form class="card p-5 space-y-4" method="post" enctype="multipart/form-data" action="payment_methods.php?action='.
        ($id ? 'update&id='.$id : 'create').'">';
  echo '  <input type="hidden" name="csrf" value="'.$csrf.'">';
  echo '  <div class="grid md:grid-cols-2 gap-4">';
  echo '    <div><label class="block text-sm font-medium mb-1">Nome</label><input class="input w-full" name="name" value="'.$name.'" required></div>';
  echo '    <div><label class="block text-sm font-medium mb-1">Código</label>';
  $codeInputAttrs = $isDefaultCode ? 'readonly' : '';
  echo '      <input class="input w-full" name="code" value="'.$code.'" '.$codeInputAttrs.' placeholder="ex.: square">';
  echo '    </div>';
  echo '    <div><label class="block text-sm font-medium mb-1">Tipo</label>';
  echo '      <select class="select w-full" name="method_type" '.($isDefaultCode?'readonly disabled':'').'>';
  $types = ['pix'=>'Pix','zelle'=>'Zelle','venmo'=>'Venmo','paypal'=>'PayPal','square'=>'Square','custom'=>'Personalizado'];
  $currentType = $isDefaultCode ? $code : ($settings['type'] ?? 'custom');
  foreach ($types as $value => $label) {
    $sel = ($currentType === $value) ? 'selected' : '';
    echo '        <option value="'.$value.'" '.$sel.'>'.$label.'</option>';
  }
  echo '      </select>';
  echo '    </div>';
  echo '    <div><label class="block text-sm font-medium mb-1">Status</label>';
  $checked = !isset($row['is_active']) || (int)$row['is_active'] === 1 ? 'checked' : '';
  echo '      <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" '.$checked.'> Ativo</label>';
  echo '    </div>';
  echo '    <div><label class="block text-sm font-medium mb-1">Solicitar comprovante?</label>';
  $receiptChecked = !empty($row['require_receipt']) ? 'checked' : '';
  echo '      <label class="inline-flex items-center gap-2"><input type="checkbox" name="require_receipt" value="1" '.$receiptChecked.'> Exigir upload no checkout</label>';
  echo '    </div>';
  echo '  </div>';

  echo '  <div><label class="block text-sm font-medium mb-1">Descrição interna</label><input class="input w-full" name="description" value="'.$description.'" placeholder="Visible apenas no painel"></div>';
  echo '  <div><label class="block text-sm font-medium mb-1">Instruções (placeholders: {valor_pedido}, {numero_pedido}, {email_cliente})</label>';
  echo '    <textarea class="textarea w-full" name="instructions" rows="4">'.$instructions.'</textarea></div>';

  echo '  <div class="grid md:grid-cols-2 gap-4">';
  echo '    <div><label class="block text-sm font-medium mb-1">Legenda do campo</label><input class="input w-full" name="account_label" value="'.sanitize_html($settings['account_label'] ?? '').'" placeholder="Ex.: Chave Pix"></div>';
  echo '    <div><label class="block text-sm font-medium mb-1">Valor/Conta</label><input class="input w-full" name="account_value" value="'.sanitize_html($settings['account_value'] ?? '').'" placeholder="Dados exibidos ao cliente"></div>';
  echo '  </div>';

  echo '  <div class="grid md:grid-cols-2 gap-4">';
  echo '    <div><label class="block text-sm font-medium mb-1">Cor do botão</label><input class="input w-full" name="button_bg" value="'.sanitize_html($settings['button_bg'] ?? '#dc2626').'" type="color"></div>';
  echo '    <div><label class="block text-sm font-medium mb-1">Cor do texto</label><input class="input w-full" name="button_text" value="'.sanitize_html($settings['button_text'] ?? '#ffffff').'" type="color"></div>';
  echo '    <div><label class="block text-sm font-medium mb-1">Cor ao hover</label><input class="input w-full" name="button_hover_bg" value="'.sanitize_html($settings['button_hover_bg'] ?? '#b91c1c').'" type="color"></div>';
  echo '  </div>';

  echo '  <div class="grid md:grid-cols-2 gap-4" id="type-fields">';
  echo '    <div data-type="pix"><label class="block text-sm font-medium mb-1">Chave Pix</label><input class="input w-full" name="pix_key" value="'.sanitize_html($settings['pix_key'] ?? '').'"></div>';
  echo '    <div data-type="pix"><label class="block text-sm font-medium mb-1">Nome do recebedor</label><input class="input w-full" name="pix_merchant_name" value="'.sanitize_html($settings['merchant_name'] ?? '').'"></div>';
  echo '    <div data-type="pix"><label class="block text-sm font-medium mb-1">Cidade</label><input class="input w-full" name="pix_merchant_city" value="'.sanitize_html($settings['merchant_city'] ?? '').'"></div>';

  echo '    <div data-type="zelle"><label class="block text-sm font-medium mb-1">Nome do recebedor</label><input class="input w-full" name="zelle_recipient_name" value="'.sanitize_html($settings['recipient_name'] ?? '').'"></div>';

  echo '    <div data-type="venmo"><label class="block text-sm font-medium mb-1">Link/Usuário do Venmo</label><input class="input w-full" name="venmo_link" value="'.sanitize_html($settings['venmo_link'] ?? '').'"></div>';

  echo '    <div data-type="paypal"><label class="block text-sm font-medium mb-1">Conta PayPal / Email</label><input class="input w-full" name="paypal_business" value="'.sanitize_html($settings['business'] ?? '').'"></div>';
  echo '    <div data-type="paypal"><label class="block text-sm font-medium mb-1">Moeda</label><input class="input w-full" name="paypal_currency" value="'.sanitize_html($settings['currency'] ?? 'USD').'"></div>';
  echo '    <div data-type="paypal"><label class="block text-sm font-medium mb-1">Return URL</label><input class="input w-full" name="paypal_return_url" value="'.sanitize_html($settings['return_url'] ?? '').'"></div>';
  echo '    <div data-type="paypal"><label class="block text-sm font-medium mb-1">Cancel URL</label><input class="input w-full" name="paypal_cancel_url" value="'.sanitize_html($settings['cancel_url'] ?? '').'"></div>';

  echo '    <div data-type="square"><label class="block text-sm font-medium mb-1">Modo</label><select class="select w-full" name="square_mode">';
  $squareMode = $settings['mode'] ?? 'square_product_link';
  $modes = ['square_product_link'=>'Link configurado por produto','direct_url'=>'URL fixa'];
  foreach ($modes as $value => $label) {
    $sel = ($squareMode === $value) ? 'selected' : '';
    echo '<option value="'.$value.'" '.$sel.'>'.$label.'</option>';
  }
  echo '</select></div>';
  $openNewTab = !empty($settings['open_new_tab']) ? 'checked' : '';
  echo '    <div data-type="square"><label class="block text-sm font-medium mb-1">Nova aba?</label><label class="inline-flex items-center gap-2"><input type="checkbox" name="square_open_new_tab" value="1" '.$openNewTab.'> Abrir em nova aba</label></div>';
  echo '    <div data-type="square"><label class="block text-sm font-medium mb-1">URL fixa (opcional)</label><input class="input w-full" name="square_redirect_url" value="'.sanitize_html($settings['redirect_url'] ?? '').'" placeholder="https://"></div>';

  echo '    <div data-type="custom"><label class="block text-sm font-medium mb-1">Modo</label><input class="input w-full" name="custom_mode" value="'.sanitize_html($settings['mode'] ?? 'manual').'"></div>';
  echo '    <div data-type="custom"><label class="block text-sm font-medium mb-1">URL de redirecionamento</label><input class="input w-full" name="custom_redirect_url" value="'.sanitize_html($settings['redirect_url'] ?? '').'"></div>';
  echo '  </div>';

  echo '  <div><label class="block text-sm font-medium mb-1">Ícone (PNG/SVG opcional)</label><input type="file" name="icon" accept="image/png,image/jpeg,image/webp,image/svg+xml"></div>';
  if (!empty($row['icon_path'])) {
    $icon = sanitize_html($row['icon_path']);
    echo '<div><img src="'.$icon.'" alt="ícone" class="h-10"></div>';
  }

  echo '  <div class="pt-3 flex items-center gap-2">';
  echo '    <button class="btn btn-primary px-4 py-2"><i class="fa-solid fa-floppy-disk mr-2"></i>Salvar</button>';
  echo '    <a class="btn btn-ghost px-4 py-2" href="payment_methods.php">Cancelar</a>';
  echo '  </div>';
  echo '</form>';

  echo '<script>
    const typeSelect = document.querySelector("select[name=method_type]");
    const fieldGroups = document.querySelectorAll("#type-fields [data-type]");
    function toggleFields(){
      const t = typeSelect ? typeSelect.value : "custom";
      fieldGroups.forEach(el => {
        el.style.display = (el.dataset.type === t) ? "block" : "none";
      });
    }
    if (typeSelect) {
      typeSelect.addEventListener("change", toggleFields);
      toggleFields();
    }
  </script>';
}

if ($action === 'new') {
  admin_header('Novo método de pagamento');
  echo '<div class="card mb-4"><div class="card-title p-4 border-b">Cadastrar método</div><div class="p-4">';
  pm_form([], []);
  echo '</div></div>';
  admin_footer();
  exit;
}

if ($action === 'edit' && isset($_GET['id'])) {
  $id = (int)$_GET['id'];
  $st = $pdo->prepare('SELECT * FROM payment_methods WHERE id=?');
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) { header('Location: payment_methods.php'); exit; }
  $settings = pm_decode_settings($row);
  admin_header('Editar método: '.sanitize_html($row['name'] ?? '')); 
  echo '<div class="card mb-4"><div class="card-title p-4 border-b">Editar método</div><div class="p-4">';
  pm_form($row, $settings);
  echo '</div></div>';
  admin_footer();
  exit;
}

// LISTAGEM
$methods = $pdo->query('SELECT * FROM payment_methods ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);

admin_header('Formas de pagamento');

echo '<div class="card mb-4"><div class="card-title p-4 border-b flex items-center justify-between"><span>Métodos de pagamento</span><a class="btn btn-primary px-4 py-2" href="payment_methods.php?action=new"><i class="fa-solid fa-plus mr-2"></i>Novo</a></div>';
if (!$methods) {
  echo '<div class="p-6 text-center text-gray-500">Nenhum método cadastrado.</div>';
} else {
  echo '<div class="p-0">
    <div class="px-4 py-3 text-sm text-gray-500">Arraste os itens para reordenar. Status e comprovante são exibidos na listagem.</div>
    <ul id="pm-sortable" class="divide-y divide-gray-200">';
  foreach ($methods as $pm) {
    $settings = pm_decode_settings($pm);
    $badge = ((int)$pm['is_active'] === 1) ? '<span class="badge ok">Ativo</span>' : '<span class="badge danger">Inativo</span>';
    $receipt = !empty($pm['require_receipt']) ? '<span class="badge warning">Comprovante</span>' : '';
    $iconTag = '';
    if (!empty($pm['icon_path'])) {
      $iconPath = sanitize_html($pm['icon_path']);
      $iconTag = '<img src="'.$iconPath.'" class="h-8 w-8 rounded mr-3" alt="icon">';
    }
    echo '<li class="flex items-center justify-between gap-4 px-4 py-3 bg-white" data-id="'.(int)$pm['id'].'">
      <div class="flex items-center gap-3">
        <span class="cursor-move text-gray-400"><i class="fa-solid fa-grip-lines"></i></span>
        '.$iconTag.'
        <div>
          <div class="font-semibold">'.sanitize_html($pm['name']).'</div>
          <div class="text-xs text-gray-500">Código: '.sanitize_html($pm['code']).'</div>
        </div>
      </div>
      <div class="flex items-center gap-3">'.$badge.$receipt.'</div>
      <div class="flex items-center gap-2">
        <a class="btn btn-ghost" href="payment_methods.php?action=edit&id='.(int)$pm['id'].'"><i class="fa-solid fa-pen"></i></a>
        <a class="btn btn-ghost" href="payment_methods.php?action=toggle&id='.(int)$pm['id'].'&csrf='.csrf_token().'"><i class="fa-solid fa-power-off"></i></a>
        <a class="btn btn-ghost text-red-600" href="payment_methods.php?action=delete&id='.(int)$pm['id'].'&csrf='.csrf_token().'" onclick="return confirm(\'Remover este método?\')"><i class="fa-solid fa-trash"></i></a>
      </div>
    </li>';
  }
  echo '</ul></div>';
}

echo '</div>';

echo '<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>';
echo '<script>
  const list = document.getElementById("pm-sortable");
  if (list) {
    new Sortable(list, {
      animation: 150,
      handle: ".fa-grip-lines",
      onEnd: function(){
        const ids = Array.from(list.querySelectorAll("li[data-id]")).map(el => el.dataset.id);
        fetch("payment_methods.php?action=reorder", {
          method: "POST",
          headers: {"Content-Type": "application/json"},
          body: JSON.stringify({ ids, csrf: '.json_encode(csrf_token()).' })
        });
      }
    });
  }
</script>';

admin_footer();
