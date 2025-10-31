<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require __DIR__.'/bootstrap.php';
require __DIR__.'/config.php';
require __DIR__.'/lib/db.php';
require __DIR__.'/lib/utils.php';
require __DIR__.'/admin_layout.php';

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
if (!function_exists('validate_email')){
  function validate_email($e){ return (bool)filter_var($e,FILTER_VALIDATE_EMAIL); }
}
$pdo = db();
require_admin();

$action = $_GET['action'] ?? 'list';
$canManageOrders = admin_can('manage_orders');
if ($action === 'update_status' && !$canManageOrders) {
  require_admin_capability('manage_orders');
}

function status_badge($s){
  if ($s==='paid') return '<span class="badge ok">Pago</span>';
  if ($s==='pending') return '<span class="badge warn">Pendente</span>';
  if ($s==='shipped') return '<span class="badge ok">Enviado</span>';
  if ($s==='canceled') return '<span class="badge danger">Cancelado</span>';
  return '<span class="badge">'.sanitize_html($s).'</span>';
}

if ($action==='view') {
  $id=(int)($_GET['id'] ?? 0);
  $st=$pdo->prepare("SELECT o.*, c.name AS customer, c.email, c.phone, c.address, c.city, c.state, c.zipcode FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE o.id=?");
  $st->execute([$id]);
  $o=$st->fetch();
  if (!$o){ header('Location: orders.php'); exit; }
  $items = json_decode($o['items_json'] ?? '[]', true) ?: [];
  admin_header('Pedido #'.$id);

  echo '<div class="grid md:grid-cols-3 gap-3">';
  echo '<div class="card md:col-span-2"><div class="card-title">Itens do pedido</div><div class="p-3 overflow-x-auto">';
  echo '<table class="table"><thead><tr><th>SKU</th><th>Produto</th><th>Qtd</th><th>Preço</th><th>Total</th></tr></thead><tbody>';
  foreach($items as $it){
    $line = (float)$it['price'] * (int)$it['qty'];
    echo '<tr>';
    echo '<td>'.sanitize_html($it['sku'] ?? '').'</td>';
    echo '<td>'.sanitize_html($it['name']).'</td>';
    echo '<td>'.(int)$it['qty'].'</td>';
    echo '<td>$ '.number_format((float)$it['price'],2,',','.').'</td>';
    echo '<td>$ '.number_format($line,2,',','.').'</td>';
    echo '</tr>';
  }
  echo '</tbody></table></div></div>';

  echo '<div class="card"><div class="card-title">Resumo</div><div class="p-3">';
  echo '<div class="mb-2">Subtotal: <strong>$ '.number_format((float)$o['subtotal'],2,',','.').'</strong></div>';
  echo '<div class="mb-2">Frete: <strong>$ '.number_format((float)$o['shipping_cost'],2,',','.').'</strong></div>';
  echo '<div class="mb-2">Total: <strong>$ '.number_format((float)$o['total'],2,',','.').'</strong></div>';
  echo '<div class="mb-2">Pagamento: <strong>'.sanitize_html($o['payment_method']).'</strong></div>';
  if (!empty($o['payment_ref'])) echo '<div class="mb-2">Ref: <a class="text-blue-600 underline" href="'.sanitize_html($o['payment_ref']).'" target="_blank">abrir</a></div>';
  echo '<div class="mb-2">Status: '.status_badge($o['status']).'</div>';
  if ($canManageOrders) {
    echo '<form class="mt-3" method="post" action="orders.php?action=update_status&id='.$id.'"><input type="hidden" name="csrf" value="'.csrf_token().'"><select class="select" name="status" required><option value="pending" '.($o['status']==='pending'?'selected':'').'>Pendente</option><option value="paid" '.($o['status']==='paid'?'selected':'').'>Pago</option><option value="shipped" '.($o['status']==='shipped'?'selected':'').'>Enviado</option><option value="canceled" '.($o['status']==='canceled'?'selected':'').'>Cancelado</option></select><button class="btn alt ml-2" type="submit"><i class="fa-solid fa-rotate"></i> Atualizar</button></form>';
  } else {
    echo '<div class="text-xs text-gray-500">Você não tem permissão para alterar o status.</div>';
  }
  if (!empty($o['zelle_receipt'])){
    echo '<div class="mt-3"><a class="btn" href="'.sanitize_html($o['zelle_receipt']).'" target="_blank"><i class="fa-solid fa-file"></i> Ver comprovante</a></div>';
  }
  echo '</div></div>';

  echo '<div class="card md:col-span-3"><div class="card-title">Cliente</div><div class="p-3">';
  echo '<div><strong>'.sanitize_html($o['customer']).'</strong></div>';
  echo '<div>'.sanitize_html($o['email']).' • '.sanitize_html($o['phone']).'</div>';
  echo '<div>'.sanitize_html($o['address']).' — '.sanitize_html($o['city']).' / '.sanitize_html($o['state']).' — '.sanitize_html($o['zipcode']).'</div>';
  echo '</div></div>';

  echo '</div>';
  admin_footer(); exit;
}

if ($action==='update_status' && $_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
  $id=(int)($_GET['id'] ?? 0);
  $status = sanitize_string($_POST['status'] ?? '');
  $st=$pdo->prepare("UPDATE orders SET status=? WHERE id=?");
  $st->execute([$status,$id]);
  header('Location: orders.php?action=view&id='.$id); exit;
}

// listagem
admin_header('Pedidos');
if (!$canManageOrders) {
  echo '<div class="alert alert-warning mx-auto max-w-4xl mb-4"><i class="fa-solid fa-circle-info mr-2"></i>Alterações de status disponíveis apenas para administradores autorizados.</div>';
}
$q = trim((string)($_GET['q'] ?? ''));
$w=' WHERE 1=1 '; $p=[];
if ($q!==''){
  $w .= " AND (c.name LIKE ? OR o.id = ? ) ";
  $p = ["%$q%", (int)$q];
}
$sql="SELECT o.id,o.total,o.status,o.created_at,c.name AS customer_name FROM orders o LEFT JOIN customers c ON c.id=o.customer_id $w ORDER BY o.id DESC LIMIT 200";
$st=$pdo->prepare($sql); $st->execute($p);

echo '<div class="card">';
echo '<div class="card-title">Pedidos</div>';
echo '<div class="p-3 row gap"><form method="get" class="row gap search"><input class="input" name="q" value="'.sanitize_html($q).'" placeholder="Buscar por cliente ou #id"><button class="btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button></form></div>';
echo '<div class="p-3 overflow-x-auto"><table class="table"><thead><tr><th>#</th><th>Cliente</th><th>Total</th><th>Status</th><th>Quando</th><th></th></tr></thead><tbody>';
foreach($st as $r){
  echo '<tr>';
  echo '<td>#'.(int)$r['id'].'</td>';
  echo '<td>'.sanitize_html($r['customer_name']).'</td>';
  echo '<td>$ '.number_format((float)$r['total'],2,',','.').'</td>';
  echo '<td>'.status_badge($r['status']).'</td>';
  echo '<td>'.sanitize_html($r['created_at'] ?? '').'</td>';
  echo '<td><a class="btn" href="orders.php?action=view&id='.(int)$r['id'].'"><i class="fa-solid fa-eye"></i> Ver</a></td>';
  echo '</tr>';
}
echo '</tbody></table></div></div>';

admin_footer();
