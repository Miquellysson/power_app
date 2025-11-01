<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require __DIR__.'/bootstrap.php';
require __DIR__.'/config.php';
require __DIR__.'/lib/db.php';
require __DIR__.'/lib/utils.php';
require __DIR__.'/admin_layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!function_exists('require_admin')){
  function require_admin(){
    if (empty($_SESSION['admin_id'])) {
      header('Location: admin.php?route=login'); exit;
    }
  }
}
if (!function_exists('csrf_token')){
  function csrf_token(){ if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
}
if (!function_exists('csrf_check')){
  function csrf_check($t){ $t=(string)$t; return !empty($t) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t); }
}
if (!function_exists('sanitize_string')){
  function sanitize_string($s,$max=255){ $s=trim((string)$s); if (strlen($s)>$max) $s=substr($s,0,$max); return $s; }
}
if (!function_exists('sanitize_html')){
  function sanitize_html($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('validate_email')){
  function validate_email($e){ return (bool)filter_var($e,FILTER_VALIDATE_EMAIL); }
}

$pdo = db();
require_admin();

ensure_products_schema($pdo);

$action = $_GET['action'] ?? 'list';
$canManageProducts = admin_can('manage_products');
$writeActions = ['new','create','edit','update','delete','destroy','bulk_destroy','import'];
if (!$canManageProducts && in_array($action, $writeActions, true)) {
  require_admin_capability('manage_products');
}

$isSuperAdmin = is_super_admin();

/* ========= Helpers ========= */

function products_flash(string $type, string $message): void {
  $_SESSION['products_flash'] = ['type' => $type, 'message' => $message];
}

function products_take_flash(): ?array {
  $flash = $_SESSION['products_flash'] ?? null;
  unset($_SESSION['products_flash']);
  return $flash;
}

function ensure_products_schema(PDO $pdo): void {
  static $checked = false;
  if ($checked) {
    return;
  }
  $checked = true;
  try {
    $cols = $pdo->query("SHOW COLUMNS FROM products");
    $hasPriceCompare = false;
    if ($cols) {
      while ($col = $cols->fetch(PDO::FETCH_ASSOC)) {
        if (isset($col['Field']) && $col['Field'] === 'price_compare') {
          $hasPriceCompare = true;
          break;
        }
      }
    }
    if (!$hasPriceCompare) {
      $pdo->exec("ALTER TABLE products ADD COLUMN price_compare DECIMAL(10,2) NULL AFTER price");
    }
  } catch (Throwable $e) {
    // Ignora: se a coluna já existir ou permissão negada, apenas seguimos sem interromper
  }
}

function categories_options($pdo, $current=0){
  $opts='';
  try{
    $st=$pdo->query("SELECT id,name FROM categories WHERE active=1 ORDER BY sort_order, name");
    foreach($st as $c){
      $sel = ($current==(int)$c['id'])?'selected':'';
      $opts.='<option value="'.(int)$c['id'].'" '.$sel.'>'.sanitize_html($c['name']).'</option>';
    }
  }catch(Throwable $e){}
  return $opts;
}

/** Verifica se um SKU já existe (ignorando um ID opcional) */
function sku_exists(PDO $pdo, string $sku, ?int $ignoreId = null): bool {
  if ($ignoreId) {
    $st = $pdo->prepare("SELECT id FROM products WHERE sku = ? AND id <> ? LIMIT 1");
    $st->execute([$sku, $ignoreId]);
  } else {
    $st = $pdo->prepare("SELECT id FROM products WHERE sku = ? LIMIT 1");
    $st->execute([$sku]);
  }
  return (bool) $st->fetchColumn();
}

/** Valida e normaliza uma URL do Square (aceita subdomínios válidos). */
function normalize_square_link(string $input): array {
  $url = trim($input);
  if ($url === '') {
    return [true, '', null];
  }
  if (strlen($url) > 255) {
    return [false, $url, 'O link do Square deve ter até 255 caracteres.'];
  }
  if (!filter_var($url, FILTER_VALIDATE_URL)) {
    return [false, $url, 'Informe uma URL válida do Square.'];
  }
  $parts = parse_url($url);
  $scheme = strtolower($parts['scheme'] ?? '');
  if ($scheme !== 'https') {
    return [false, $url, 'O link do Square deve começar com https://'];
  }
  $host = strtolower($parts['host'] ?? '');
  $allowed = ['square.link', 'checkout.square.site', 'squareup.com'];
  $match = false;
  foreach ($allowed as $domain) {
    if ($host === $domain) { $match = true; break; }
    if (substr($host, -strlen('.'.$domain)) === '.'.$domain) { $match = true; break; }
  }
  if (!$match) {
    return [false, $url, 'Domínio não permitido. Use links square.link, checkout.square.site ou squareup.com.'];
  }
  return [true, $url, null];
}

function normalize_stripe_link(string $input): array {
  $url = trim($input);
  if ($url === '') {
    return [true, '', null];
  }
  if (strlen($url) > 255) {
    return [false, $url, 'O link do Stripe deve ter até 255 caracteres.'];
  }
  if (!filter_var($url, FILTER_VALIDATE_URL)) {
    return [false, $url, 'Informe uma URL válida do Stripe.'];
  }
  $parts = parse_url($url);
  $scheme = strtolower($parts['scheme'] ?? '');
  if ($scheme !== 'https') {
    return [false, $url, 'O link do Stripe deve começar com https://'];
  }
  $host = strtolower($parts['host'] ?? '');
  $allowed = ['buy.stripe.com', 'checkout.stripe.com'];
  $match = false;
  foreach ($allowed as $domain) {
    if ($host === $domain) { $match = true; break; }
    if (substr($host, -strlen('.'.$domain)) === '.'.$domain) { $match = true; break; }
  }
  if (!$match) {
    return [false, $url, 'Domínio não permitido. Use links buy.stripe.com ou checkout.stripe.com.'];
  }
  return [true, $url, null];
}

function parse_decimal_value($raw, ?float $default = null): ?float {
  $value = trim((string)$raw);
  if ($value === '') {
    return $default;
  }
  $value = str_replace(["\xc2\xa0", ' '], '', $value);
  $comma = strrpos($value, ',');
  $dot   = strrpos($value, '.');
  if ($comma !== false && $dot !== false) {
    if ($comma > $dot) {
      $value = str_replace('.', '', $value);
      $value = str_replace(',', '.', $value);
    } else {
      $value = str_replace(',', '', $value);
    }
  } elseif ($comma !== false) {
    $value = str_replace(',', '.', $value);
  }
  if (!is_numeric($value)) {
    return null;
  }
  return (float)$value;
}

/** Formulário de produto (reutilizável) */
function product_form($row){
  $id = (int)($row['id'] ?? 0);
  $name = sanitize_html($row['name'] ?? '');
  $sku = sanitize_html($row['sku'] ?? '');
  $price = number_format((float)($row['price'] ?? 0), 2, '.', '');
  $priceCompareRaw = $row['price_compare'] ?? null;
  if ($priceCompareRaw === null || $priceCompareRaw === '') {
    $priceCompare = '';
  } else {
    $priceCompare = number_format((float)$priceCompareRaw, 2, '.', '');
  }
  $shippingCost = number_format((float)($row['shipping_cost'] ?? 7.00), 2, '.', '');
  $stock = (int)($row['stock'] ?? 0);
  $category_id = (int)($row['category_id'] ?? 0);
  $desc = sanitize_html($row['description'] ?? '');
  $active = (int)($row['active'] ?? 1);
  $featured = (int)($row['featured'] ?? 0);
  $img = sanitize_html($row['image_path'] ?? '');
  $square_link = sanitize_html($row['square_payment_link'] ?? '');
  $stripe_link = sanitize_html($row['stripe_payment_link'] ?? '');
  $csrf = csrf_token();

  echo '<form class="p-4 space-y-3" method="post" enctype="multipart/form-data" action="products.php?action='.($id?'update&id='.$id:'create').'">';
  echo '  <input type="hidden" name="csrf" value="'.$csrf.'">';
  echo '  <div class="grid md:grid-cols-2 gap-3">';
  echo '    <div class="field"><span>Nome</span><input class="input" name="name" value="'.$name.'" required></div>';
  echo '    <div class="field"><span>SKU</span><input class="input" name="sku" value="'.$sku.'" required></div>';
  echo '    <div class="field"><span>Preço original (De)</span><input class="input" name="price_compare" type="number" step="0.01" value="'.$priceCompare.'" placeholder="Ex.: 59.90">';
  echo '      <p class="text-xs text-gray-500 mt-1">Deixe vazio para ocultar a faixa “de”.</p></div>';
  echo '    <div class="field"><span>Preço atual (Por)</span><input class="input" name="price" type="number" step="0.01" value="'.$price.'" required>';
  echo '      <p class="text-xs text-gray-500 mt-1">Valor final cobrado do cliente.</p></div>';
  echo '    <div class="field"><span>Frete (US$)</span><input class="input" name="shipping_cost" type="number" step="0.01" value="'.$shippingCost.'" placeholder="7.00"></div>';
  echo '    <div class="field"><span>Estoque</span><input class="input" name="stock" type="number" value="'.$stock.'" required></div>';
  echo '    <div class="field"><span>Categoria</span><select class="select" name="category_id">'.categories_options($GLOBALS["pdo"], $category_id).'</select></div>';
  echo '    <div class="field"><span>Ativo</span><select class="select" name="active"><option value="1" '.($active? 'selected':'').'>Sim</option><option value="0" '.(!$active? 'selected':'').'>Não</option></select></div>';
  echo '    <div class="field"><span>Destaque</span><select class="select" name="featured"><option value="0" '.(!$featured? 'selected':'').'>Não</option><option value="1" '.($featured? 'selected':'').'>Sim</option></select></div>';
  echo '    <div class="field md:col-span-2"><span>Link de Pagamento Square</span>';
  echo '      <input class="input" type="url" name="square_payment_link" value="'.$square_link.'" placeholder="https://square.link/u/xxxx">';
  echo '      <p class="text-xs text-gray-500 mt-1">Cole aqui o link gerado no Square (aceita square.link, checkout.square.site ou squareup.com).</p>';
  if ($square_link) {
    echo '      <p class="text-xs mt-1"><a class="text-brand-600 underline" href="'.$square_link.'" target="_blank" rel="noopener">Testar link</a></p>';
  }
  echo '    </div>';
  echo '    <div class="field md:col-span-2"><span>Link de Pagamento Stripe</span>';
  echo '      <input class="input" type="url" name="stripe_payment_link" value="'.$stripe_link.'" placeholder="https://buy.stripe.com/...">';
  echo '      <p class="text-xs text-gray-500 mt-1">Cole aqui o link de pagamento do Stripe (aceita buy.stripe.com e checkout.stripe.com).</p>';
  if ($stripe_link) {
    echo '      <p class="text-xs mt-1"><a class="text-brand-600 underline" href="'.$stripe_link.'" target="_blank" rel="noopener">Testar link</a></p>';
  }
  echo '    </div>';
  echo '  </div>';
  echo '  <div class="field"><span>Descrição</span><textarea class="textarea" name="description" rows="4">'.$desc.'</textarea></div>';
  echo '  <div class="field"><span>Imagem do produto (JPG/PNG/WEBP)</span><input class="input" type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></div>';
  if ($img) echo '<div class="p-2"><img src="'.sanitize_html($img).'" alt="img" style="max-height:100px;border-radius:8px"></div>';
  echo '  <div class="pt-2"><button class="btn alt" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salvar</button> <a class="btn" href="products.php"><i class="fa-solid fa-arrow-left"></i> Voltar</a></div>';
  echo '</form>';
}

/* ========= Actions ========= */

if ($action==='export') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="produtos-'.date('Ymd-His').'.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['sku','name','price','price_compare','shipping_cost','stock','category_id','description','image_path','square_payment_link','stripe_payment_link','active']);
  $stmt = $pdo->query("SELECT sku,name,price,price_compare,shipping_cost,stock,category_id,description,image_path,square_payment_link,stripe_payment_link,active FROM products ORDER BY id ASC");
  foreach ($stmt as $row) {
    fputcsv($out, [
      $row['sku'],
      $row['name'],
      number_format((float)$row['price'], 2, '.', ''),
      $row['price_compare'] !== null ? number_format((float)$row['price_compare'], 2, '.', '') : '',
      number_format((float)($row['shipping_cost'] ?? 7), 2, '.', ''),
      (int)$row['stock'],
      $row['category_id'],
      $row['description'],
      $row['image_path'],
      $row['square_payment_link'],
      $row['stripe_payment_link'],
      (int)$row['active']
    ]);
  }
  fclose($out);
  exit;
}

if ($action==='import') {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
    if (empty($_FILES['csv']['name']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
      products_flash('error', 'Selecione um arquivo CSV válido.');
      header('Location: products.php?action=import'); exit;
    }
    $tmp = $_FILES['csv']['tmp_name'];
    $handle = fopen($tmp, 'r');
    if (!$handle) {
      products_flash('error', 'Não foi possível ler o arquivo enviado.');
      header('Location: products.php?action=import'); exit;
    }
    $delimiter = ';';
    $header = fgetcsv($handle, 0, $delimiter);
    if ($header && count($header) < 2) {
      rewind($handle);
      $delimiter = ',';
      $header = fgetcsv($handle, 0, $delimiter);
    }
    if (!$header) {
      fclose($handle);
      products_flash('error', 'Arquivo CSV vazio ou inválido.');
      header('Location: products.php?action=import'); exit;
    }
    $headerLower = array_map(fn($v) => strtolower(trim($v)), $header);
    $headerMap = array_flip($headerLower);
    $hasPriceCompare = isset($headerMap['price_compare']);
    foreach (['sku','name','price','stock'] as $required) {
      if (!isset($headerMap[$required])) {
        fclose($handle);
        products_flash('error', 'Cabeçalho inválido. Campos obrigatórios: sku, name, price, stock.');
        header('Location: products.php?action=import'); exit;
      }
    }
    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];
    $line = 1;
    $selectSku = $pdo->prepare("SELECT * FROM products WHERE sku = ? LIMIT 1");
    $updateStmt = $pdo->prepare("UPDATE products SET name=?, sku=?, price=?, price_compare=?, shipping_cost=?, stock=?, category_id=?, description=?, active=?, featured=?, image_path=?, square_payment_link=?, stripe_payment_link=? WHERE id=?");
    $insertStmt = $pdo->prepare("INSERT INTO products(name,sku,price,price_compare,shipping_cost,stock,category_id,description,active,featured,image_path,square_payment_link,stripe_payment_link,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
    $categoryIds = [];
    try {
      $categoryIds = $pdo->query("SELECT id FROM categories")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
      $categoryIds = [];
    }
    $categoryIds = array_map('intval', $categoryIds);
    $pdo->beginTransaction();
    try {
      while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $line++;
        if (count($row) === 1 && trim($row[0]) === '') {
          continue;
        }
        $data = [];
        foreach ($headerLower as $idx => $col) {
          $data[$col] = trim((string)($row[$idx] ?? ''));
        }
        $sku = $data['sku'] ?? '';
        if ($sku === '') {
          $errors[] = "Linha {$line}: SKU vazio, registro ignorado.";
          $skipped++;
          continue;
        }
        $name = $data['name'] ?? '';
        if ($name === '') {
          $errors[] = "Linha {$line}: Nome vazio para o SKU {$sku}.";
          $skipped++;
          continue;
        }
        $price = parse_decimal_value($data['price'] ?? '', null);
        if ($price === null) {
          $errors[] = "Linha {$line}: Preço inválido para o SKU {$sku}.";
          $skipped++;
          continue;
        }
        $priceCompare = null;
        if ($hasPriceCompare) {
          $priceCompare = parse_decimal_value($data['price_compare'] ?? '', null);
          if ($priceCompare !== null && $priceCompare < 0) {
            $priceCompare = null;
          }
        }
        $shippingCost = parse_decimal_value($data['shipping_cost'] ?? '', 7.0);
        if ($shippingCost === null) {
          $shippingCost = 7.0;
        }
        $shippingCost = max(0, $shippingCost);
        $stock = (int)($data['stock'] ?? 0);
        $categoryId = null;
        if (isset($data['category_id']) && $data['category_id'] !== '') {
          $categoryId = (int)$data['category_id'];
          if ($categoryId < 0) $categoryId = null;
          if ($categoryId !== null && !in_array($categoryId, $categoryIds, true)) {
            $errors[] = "Linha {$line}: Categoria {$categoryId} não encontrada. Valor ajustado para vazio.";
            $categoryId = null;
          }
        }
        $description = $data['description'] ?? '';
        $imagePath = $data['image_path'] ?? null;
        $squareInput = $data['square_payment_link'] ?? '';
        [$squareOk, $squareLink, $squareError] = normalize_square_link($squareInput);
        if (!$squareOk) {
          $errors[] = "Linha {$line}: {$squareError}";
          $skipped++;
          continue;
        }
        $stripeInput = $data['stripe_payment_link'] ?? '';
        [$stripeOk, $stripeLink, $stripeError] = normalize_stripe_link($stripeInput);
        if (!$stripeOk) {
          $errors[] = "Linha {$line}: {$stripeError}";
          $skipped++;
          continue;
        }
        $active = isset($data['active']) ? (int)$data['active'] : 1;
        if ($active !== 0) $active = 1;
        $selectSku->execute([$sku]);
        $existing = $selectSku->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
          $featured = (int)($existing['featured'] ?? 0);
          $imgToUse = ($imagePath !== null && $imagePath !== '') ? $imagePath : ($existing['image_path'] ?? null);
          $descToUse = $description !== '' ? $description : ($existing['description'] ?? '');
          $categoryToUse = $categoryId ?? ($existing['category_id'] ?? null);
          if ($categoryToUse !== null && !in_array((int)$categoryToUse, $categoryIds, true)) {
            $categoryToUse = null;
          }
          $squareToUse = $squareLink !== '' ? $squareLink : ($existing['square_payment_link'] ?? '');
          $stripeToUse = $stripeLink !== '' ? $stripeLink : ($existing['stripe_payment_link'] ?? '');
          $compareToUse = $hasPriceCompare ? $priceCompare : ($existing['price_compare'] ?? null);
          $updateStmt->execute([
            $name,
            $sku,
            $price,
            $compareToUse,
            $shippingCost,
            $stock,
            $categoryToUse,
            $descToUse,
            $active,
            $featured,
            $imgToUse,
            $squareToUse,
            $stripeToUse,
            $existing['id']
          ]);
          $updated++;
        } else {
          $insertStmt->execute([
            $name,
            $sku,
            $price,
            $priceCompare,
            $shippingCost,
            $stock,
            $categoryId,
            $description,
            $active,
            0,
            $imagePath ?: null,
            $squareLink,
            $stripeLink
          ]);
          $inserted++;
        }
      }
      $pdo->commit();
    } catch (Throwable $e) {
      $pdo->rollBack();
      fclose($handle);
      products_flash('error', 'Erro ao importar: '.$e->getMessage());
      header('Location: products.php?action=import'); exit;
    }
    fclose($handle);
    $parts = [];
    if ($inserted) $parts[] = "{$inserted} produto(s) criado(s)";
    if ($updated) $parts[] = "{$updated} produto(s) atualizado(s)";
    if ($skipped) $parts[] = "{$skipped} linha(s) ignorada(s)";
    if ($errors) {
      $parts[] = implode(' ', $errors);
      products_flash('warning', implode(' | ', $parts));
    } else {
      products_flash('success', $parts ? implode(' | ', $parts) : 'Importação concluída.');
    }
    header('Location: products.php'); exit;
  }

  admin_header('Importar produtos');
  echo '<div class="card"><div class="card-title">Importar produtos via CSV</div><div class="p-4 space-y-4">';
  $flash = products_take_flash();
  if ($flash) {
    $class = $flash['type'] === 'error' ? 'alert alert-error' : ($flash['type'] === 'warning' ? 'alert alert-warning' : 'alert alert-success');
    $icon = $flash['type'] === 'error' ? 'fa-circle-exclamation' : ($flash['type'] === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-check');
    echo '<div class="'.$class.'"><i class="fa-solid '.$icon.' mr-2"></i>'.sanitize_html($flash['message']).'</div>';
  }
  echo '<p class="text-sm text-gray-600">Envie um arquivo CSV (UTF-8) com o cabeçalho <code>sku,name,price,price_compare,stock,category_id,description,image_path,square_payment_link,stripe_payment_link,active</code>. SKU existente é atualizado; demais são criados.</p>';
  echo '<p class="text-sm text-gray-600">Use <a class="text-brand-600 underline" href="products.php?action=export">Exportar CSV</a> para gerar um modelo.</p>';
  echo '<form method="post" enctype="multipart/form-data" class="space-y-3">';
  echo '  <input type="hidden" name="csrf" value="'.csrf_token().'">';
  echo '  <input class="input w-full" type="file" name="csv" accept=".csv" required>';
  echo '  <div class="flex gap-2">';
  echo '    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-file-arrow-up mr-2"></i>Importar</button>';
  echo '    <a class="btn btn-ghost" href="products.php"><i class="fa-solid fa-arrow-left mr-2"></i>Voltar</a>';
  echo '  </div>';
  echo '</form></div></div>';
  admin_footer(); exit;
}


if ($action==='new') {
  admin_header('Novo produto');
  echo '<div class="card"><div class="card-title">Cadastrar produto</div>';
  product_form([]);
  echo '</div>';
  admin_footer(); exit;
}

if ($action==='create' && $_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');

  $name = sanitize_string($_POST['name'] ?? '');
  $sku  = sanitize_string($_POST['sku'] ?? '');
  $price= (float)($_POST['price'] ?? 0);
  $price_compare_input = trim((string)($_POST['price_compare'] ?? ''));
  $price_compare = ($price_compare_input === '') ? null : (float)$price_compare_input;
  if ($price_compare !== null && $price_compare < 0) {
    $price_compare = null;
  }
  $shipping_cost = isset($_POST['shipping_cost']) ? (float)$_POST['shipping_cost'] : 7.0;
  if ($shipping_cost < 0) $shipping_cost = 0;
  $stock= (int)($_POST['stock'] ?? 0);
  $category_id = (int)($_POST['category_id'] ?? 0);
  if ($category_id <= 0) {
    $category_id = null;
  }
  $description = sanitize_string($_POST['description'] ?? '', 2000);
  $active = (int)($_POST['active'] ?? 1);
  $featured = (int)($_POST['featured'] ?? 0);
  $image_path = null;
  $square_input = (string)($_POST['square_payment_link'] ?? '');
  [$square_ok, $square_link, $square_error] = normalize_square_link($square_input);
  $stripe_input = (string)($_POST['stripe_payment_link'] ?? '');
  [$stripe_ok, $stripe_link, $stripe_error] = normalize_stripe_link($stripe_input);
  if (!$square_ok) {
    admin_header('Novo produto');
    echo '<div class="card"><div class="card-title">Cadastrar produto</div>';
    echo '<div class="p-4 mb-2 rounded border border-red-200 bg-red-50 text-red-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i> '.sanitize_html($square_error).'</div>';
    product_form([
      'name'=>$name,'sku'=>$sku,'price'=>$price,'stock'=>$stock,'category_id'=>$category_id,
      'description'=>$description,'active'=>$active,'featured'=>$featured,'image_path'=>null,
      'shipping_cost'=>$shipping_cost,
      'price_compare'=>$price_compare_input,
      'square_payment_link'=>$square_input,
      'stripe_payment_link'=>$stripe_input
    ]);
    echo '</div>';
    admin_footer(); exit;
  }
  if (!$stripe_ok) {
    admin_header('Novo produto');
    echo '<div class="card"><div class="card-title">Cadastrar produto</div>';
    echo '<div class="p-4 mb-2 rounded border border-red-200 bg-red-50 text-red-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i> '.sanitize_html($stripe_error).'</div>';
    product_form([
      'name'=>$name,'sku'=>$sku,'price'=>$price,'stock'=>$stock,'category_id'=>$category_id,
      'description'=>$description,'active'=>$active,'featured'=>$featured,'image_path'=>null,
      'shipping_cost'=>$shipping_cost,
      'price_compare'=>$price_compare_input,
      'square_payment_link'=>$square_input,
      'stripe_payment_link'=>$stripe_input
    ]);
    echo '</div>';
    admin_footer(); exit;
  }

  // Checagem de SKU duplicado (antes de gravar)
  if ($sku === '' || sku_exists($pdo, $sku, null)) {
    admin_header('Novo produto');
    echo '<div class="card"><div class="card-title">Cadastrar produto</div>';
    echo '<div class="p-4 mb-2 rounded border border-red-200 bg-red-50 text-red-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i> SKU já utilizado: <b>'.sanitize_html($sku).'</b>. Escolha outro.</div>';
    // Re-exibe formulário com os dados postados
    product_form([
      'name'=>$name,'sku'=>$sku,'price'=>$price,'stock'=>$stock,'category_id'=>$category_id,
      'description'=>$description,'active'=>$active,'featured'=>$featured,'image_path'=>null,
      'shipping_cost'=>$shipping_cost,
      'price_compare'=>$price_compare_input,
      'square_payment_link'=>$square_input,
      'stripe_payment_link'=>$stripe_input
    ]);
    echo '</div>';
    admin_footer(); exit;
  }

  if (!empty($_FILES['image']['name']) && $_FILES['image']['error']===UPLOAD_ERR_OK) {
    $dir = __DIR__.'/storage/products'; @mkdir($dir,0775,true);
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext,['jpg','jpeg','png','webp'])) $ext='jpg';
    $fname = 'p_'.time().'_'.bin2hex(random_bytes(3)).'.'.$ext;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $dir.'/'.$fname)) {
      $image_path = 'storage/products/'.$fname;
    }
  }

  $st=$pdo->prepare("INSERT INTO products(name,sku,price,price_compare,shipping_cost,stock,category_id,description,active,featured,image_path,square_payment_link,stripe_payment_link,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
  try {
    $st->execute([$name,$sku,$price,$price_compare,$shipping_cost,$stock,$category_id,$description,$active,$featured,$image_path,$square_link,$stripe_link]);
    header('Location: products.php'); exit;
  } catch (PDOException $e) {
    // Proteção extra caso outro processo crie o mesmo SKU no intervalo
    if (!empty($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
      admin_header('Novo produto');
      echo '<div class="card"><div class="card-title">Cadastrar produto</div>';
      echo '<div class="p-4 mb-2 rounded border border-red-200 bg-red-50 text-red-700"><i class="fa-solid fa-circle-exclamation mr-1"></i> SKU duplicado no banco. Tente outro valor.</div>';
      product_form([
        'name'=>$name,
        'sku'=>$sku,
        'price'=>$price,
        'shipping_cost'=>$shipping_cost,
        'stock'=>$stock,
        'category_id'=>$category_id,
        'description'=>$description,
        'active'=>$active,
        'featured'=>$featured,
        'image_path'=>null,
        'square_payment_link'=>$square_input,
        'stripe_payment_link'=>$stripe_input
      ]);
      echo '</div>';
      admin_footer(); exit;
    }
    throw $e;
  }
}

if ($action==='edit') {
  $id=(int)($_GET['id'] ?? 0);
  $st=$pdo->prepare("SELECT * FROM products WHERE id=?");
  $st->execute([$id]);
  $row=$st->fetch();
  if (!$row){ header('Location: products.php'); exit; }
  admin_header('Editar produto');
  echo '<div class="card"><div class="card-title">Editar produto #'.(int)$id.'</div>';
  product_form($row);
  echo '</div>';
  admin_footer(); exit;
}

if ($action==='update' && $_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
  $id=(int)($_GET['id'] ?? 0);

  $st=$pdo->prepare("SELECT image_path FROM products WHERE id=?");
  $st->execute([$id]);
  $currentImage=$st->fetchColumn();

  $name = sanitize_string($_POST['name'] ?? '');
  $sku  = sanitize_string($_POST['sku'] ?? '');
  $price= (float)($_POST['price'] ?? 0);
  $price_compare_input = trim((string)($_POST['price_compare'] ?? ''));
  $price_compare = ($price_compare_input === '') ? null : (float)$price_compare_input;
  if ($price_compare !== null && $price_compare < 0) {
    $price_compare = null;
  }
  $shipping_cost = isset($_POST['shipping_cost']) ? (float)$_POST['shipping_cost'] : 7.0;
  if ($shipping_cost < 0) $shipping_cost = 0;
  $stock= (int)($_POST['stock'] ?? 0);
  $category_id = (int)($_POST['category_id'] ?? 0);
  if ($category_id <= 0) {
    $category_id = null;
  }
  $description = sanitize_string($_POST['description'] ?? '', 2000);
  $active = (int)($_POST['active'] ?? 1);
  $featured = (int)($_POST['featured'] ?? 0);
  $image_path = $currentImage;
  $square_input = (string)($_POST['square_payment_link'] ?? '');
  [$square_ok, $square_link, $square_error] = normalize_square_link($square_input);
  $stripe_input = (string)($_POST['stripe_payment_link'] ?? '');
  [$stripe_ok, $stripe_link, $stripe_error] = normalize_stripe_link($stripe_input);

  if (!$square_ok || !$stripe_ok) {
    $alert = !$square_ok ? $square_error : $stripe_error;
    admin_header('Editar produto');
    echo '<div class="card"><div class="card-title">Editar produto #'.(int)$id.'</div>';
    echo '<div class="p-4 mb-2 rounded border border-red-200 bg-red-50 text-red-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i> '.sanitize_html($alert).'</div>';
    product_form([
      'id'=>$id,'name'=>$name,'sku'=>$sku,'price'=>$price,'shipping_cost'=>$shipping_cost,'stock'=>$stock,'category_id'=>$category_id,
      'description'=>$description,'active'=>$active,'featured'=>$featured,'image_path'=>$image_path,
      'price_compare'=>$price_compare_input,
      'square_payment_link'=>$square_input,
      'stripe_payment_link'=>$stripe_input
    ]);
    echo '</div>';
    admin_footer(); exit;
  }

  if ($sku === '' || sku_exists($pdo, $sku, $id)) {
    admin_header('Editar produto');
    echo '<div class="card"><div class="card-title">Editar produto #'.(int)$id.'</div>';
    echo '<div class="p-4 mb-2 rounded border border-red-200 bg-red-50 text-red-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i> SKU já utilizado por outro produto: <b>'.sanitize_html($sku).'</b>.</div>';
    product_form([
      'id'=>$id,'name'=>$name,'sku'=>$sku,'price'=>$price,'shipping_cost'=>$shipping_cost,'stock'=>$stock,'category_id'=>$category_id,
      'description'=>$description,'active'=>$active,'featured'=>$featured,'image_path'=>$image_path,
      'price_compare'=>$price_compare_input,
      'square_payment_link'=>$square_input,
      'stripe_payment_link'=>$stripe_input
    ]);
    echo '</div>';
    admin_footer(); exit;
  }

  if (!empty($_FILES['image']['name']) && $_FILES['image']['error']===UPLOAD_ERR_OK) {
    $dir = __DIR__.'/storage/products'; @mkdir($dir,0775,true);
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext,['jpg','jpeg','png','webp'])) $ext='jpg';
    $fname = 'p_'.time().'_'.bin2hex(random_bytes(3)).'.'.$ext;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $dir.'/'.$fname)) {
      $image_path = 'storage/products/'.$fname;
    }
  }

  $st=$pdo->prepare("UPDATE products SET name=?,sku=?,price=?,price_compare=?,shipping_cost=?,stock=?,category_id=?,description=?,active=?,featured=?,image_path=?,square_payment_link=?,stripe_payment_link=? WHERE id=?");
  try {
    $st->execute([$name,$sku,$price,$price_compare,$shipping_cost,$stock,$category_id,$description,$active,$featured,$image_path,$square_link,$stripe_link,$id]);
    header('Location: products.php'); exit;
  } catch (PDOException $e) {
    if (!empty($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
      admin_header('Editar produto');
      echo '<div class="card"><div class="card-title">Editar produto #'.(int)$id.'</div>';
      echo '<div class="p-4 mb-2 rounded border border-red-200 bg-red-50 text-red-700"><i class="fa-solid fa-circle-exclamation mr-1"></i> SKU duplicado no banco. Tente outro valor.</div>';
      product_form([
        'id'=>$id,'name'=>$name,'sku'=>$sku,'price'=>$price,'shipping_cost'=>$shipping_cost,'stock'=>$stock,'category_id'=>$category_id,
        'description'=>$description,'active'=>$active,'featured'=>$featured,'image_path'=>$image_path,
        'price_compare'=>$price_compare_input,
        'square_payment_link'=>$square_input,
        'stripe_payment_link'=>$stripe_input
      ]);
      echo '</div>';
      admin_footer(); exit;
    }
    throw $e;
  }
}

if ($action==='delete') {
  $id=(int)($_GET['id'] ?? 0);
  $csrf=$_GET['csrf'] ?? '';
  if (!csrf_check($csrf)) die('CSRF');
  require_super_admin();
  // Soft delete: active=0
  $st=$pdo->prepare("UPDATE products SET active=0 WHERE id=?");
  $st->execute([$id]);
  header('Location: products.php'); exit;
}

if ($action==='destroy') {
  $id=(int)($_GET['id'] ?? 0);
  $csrf=$_GET['csrf'] ?? '';
  if (!csrf_check($csrf)) die('CSRF');
  require_super_admin();
  if ($id > 0) {
    $st=$pdo->prepare("DELETE FROM products WHERE id=?");
    $st->execute([$id]);
    products_flash('success', 'Produto #'.$id.' excluído definitivamente.');
  }
  header('Location: products.php'); exit;
}

if ($action==='bulk_destroy' && $_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
  require_super_admin();
  $ids = array_filter(array_map('intval', $_POST['selected'] ?? []));
  if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)");
    $st->execute($ids);
    products_flash('success', count($ids).' produto(s) excluído(s) definitivamente.');
  } else {
    products_flash('warning', 'Selecione pelo menos um produto para excluir.');
  }
  header('Location: products.php'); exit;
}

/* ========= Listagem ========= */

admin_header('Produtos');
$flash = products_take_flash();
if ($flash) {
  $class = $flash['type'] === 'error' ? 'alert alert-error' : ($flash['type'] === 'warning' ? 'alert alert-warning' : 'alert alert-success');
  $icon = $flash['type'] === 'error' ? 'fa-circle-exclamation' : ($flash['type'] === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-check');
  echo '<div class="'.$class.' mx-auto max-w-4xl mb-4"><i class="fa-solid '.$icon.' mr-2"></i>'.sanitize_html($flash['message']).'</div>';
}
if (!$canManageProducts) {
  echo '<div class="alert alert-warning mx-auto max-w-4xl mb-4"><i class="fa-solid fa-circle-info mr-2"></i>Você possui acesso somente leitura nesta seção.</div>';
}
$q = trim((string)($_GET['q'] ?? ''));
$w = " WHERE 1=1 ";
$p = [];
if ($q!==''){
  $w .= " AND (p.name LIKE ? OR p.sku LIKE ?) ";
  $like = "%$q%"; $p = [$like,$like];
}
$st=$pdo->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id $w ORDER BY p.id DESC LIMIT 200");
$st->execute($p);

echo '<div class="card">';
echo '<div class="card-title">Produtos</div>';
echo '<div class="p-3 row gap items-center flex-wrap">';
echo '  <form method="get" class="row gap search"><input type="hidden" name="action" value="list"><input class="input" name="q" value="'.sanitize_html($q).'" placeholder="Buscar por nome ou SKU"><button class="btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button></form>';
echo '  <div class="flex gap-2 flex-wrap">';
if ($canManageProducts) {
  echo '    <a class="btn alt" href="products.php?action=new"><i class="fa-solid fa-plus"></i> Novo</a>';
  echo '    <a class="btn" href="products.php?action=import"><i class="fa-solid fa-file-arrow-up"></i> Importar CSV</a>';
}
echo '    <a class="btn btn-ghost" href="products.php?action=export"><i class="fa-solid fa-file-arrow-down"></i> Exportar CSV</a>';
echo '  </div>';
echo '</div>';
echo '<form id="bulk-delete-form" method="post" action="products.php?action=bulk_destroy">';
echo '  <input type="hidden" name="csrf" value="'.csrf_token().'">';
echo '  <div class="p-3 overflow-x-auto"><table class="table"><thead><tr>';
if ($isSuperAdmin) {
  echo '<th><input type="checkbox" id="checkAllProducts"></th>';
} else {
  echo '<th></th>';
}
echo '<th>#</th><th>SKU</th><th>Produto</th><th>Categoria</th><th>Preço</th><th>Frete</th><th>Estoque</th><th>Square</th><th>Ativo</th><th></th></tr></thead><tbody>';
foreach($st as $r){
  echo '<tr>';
  echo '<td>';
  if ($isSuperAdmin) {
    echo '<input type="checkbox" name="selected[]" value="'.(int)$r['id'].'" class="product-select">';
  }
  echo '</td>';
  echo '<td>'.(int)$r['id'].'</td>';
  echo '<td>'.sanitize_html($r['sku']).'</td>';
  echo '<td>'.sanitize_html($r['name']).'</td>';
  echo '<td>'.sanitize_html($r['category_name']).'</td>';
  $priceNow = (float)$r['price'];
  $priceCompareList = isset($r['price_compare']) ? (float)$r['price_compare'] : null;
  if ($priceCompareList && $priceCompareList > $priceNow) {
    $compareFormatted = '$ '.number_format($priceCompareList,2,',','.');
    $priceFormatted = '$ '.number_format($priceNow,2,',','.');
    echo '<td><div class="flex flex-col leading-tight"><span class="text-[11px] line-through text-gray-400">'.$compareFormatted.'</span><span class="font-semibold text-brand-700">'.$priceFormatted.'</span></div></td>';
  } else {
    echo '<td>$ '.number_format($priceNow,2,',','.').'</td>';
  }
  echo '<td>$ '.number_format((float)($r['shipping_cost'] ?? 7),2,',','.').'</td>';
  echo '<td>'.(int)$r['stock'].'</td>';
  $squareCol = trim((string)($r['square_payment_link'] ?? ''));
  if ($squareCol !== '') {
    $safeLink = sanitize_html($squareCol);
    echo '<td><span class="badge ok">Config.</span> <a class="text-sm text-brand-600 underline ml-1" href="'.$safeLink.'" target="_blank" rel="noopener">Testar</a></td>';
  } else {
    echo '<td><span class="badge danger">Pendente</span></td>';
  }
  echo '<td>'.((int)$r['active']?'<span class="badge ok">Sim</span>':'<span class="badge danger">Não</span>').'</td>';
  echo '<td class="flex gap-2 flex-wrap">';
  if ($canManageProducts) {
    echo '<a class="btn" href="products.php?action=edit&id='.(int)$r['id'].'"><i class="fa-solid fa-pen"></i> Editar</a>';
  }
  if ($isSuperAdmin) {
    echo '<a class="btn" href="products.php?action=delete&id='.(int)$r['id'].'&csrf='.csrf_token().'" onclick="return confirm(\'Desativar este produto?\')"><i class="fa-solid fa-ban"></i> Desativar</a>';
    echo '<a class="btn btn-danger" href="products.php?action=destroy&id='.(int)$r['id'].'&csrf='.csrf_token().'" onclick="return confirm(\'Excluir definitivamente este produto?\')"><i class="fa-solid fa-trash"></i> Excluir</a>';
  }
  echo '</td>';
  echo '</tr>';
}
echo '</tbody></table></div>';
if ($isSuperAdmin) {
  echo '<div class="p-3 flex flex-wrap gap-3 items-center justify-end border-t">';
  echo '  <button type="submit" class="btn btn-danger" onclick="return confirm(\'Excluir definitivamente os itens selecionados?\')"><i class="fa-solid fa-trash-can mr-2"></i>Excluir selecionados</button>';
  echo '</div>';
}
echo '</form></div>';
echo '<script>
document.getElementById("checkAllProducts")?.addEventListener("change", function(e){
  const checked = e.target.checked;
  document.querySelectorAll(".product-select").forEach(cb => cb.checked = checked);
});
</script>';

admin_footer();
