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

if ($action==='new') {
  admin_header('Nova categoria');
  echo '<div class="card"><div class="card-title">Criar categoria</div><div class="p-4">';
  echo '<form method="post" action="categories.php?action=create"><input type="hidden" name="csrf" value="'.csrf_token().'">';
  echo '<div class="grid md:grid-cols-3 gap-3">';
  echo '<div class="field"><span>Nome</span><input class="input" name="name" required></div>';
  echo '<div class="field"><span>Ordem</span><input class="input" type="number" name="sort_order" value="0"></div>';
  echo '<div class="field"><span>Ativa</span><select class="select" name="active"><option value="1">Sim</option><option value="0">Não</option></select></div>';
  echo '</div><div class="pt-2"><button class="btn alt">Salvar</button> <a class="btn" href="categories.php">Voltar</a></div></form>';
  echo '</div></div>';
  admin_footer(); exit;
}

if ($action==='create' && $_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
  $name = sanitize_string($_POST['name'] ?? '');
  $sort = (int)($_POST['sort_order'] ?? 0);
  $active= (int)($_POST['active'] ?? 1);
  $st=$pdo->prepare("INSERT INTO categories(name,sort_order,active,created_at) VALUES(?,?,?,NOW())");
  $st->execute([$name,$sort,$active]);
  header('Location: categories.php'); exit;
}

if ($action==='edit') {
  $id=(int)($_GET['id'] ?? 0);
  $st=$pdo->prepare("SELECT * FROM categories WHERE id=?");
  $st->execute([$id]);
  $c=$st->fetch();
  if (!$c){ header('Location: categories.php'); exit; }
  admin_header('Editar categoria');
  echo '<div class="card"><div class="card-title">Editar categoria</div><div class="p-4">';
  echo '<form method="post" action="categories.php?action=update&id='.$id.'"><input type="hidden" name="csrf" value="'.csrf_token().'">';
  echo '<div class="grid md:grid-cols-3 gap-3">';
  echo '<div class="field"><span>Nome</span><input class="input" name="name" value="'.sanitize_html($c['name']).'" required></div>';
  echo '<div class="field"><span>Ordem</span><input class="input" type="number" name="sort_order" value="'.(int)$c['sort_order'].'"></div>';
  echo '<div class="field"><span>Ativa</span><select class="select" name="active"><option value="1" '.((int)$c['active']===1?'selected':'').'>Sim</option><option value="0" '.((int)$c['active']===0?'selected':'').'>Não</option></select></div>';
  echo '</div><div class="pt-2"><button class="btn alt">Atualizar</button> <a class="btn" href="categories.php">Voltar</a></div></form>';
  echo '</div></div>';
  admin_footer(); exit;
}

if ($action==='update' && $_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
  $id=(int)($_GET['id'] ?? 0);
  $name = sanitize_string($_POST['name'] ?? '');
  $sort = (int)($_POST['sort_order'] ?? 0);
  $active= (int)($_POST['active'] ?? 1);
  $st=$pdo->prepare("UPDATE categories SET name=?,sort_order=?,active=? WHERE id=?");
  $st->execute([$name,$sort,$active,$id]);
  header('Location: categories.php'); exit;
}

if ($action==='delete') {
  $id=(int)($_GET['id'] ?? 0);
  $csrf=$_GET['csrf'] ?? '';
  if (!csrf_check($csrf)) die('CSRF');
  $st=$pdo->prepare("DELETE FROM categories WHERE id=?");
  $st->execute([$id]);
  header('Location: categories.php'); exit;
}

admin_header('Categorias');
$q = trim((string)($_GET['q'] ?? ''));
$w=' WHERE 1=1 '; $p=[];
if ($q!==''){ $w.=" AND (name LIKE ?) "; $p=["%$q%"]; }
$st=$pdo->prepare("SELECT * FROM categories $w ORDER BY sort_order, name LIMIT 200");
$st->execute($p);
echo '<div class="card"><div class="card-title">Categorias</div>';
echo '<div class="p-3 row gap"><form class="row gap search"><input class="input" name="q" value="'.sanitize_html($q).'" placeholder="Buscar por nome"><button class="btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button></form><a class="btn alt" href="categories.php?action=new"><i class="fa-solid fa-plus"></i> Nova</a></div>';
echo '<div class="p-3 overflow-x-auto"><table class="table"><thead><tr><th>#</th><th>Nome</th><th>Ordem</th><th>Ativa</th><th></th></tr></thead><tbody>';
foreach($st as $c){
  echo '<tr>';
  echo '<td>'.(int)$c['id'].'</td>';
  echo '<td>'.sanitize_html($c['name']).'</td>';
  echo '<td>'.(int)$c['sort_order'].'</td>';
  echo '<td>'.((int)$c['active']?'<span class="badge ok">Sim</span>':'<span class="badge danger">Não</span>').'</td>';
  echo '<td><a class="btn" href="categories.php?action=edit&id='.(int)$c['id'].'"><i class="fa-solid fa-pen"></i> Editar</a> <a class="btn" href="categories.php?action=delete&id='.(int)$c['id'].'&csrf='.csrf_token().'" onclick="return confirm(\'Excluir categoria?\')"><i class="fa-solid fa-trash"></i> Excluir</a></td>';
  echo '</tr>';
}
echo '</tbody></table></div></div>';
admin_footer();
