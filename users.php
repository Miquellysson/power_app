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
  admin_header('Novo usuário');
  echo '<div class="card"><div class="card-title">Criar usuário</div><div class="p-4">';
  echo '<form method="post" action="users.php?action=create"><input type="hidden" name="csrf" value="'.csrf_token().'">';
  echo '<div class="grid md:grid-cols-2 gap-3">';
  echo '<div class="field"><span>Nome</span><input class="input" name="name" required></div>';
  echo '<div class="field"><span>E-mail</span><input class="input" type="email" name="email" required></div>';
  echo '<div class="field"><span>Senha</span><input class="input" type="password" name="password" required></div>';
  echo '<div class="field"><span>Ativo</span><select class="select" name="active"><option value="1">Sim</option><option value="0">Não</option></select></div>';
  echo '</div><div class="pt-2"><button class="btn alt"><i class="fa-solid fa-floppy-disk"></i> Salvar</button> <a class="btn" href="users.php">Voltar</a></div></form>';
  echo '</div></div>';
  admin_footer(); exit;
}

if ($action==='create' && $_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
  $name = sanitize_string($_POST['name'] ?? '');
  $email= sanitize_string($_POST['email'] ?? '');
  $active= (int)($_POST['active'] ?? 1);
  $pass = (string)($_POST['password'] ?? '');
  if (!$name || !validate_email($email) || strlen($pass)<6) die('Dados inválidos');
  $hash = password_hash($pass, PASSWORD_BCRYPT);
  $st=$pdo->prepare("INSERT INTO users(name,email,pass,active,created_at) VALUES(?,?,?,?,NOW())");
  $st->execute([$name,$email,$hash,$active]);
  header('Location: users.php'); exit;
}

if ($action==='edit') {
  $id=(int)($_GET['id'] ?? 0);
  $st=$pdo->prepare("SELECT * FROM users WHERE id=?");
  $st->execute([$id]);
  $u=$st->fetch();
  if (!$u){ header('Location: users.php'); exit; }
  admin_header('Editar usuário');
  echo '<div class="card"><div class="card-title">Editar usuário</div><div class="p-4">';
  echo '<form method="post" action="users.php?action=update&id='.$id.'"><input type="hidden" name="csrf" value="'.csrf_token().'">';
  echo '<div class="grid md:grid-cols-2 gap-3">';
  echo '<div class="field"><span>Nome</span><input class="input" name="name" value="'.sanitize_html($u['name']).'" required></div>';
  echo '<div class="field"><span>E-mail</span><input class="input" type="email" name="email" value="'.sanitize_html($u['email']).'" required></div>';
  echo '<div class="field"><span>Nova senha (opcional)</span><input class="input" type="password" name="password" placeholder="Deixe em branco p/ manter"></div>';
  echo '<div class="field"><span>Ativo</span><select class="select" name="active"><option value="1" '.((int)$u['active']===1?'selected':'').'>Sim</option><option value="0" '.((int)$u['active']===0?'selected':'').'>Não</option></select></div>';
  echo '</div><div class="pt-2"><button class="btn alt"><i class="fa-solid fa-floppy-disk"></i> Atualizar</button> <a class="btn" href="users.php">Voltar</a></div></form>';
  echo '</div></div>';
  admin_footer(); exit;
}

if ($action==='update' && $_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
  $id=(int)($_GET['id'] ?? 0);
  $name = sanitize_string($_POST['name'] ?? '');
  $email= sanitize_string($_POST['email'] ?? '');
  $active= (int)($_POST['active'] ?? 1);
  $pass = (string)($_POST['password'] ?? '');
  if (!$name || !validate_email($email)) die('Dados inválidos');
  if ($pass!==''){
    $hash=password_hash($pass, PASSWORD_BCRYPT);
    $st=$pdo->prepare("UPDATE users SET name=?,email=?,pass=?,active=? WHERE id=?");
    $st->execute([$name,$email,$hash,$active,$id]);
  } else {
    $st=$pdo->prepare("UPDATE users SET name=?,email=?,active=? WHERE id=?");
    $st->execute([$name,$email,$active,$id]);
  }
  header('Location: users.php'); exit;
}

if ($action==='delete') {
  $id=(int)($_GET['id'] ?? 0);
  $csrf=$_GET['csrf'] ?? '';
  if (!csrf_check($csrf)) die('CSRF');
  $st=$pdo->prepare("DELETE FROM users WHERE id=?");
  $st->execute([$id]);
  header('Location: users.php'); exit;
}

// list
admin_header('Usuários');
$q = trim((string)($_GET['q'] ?? ''));
$w=' WHERE 1=1 '; $p=[];
if ($q!==''){ $w.=" AND (name LIKE ? OR email LIKE ?) "; $p=["%$q%","%$q%"]; }
$st=$pdo->prepare("SELECT id,name,email,active,created_at FROM users $w ORDER BY id DESC LIMIT 200");
$st->execute($p);
echo '<div class="card"><div class="card-title">Usuários</div>';
echo '<div class="p-3 row gap"><form class="row gap search"><input class="input" name="q" value="'.sanitize_html($q).'" placeholder="Buscar por nome/e-mail"><button class="btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button></form><a class="btn alt" href="users.php?action=new"><i class="fa-solid fa-plus"></i> Novo</a></div>';
echo '<div class="p-3 overflow-x-auto"><table class="table"><thead><tr><th>#</th><th>Nome</th><th>E-mail</th><th>Ativo</th><th>Criado</th><th></th></tr></thead><tbody>';
foreach($st as $u){
  echo '<tr>';
  echo '<td>'.(int)$u['id'].'</td>';
  echo '<td>'.sanitize_html($u['name']).'</td>';
  echo '<td>'.sanitize_html($u['email']).'</td>';
  echo '<td>'.((int)$u['active']?'<span class="badge ok">Sim</span>':'<span class="badge danger">Não</span>').'</td>';
  echo '<td>'.sanitize_html($u['created_at'] ?? '').'</td>';
  echo '<td><a class="btn" href="users.php?action=edit&id='.(int)$u['id'].'"><i class="fa-solid fa-pen"></i> Editar</a> <a class="btn" href="users.php?action=delete&id='.(int)$u['id'].'&csrf='.csrf_token().'" onclick="return confirm(\'Excluir usuário?\')"><i class="fa-solid fa-trash"></i> Excluir</a></td>';
  echo '</tr>';
}
echo '</tbody></table></div></div>';
admin_footer();
