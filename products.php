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

$action = $_GET['action'] ?? 'list';

/* ========= Helpers ========= */

function products_flash(string $type, string $message): void {
  $_SESSION['products_flash'] = ['type' => $type, 'message' => $message];
}

function products_take_flash(): ?array {
  $flash = $_SESSION['products_flash'] ?? null;
  unset($_SESSION['products_flash']);
  return $flash;
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

/** Formulário de produto (reutilizável) */
function product_form($row){
  $id = (int)($row['id'] ?? 0);
  $name = sanitize_html($row['name'] ?? '');
  $sku = sanitize_html($row['sku'] ?? '');
  $price = number_format((float)($row['price'] ?? 0), 2, '.', '');
  $stock = (int)($row['stock'] ?? 0);
  $category_id = (int)($row['category_id'] ?? 0);
  $desc = sanitize_html($row['description'] ?? '');
  $active = (int)($row['active'] ?? 1);
  $featured = (int)($row['featured'] ?? 0);
  $img = sanitize_html($row['image_path'] ?? '');
  $square_link = sanitize_html($row['square_payment_link'] ?? '');
  $csrf = csrf_token();

  echo '<form class="p-4 space-y-3" method="post" enctype="multipart/form-data" action="products.php?action='.($id?'update&id='.$id:'create').'">';
  echo '  <input type="hidden" name="csrf" value="'.$csrf.'">';
  echo '  <div class="grid md:grid-cols-2 gap-3">';
  echo '    <div class="field"><span>Nome</span><input class="input" name="name" value="'.$name.'" required></div>';
  echo '    <div class="field"><span>SKU</span><input class="input" name="sku" value="'.$sku.'" required></div>';
  echo '    <div class="field"><span>Preço</span><input class="input" name="price" type="number" step="0.01" value="'.$price.'" required></div>';
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
  fputcsv($out, ['sku','name','price','stock','category_id','description','image_path','square_payment_link','active']);
  $stmt = $pdo->query("SELECT sku,name,price,stock,category_id,description,image_path,square_payment_link,active FROM products ORDER BY id ASC");
  foreach ($stmt as $row) {
    fputcsv($out, [
      $row['sku'],
      $row['name'],
      number_format((float)$row['price'], 2, '.', ''),
      (int)$row['stock'],
      $row['category_id'],
      $row['description'],
      $row['image_path'],
      $row['square_payment_link'],
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
    $updateStmt = $pdo->prepare("UPDATE products SET name=?, sku=?, price=?, stock=?, category_id=?, description=?, active=?, featured=?, image_path=?, square_payment_link=? WHERE id=?");
    $insertStmt = $pdo->prepare("INSERT INTO products(name,sku,price,stock,category_id,description,active,featured,image_path,square_payment_link,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())");
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
        $priceRaw = str_replace(['.',','], ['','.'], $data['price'] ?? '0');
        if (!is_numeric($priceRaw)) {
          $errors[] = "Linha {$line}: Preço inválido para o SKU {$sku}.";
          $skipped++;
          continue;
        }
        $price = (float)$priceRaw;
        $stock = (int)($data['stock'] ?? 0);
        $categoryId = null;
        if (isset($data['category_id']) && $data['category_id'] !== '') {
          $categoryId = (int)$data['category_id'];
          if ($categoryId < 0) $categoryId = null;
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
        $active = isset($data['active']) ? (int)$data['active'] : 1;
        if ($active !== 0) $active = 1;
        $selectSku->execute([$sku]);
        $existing = $selectSku->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
          $featured = (int)($existing['featured'] ?? 0);
          $imgToUse = ($imagePath !== null && $imagePath !== '') ? $imagePath : ($existing['image_path'] ?? null);
          $descToUse = $description !== '' ? $description : ($existing['description'] ?? '');
          $categoryToUse = $categoryId ?? ($existing['category_id'] ?? null);
          $squareToUse = $squareLink !== '' ? $squareLink : ($existing['square_payment_link'] ?? '');
          $updateStmt->execute([
            $name,
            $sku,
            $price,
            $stock,
            $categoryToUse,
            $descToUse,
            $active,
            $featured,
            $imgToUse,
            $squareToUse,
            $existing['id']
          ]);
          $updated++;
        } else {
          $insertStmt->execute([
            $name,
            $sku,
            $price,
            $stock,
            $categoryId,
            $description,
            $active,
            0,
            $imagePath ?: null,
            $squareLink
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
  echo '<p class="text-sm text-gray-600">Envie um arquivo CSV (UTF-8) com o cabeçalho <code>sku,name,price,stock,category_id,description,image_path,square_payment_link,active</code>. SKU existente é atualizado; demais são criados.</p>';
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
  $stock= (int)($_POST['stock'] ?? 0);
  $category_id = (int)($_POST['category_id'] ?? 0);
  $description = sanitize_string($_POST['description'] ?? '', 2000);
  $active = (int)($_POST['active'] ?? 1);
  $featured = (int)($_POST['featured'] ?? 0);
  $image_path = null;
  $square_input = (string)($_POST['square_payment_link'] ?? '');
  [$square_ok, $square_link, $square_error] = normalize_square_link($square_input);
  if (!$square_ok) {
    admin_header('Novo produto');
    echo '<div class="card"><div class="card-title">Cadastrar produto</div>';
    echo '<div class="p-4 mb-2 rounded border border-red-200 bg-red-50 text-red-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i> '.sanitize_html($square_error).'</div>';
    product_form([
      'name'=>$name,'sku'=>$sku,'price'=>$price,'stock'=>$stock,'category_id'=>$category_id,
      'description'=>$description,'active'=>$active,'featured'=>$featured,'image_path'=>null,
      'square_payment_link'=>$square_input
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
      'square_payment_link'=>$square_input
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

  $st=$pdo->prepare("INSERT INTO products(name,sku,price,stock,category_id,description,active,featured,image_path,square_payment_link,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())");
  try {
    $st->execute([$name,$sku,$price,$stock,$category_id,$description,$active,$featured,$image_path,$square_link]);
    header('Location: products.php'); exit;
  } catch (PDOException $e) {
    // Proteção extra caso outro processo crie o mesmo SKU no intervalo
    if (!empty($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
      admin_header('Novo produto');
      echo '<div class="card"><div class="card-title">Cadastrar produto</div>';
      echo '<div class="p-4 mb-2 rounded border border-red-200 bg-red-50 text-red-700"><i class="fa-solid fa-circle-exclamation mr-1"></i> SKU duplicado no banco. Tente outro valor.</div>';
      product_form([
        'name'=>$name,'sku'=>$sku,'price'=>$price,'stock'=>$stock,'category_id'=>$category_id,
        'description'=>$description,'active'=>$active,'featured'=>$featured,'image_path'=>null,
        'square_payment_link'=>$square_input
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
  $cur=$st->fetchColumn();

  $name = sanitize_string($_POST['name'] ?? '');
  $sku  = sanitize_string($_POST['sku'] ?? '');
  $price= (float)($_POST['price'] ?? 0);
  $stock= (int)($_POST['stock'] ?? 0);
  $category_id = (int)($_POST['category_id'] ?? 0);
  $description = sanitize_string($_POST['description'] ?? '', 2000);
  $active = (int)($_POST['active'] ?? 1);
  $featured = (int)($_POST['featured'] ?? 0);
  $image_path = $cur;
  $square_input = (string)($_POST['square_payment_link'] ?? '');
  [$square_ok, $square_link, $square_error] = normalize_square_link($square_input);
  if (!$square_ok) {
    admin_header('Editar produto');
    echo '<div class="card"><div class="card-title">Editar produto #'.(int)$id.'</div>';
    echo '<div class="p-4 mb-2 rounded border border-red-200 bg-red-50 text-red-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i> '.sanitize_html($square_error).'</div>';
    product_form([
      'id'=>$id,'name'=>$name,'sku'=>$sku,'price'=>$price,'stock'=>$stock,'category_id'=>$category_id,
      'description'=>$description,'active'=>$active,'featured'=>$featured,'image_path'=>$image_path,
      'square_payment_link'=>$square_input
    ]);
    echo '</div>';
    admin_footer(); exit;
  }

  // Checagem de SKU duplicado (exclui o próprio ID)
  if ($sku === '' || sku_exists($pdo, $sku, $id)) {
    admin_header('Editar produto');
    echo '<div class="card"><div class="card-title">Editar produto #'.(int)$id.'</div>';
    echo '<div class="p-4 mb-2 rounded border border-red-200 bg-red-50 text-red-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i> SKU já utilizado por outro produto: <b>'.sanitize_html($sku).'</b>.</div>';
    product_form([
      'id'=>$id,'name'=>$name,'sku'=>$sku,'price'=>$price,'stock'=>$stock,'category_id'=>$category_id,
      'description'=>$description,'active'=>$active,'featured'=>$featured,'image_path'=>$image_path,
      'square_payment_link'=>$square_input
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

  $st=$pdo->prepare("UPDATE products SET name=?,sku=?,price=?,stock=?,category_id=?,description=?,active=?,featured=?,image_path=?,square_payment_link=? WHERE id=?");
  try {
    $st->execute([$name,$sku,$price,$stock,$category_id,$description,$active,$featured,$image_path,$square_link,$id]);
    header('Location: products.php'); exit;
  } catch (PDOException $e) {
    if (!empty($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
      admin_header('Editar produto');
      echo '<div class="card"><div class="card-title">Editar produto #'.(int)$id.'</div>';
      echo '<div class="p-4 mb-2 rounded border border-red-200 bg-red-50 text-red-700"><i class="fa-solid fa-circle-exclamation mr-1"></i> SKU duplicado no banco. Tente outro valor.</div>';
      product_form([
        'id'=>$id,'name'=>$name,'sku'=>$sku,'price'=>$price,'stock'=>$stock,'category_id'=>$category_id,
        'description'=>$description,'active'=>$active,'featured'=>$featured,'image_path'=>$image_path,
        'square_payment_link'=>$square_input
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
  // Soft delete: active=0
  $st=$pdo->prepare("UPDATE products SET active=0 WHERE id=?");
  $st->execute([$id]);
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
echo '    <a class="btn alt" href="products.php?action=new"><i class="fa-solid fa-plus"></i> Novo</a>';
echo '    <a class="btn" href="products.php?action=import"><i class="fa-solid fa-file-arrow-up"></i> Importar CSV</a>';
echo '    <a class="btn btn-ghost" href="products.php?action=export"><i class="fa-solid fa-file-arrow-down"></i> Exportar CSV</a>';
echo '  </div>';
echo '</div>';
echo '<div class="p-3 overflow-x-auto"><table class="table"><thead><tr><th>#</th><th>SKU</th><th>Produto</th><th>Categoria</th><th>Preço</th><th>Estoque</th><th>Square</th><th>Ativo</th><th></th></tr></thead><tbody>';
foreach($st as $r){
  echo '<tr>';
  echo '<td>'.(int)$r['id'].'</td>';
  echo '<td>'.sanitize_html($r['sku']).'</td>';
  echo '<td>'.sanitize_html($r['name']).'</td>';
  echo '<td>'.sanitize_html($r['category_name']).'</td>';
  echo '<td>$ '.number_format((float)$r['price'],2,',','.').'</td>';
  echo '<td>'.(int)$r['stock'].'</td>';
  $squareCol = trim((string)($r['square_payment_link'] ?? ''));
  if ($squareCol !== '') {
    $safeLink = sanitize_html($squareCol);
    echo '<td><span class="badge ok">Config.</span> <a class="text-sm text-brand-600 underline ml-1" href="'.$safeLink.'" target="_blank" rel="noopener">Testar</a></td>';
  } else {
    echo '<td><span class="badge danger">Pendente</span></td>';
  }
  echo '<td>'.((int)$r['active']?'<span class="badge ok">Sim</span>':'<span class="badge danger">Não</span>').'</td>';
  echo '<td><a class="btn" href="products.php?action=edit&id='.(int)$r['id'].'"><i class="fa-solid fa-pen"></i> Editar</a> <a class="btn" href="products.php?action=delete&id='.(int)$r['id'].'&csrf='.csrf_token().'" onclick="return confirm(\'Desativar este produto?\')"><i class="fa-solid fa-trash"></i> Desativar</a></td>';
  echo '</tr>';
}
echo '</tbody></table></div></div>';

admin_footer();
