<?php
/**
 * install.php — Farma Fácil (pasta /farmafixed)
 * - Cria/atualiza as tabelas
 * - Faz seed de admin, categorias e produtos demo
 * - Idempotente (pode rodar mais de uma vez)
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Carrega config (constantes DB_* e ADMIN_*) e conexão
require __DIR__ . '/config.php';
require __DIR__ . '/lib/db.php';

try {
  $pdo = db();

  // ===== USERS =====
  $pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) UNIQUE NOT NULL,
    pass VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // ===== CATEGORIES =====
  $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    image_path VARCHAR(255) NULL,
    active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // ===== PRODUCTS =====
  $pdo->exec("CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NULL,
    sku VARCHAR(100) UNIQUE,
    name VARCHAR(190) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 100,
    image_path VARCHAR(255) NULL,
    active TINYINT(1) DEFAULT 1,
    featured TINYINT(1) DEFAULT 0,
    meta_title VARCHAR(255) NULL,
    meta_description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // ===== CUSTOMERS =====
  $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190),
    email VARCHAR(190),
    phone VARCHAR(60),
    address VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(50),
    zipcode VARCHAR(20),
    country VARCHAR(50) DEFAULT 'BR',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // ===== ORDERS =====
  $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    items_json LONGTEXT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    shipping_cost DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(40) NOT NULL,
    payment_ref TEXT,
    payment_status VARCHAR(20) DEFAULT 'pending',
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    track_token VARCHAR(64) DEFAULT NULL,
    zelle_receipt VARCHAR(255),
    notes TEXT,
    admin_viewed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // ===== ORDER ITEMS (compat com diag.php e futuras consultas) =====
  $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NULL,
    name VARCHAR(190) NOT NULL,
    sku VARCHAR(100) NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // ===== NOTIFICATIONS =====
  // Em MariaDB, JSON costuma ser alias de LONGTEXT. Se sua versão não suportar JSON, troque para LONGTEXT.
  $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    data JSON,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // ===== SETTINGS (chave/valor) =====
  $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    skey VARCHAR(191) NOT NULL,
    svalue LONGTEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_settings_skey (skey)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // ===== ALTERs idempotentes para colunas faltantes =====
  $tables_columns = [
    'products'  => ['category_id','active','featured','meta_title','meta_description','updated_at'],
    'customers' => ['city','state','zipcode','country'],
    'orders'    => ['shipping_cost','total','payment_status','admin_viewed','notes','updated_at','track_token']
  ];

  foreach ($tables_columns as $table => $columns) {
    $existing_cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($columns as $col) {
      if (!in_array($col, $existing_cols, true)) {
        switch ("$table.$col") {
          case 'products.category_id':
            $pdo->exec("ALTER TABLE products ADD COLUMN category_id INT NULL AFTER id");
            break;
          case 'products.active':
            $pdo->exec("ALTER TABLE products ADD COLUMN active TINYINT(1) DEFAULT 1");
            break;
          case 'products.featured':
            $pdo->exec("ALTER TABLE products ADD COLUMN featured TINYINT(1) DEFAULT 0");
            break;
          case 'products.meta_title':
            $pdo->exec("ALTER TABLE products ADD COLUMN meta_title VARCHAR(255) NULL");
            break;
          case 'products.meta_description':
            $pdo->exec("ALTER TABLE products ADD COLUMN meta_description VARCHAR(255) NULL");
            break;
          case 'products.updated_at':
            $pdo->exec("ALTER TABLE products ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            break;
          case 'customers.city':
            $pdo->exec("ALTER TABLE customers ADD COLUMN city VARCHAR(100)");
            break;
          case 'customers.state':
            $pdo->exec("ALTER TABLE customers ADD COLUMN state VARCHAR(50)");
            break;
          case 'customers.zipcode':
            $pdo->exec("ALTER TABLE customers ADD COLUMN zipcode VARCHAR(20)");
            break;
          case 'customers.country':
            $pdo->exec("ALTER TABLE customers ADD COLUMN country VARCHAR(50) DEFAULT 'BR'");
            break;
          case 'orders.shipping_cost':
            $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_cost DECIMAL(10,2) DEFAULT 0.00");
            break;
          case 'orders.total':
            $pdo->exec("ALTER TABLE orders ADD COLUMN total DECIMAL(10,2) NOT NULL DEFAULT 0.00");
            break;
          case 'orders.payment_status':
            $pdo->exec("ALTER TABLE orders ADD COLUMN payment_status VARCHAR(20) DEFAULT 'pending'");
            break;
          case 'orders.admin_viewed':
            $pdo->exec("ALTER TABLE orders ADD COLUMN admin_viewed TINYINT(1) DEFAULT 0");
            break;
          case 'orders.notes':
            $pdo->exec("ALTER TABLE orders ADD COLUMN notes TEXT");
            break;
          case 'orders.updated_at':
            $pdo->exec("ALTER TABLE orders ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            break;
          case 'orders.track_token':
            $pdo->exec("ALTER TABLE orders ADD COLUMN track_token VARCHAR(64) DEFAULT NULL");
            break;
        }
      }
    }
  }

  // ===== Admin seed =====
  // As constantes ADMIN_EMAIL e ADMIN_PASS_HASH vêm do config.php
  $st = $pdo->prepare("INSERT IGNORE INTO users(email, pass, role) VALUES(?,?,?)");
  $st->execute([ADMIN_EMAIL, ADMIN_PASS_HASH, 'admin']);

  // ===== Seed categories =====
  $categories = [
    ['Analgésicos', 'analgesicos', 'Medicamentos para alívio de dores e febres'],
    ['Antibióticos', 'antibioticos', 'Medicamentos para combate a infecções'],
    ['Anti-inflamatórios', 'anti-inflamatorios', 'Medicamentos para redução de inflamações'],
    ['Suplementos', 'suplementos', 'Vitaminas e suplementos alimentares'],
    ['Digestivos', 'digestivos', 'Medicamentos para problemas digestivos'],
    ['Cardiovasculares', 'cardiovasculares', 'Medicamentos para coração e pressão'],
    ['Respiratórios', 'respiratorios', 'Medicamentos para problemas respiratórios'],
    ['Dermatológicos', 'dermatologicos', 'Medicamentos para pele'],
    ['Anticoncepcionais', 'anticoncepcionais', 'Medicamentos anticoncepcionais'],
  ];
  $cat_ins = $pdo->prepare("INSERT IGNORE INTO categories(name, slug, description) VALUES(?,?,?)");
  foreach ($categories as $cat) { $cat_ins->execute($cat); }

  // Mapa slug->id
  $cat_map = [];
  $cats = $pdo->query("SELECT id, slug FROM categories")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($cats as $c) { $cat_map[$c['slug']] = (int)$c['id']; }

  // ===== Seed products =====
  $meds = [
    ['FF-0001','Paracetamol 750mg','Analgésico e antitérmico', 12.90, 'analgesicos'],
    ['FF-0002','Ibuprofeno 400mg','Anti-inflamatório não esteroidal', 18.50, 'anti-inflamatorios'],
    ['FF-0003','Amoxicilina 500mg','Antibiótico (venda controlada)', 34.90, 'antibioticos'],
    ['FF-0004','Omeprazol 20mg','Inibidor de bomba de prótons', 22.00, 'digestivos'],
    ['FF-0005','Loratadina 10mg','Antialérgico', 15.75, 'respiratorios'],
    ['FF-0006','Dipirona 1g','Analgésico/antitérmico', 9.90, 'analgesicos'],
    ['FF-0007','Losartana 50mg','Anti-hipertensivo', 28.00, 'cardiovasculares'],
    ['FF-0008','Metformina 850mg','Antidiabético', 29.90, 'cardiovasculares'],
    ['FF-0009','Vitamina C 500mg','Suplemento vitamínico', 11.50, 'suplementos'],
    ['FF-0010','Azitromicina 500mg','Antibiótico (venda controlada)', 39.90, 'antibioticos'],
    ['FF-0011','Protetor Solar FPS 60','Proteção solar dermatológica', 45.90, 'dermatologicos'],
    ['FF-0012','Xarope para Tosse','Medicamento expectorante', 18.90, 'respiratorios'],
  ];

  $ins = $pdo->prepare("INSERT IGNORE INTO products(sku, name, description, price, stock, image_path, category_id) VALUES(?,?,?,?,100,?,?)");
  foreach ($meds as $m) {
    $sku = $m[0];
    $category_id = $cat_map[$m[4]] ?? null;
    $img = "https://picsum.photos/seed/".urlencode($sku)."/600/600";
    $ins->execute([$m[0], $m[1], $m[2], $m[3], $img, $category_id]);
  }

  // Produtos sem categoria => atribui e define imagem
  $rows = $pdo->query("SELECT id, sku FROM products WHERE category_id IS NULL")->fetchAll(PDO::FETCH_ASSOC);
  if ($rows) {
    $up = $pdo->prepare("UPDATE products SET image_path=?, category_id=? WHERE id=?");
    $cat_ids = array_values($cat_map);
    foreach ($rows as $r) {
      $img = "https://picsum.photos/seed/".urlencode($r['sku'] ?: ('prod'.$r['id']))."/600/600";
      $random_cat = $cat_ids[array_rand($cat_ids)];
      $up->execute([$img, $random_cat, $r['id']]);
    }
  }

  // Ajusta total em pedidos antigos
  $pdo->exec("UPDATE orders SET total = subtotal + shipping_cost WHERE total = 0");

  // ===== Saída =====
  $created_count = count($categories);
  $updated_count = $rows ? count($rows) : 0;

  echo "✅ OK. Banco criado/atualizado com sistema de categorias e notificações. <br>";
  echo "📋 Categorias criadas: {$created_count} <br>";
  echo "💊 Produtos atualizados (sem categoria previa): {$updated_count} <br><br>";
  echo "👉 Acesse <a href='/index.php'>/index.php (loja)</a> ";
  echo "ou <a href='/admin.php'>/admin.php (painel)</a>";

} catch (Throwable $e) {
  http_response_code(500);
  echo "❌ Erro ao instalar: " . htmlspecialchars($e->getMessage());
}
