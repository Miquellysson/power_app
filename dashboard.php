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

admin_header('Dashboard');

// KPIs
$counts = ['orders'=>0,'customers'=>0,'products'=>0,'categories'=>0];
try{ $counts['orders']     = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(); }catch(Throwable $e){}
try{ $counts['customers']  = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn(); }catch(Throwable $e){}
try{ $counts['products']   = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn(); }catch(Throwable $e){}
try{ $counts['categories'] = (int)$pdo->query("SELECT COUNT(*) FROM categories WHERE active=1")->fetchColumn(); }catch(Throwable $e){}

echo '<div class="kpis">';
echo '  <div class="kpi"><div class="icon"><i class="fa-solid fa-receipt"></i></div><div><div class="val">'.$counts['orders'].'</div><div class="lbl">Pedidos</div></div></div>';
echo '  <div class="kpi"><div class="icon"><i class="fa-solid fa-users"></i></div><div><div class="val">'.$counts['customers'].'</div><div class="lbl">Clientes</div></div></div>';
echo '  <div class="kpi"><div class="icon"><i class="fa-solid fa-pills"></i></div><div><div class="val">'.$counts['products'].'</div><div class="lbl">Produtos ativos</div></div></div>';
echo '  <div class="kpi"><div class="icon"><i class="fa-solid fa-tags"></i></div><div><div class="val">'.$counts['categories'].'</div><div class="lbl">Categorias</div></div></div>';
echo '</div>';

// Últimos pedidos
echo '<div class="card"><div class="card-title">Últimos pedidos</div><div class="p-3 overflow-x-auto">';
try{
  $st=$pdo->query("SELECT o.id,o.total,o.status,o.created_at,c.name AS customer_name FROM orders o LEFT JOIN customers c ON c.id=o.customer_id ORDER BY o.id DESC LIMIT 10");
  echo '<table class="table"><thead><tr><th>#</th><th>Cliente</th><th>Total</th><th>Status</th><th>Quando</th><th></th></tr></thead><tbody>';
  foreach($st as $row){
    $badge = '<span class="badge">'.sanitize_html($row['status']).'</span>';
    if ($row['status']==='paid') $badge='<span class="badge ok">Pago</span>';
    elseif ($row['status']==='pending') $badge='<span class="badge warn">Pendente</span>';
    elseif ($row['status']==='canceled') $badge='<span class="badge danger">Cancelado</span>';
    echo '<tr>';
    echo '<td>#'.(int)$row['id'].'</td>';
    echo '<td>'.sanitize_html($row['customer_name'] ?: '-').'</td>';
    echo '<td>$ '.number_format((float)$row['total'],2,',','.').'</td>';
    echo '<td>'.$badge.'</td>';
    echo '<td>'.sanitize_html($row['created_at'] ?? '').'</td>';
    echo '<td><a class="btn" href="orders.php?action=view&id='.(int)$row['id'].'"><i class="fa-solid fa-eye"></i> Ver</a></td>';
    echo '</tr>';
  }
  echo '</tbody></table>';
}catch(Throwable $e){
  echo '<div class="p-3 text-sm text-red-600">Erro ao carregar pedidos.</div>';
}
echo '</div></div>';

admin_footer();
