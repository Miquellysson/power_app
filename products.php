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
echo '<div class="p-3 row gap"><form method="get" class="row gap search"><input type="hidden" name="action" value="list"><input class="input" name="q" value="'.sanitize_html($q).'" placeholder="Buscar por nome ou SKU"><button class="btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button></form><a class="btn alt" href="products.php?action=new"><i class="fa-solid fa-plus"></i> Novo</a></div>';
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
