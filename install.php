<?php
/**
 * install.php — Get Power (pasta /getpower)
 * - Cria/atualiza as tabelas
 * - Faz seed de admin, categorias e produtos demo
 * - Idempotente (pode rodar mais de uma vez)
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Carrega config (constantes DB_* e ADMIN_*) e conexão
$configData = require __DIR__ . '/config.php';
require __DIR__ . '/lib/db.php';

try {
  $pdo = db();

  // ===== USERS =====
  $pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL DEFAULT '',
    email VARCHAR(190) UNIQUE NOT NULL,
    pass VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'admin',
    active TINYINT(1) NOT NULL DEFAULT 1,
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
    price_compare DECIMAL(10,2) NULL,
    shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 7.00,
    stock INT NOT NULL DEFAULT 100,
    image_path VARCHAR(255) NULL,
    square_payment_link VARCHAR(255) NULL,
    stripe_payment_link VARCHAR(255) NULL,
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

  // ===== PAGE LAYOUTS (builder visual) =====
  $pdo->exec("CREATE TABLE IF NOT EXISTS page_layouts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_slug VARCHAR(100) NOT NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    content LONGTEXT,
    styles LONGTEXT,
    meta JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_layout_slug_status (page_slug, status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // ===== PAYMENT METHODS =====
  $pdo->exec("CREATE TABLE IF NOT EXISTS payment_methods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    instructions LONGTEXT NULL,
    settings JSON NULL,
    icon_path VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    require_receipt TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_payment_code (code)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // ===== ALTERs idempotentes para colunas faltantes =====
  $tables_columns = [
    'products'        => ['category_id','square_payment_link','stripe_payment_link','active','featured','meta_title','meta_description','updated_at','shipping_cost','price_compare'],
    'customers'       => ['city','state','zipcode','country'],
    'orders'          => ['shipping_cost','total','payment_status','admin_viewed','notes','updated_at','track_token'],
    'page_layouts'    => ['meta'],
    'payment_methods' => ['description','instructions','settings','icon_path','require_receipt','sort_order'],
    'users'           => ['name','role','active']
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
          case 'products.square_payment_link':
            $pdo->exec("ALTER TABLE products ADD COLUMN square_payment_link VARCHAR(255) NULL AFTER image_path");
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
          case 'products.stripe_payment_link':
            $pdo->exec("ALTER TABLE products ADD COLUMN stripe_payment_link VARCHAR(255) NULL AFTER square_payment_link");
            break;
          case 'products.shipping_cost':
            $pdo->exec("ALTER TABLE products ADD COLUMN shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 7.00 AFTER price");
            $pdo->exec("UPDATE products SET shipping_cost = 7.00 WHERE shipping_cost IS NULL");
            break;
          case 'products.price_compare':
            $pdo->exec("ALTER TABLE products ADD COLUMN price_compare DECIMAL(10,2) NULL AFTER price");
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
          case 'page_layouts.meta':
            $pdo->exec("ALTER TABLE page_layouts ADD COLUMN meta JSON AFTER styles");
            break;
          case 'payment_methods.description':
            $pdo->exec("ALTER TABLE payment_methods ADD COLUMN description TEXT NULL AFTER name");
            break;
          case 'payment_methods.instructions':
            $pdo->exec("ALTER TABLE payment_methods ADD COLUMN instructions LONGTEXT NULL AFTER description");
            break;
          case 'payment_methods.settings':
            $pdo->exec("ALTER TABLE payment_methods ADD COLUMN settings JSON NULL AFTER instructions");
            break;
          case 'payment_methods.icon_path':
            $pdo->exec("ALTER TABLE payment_methods ADD COLUMN icon_path VARCHAR(255) NULL AFTER settings");
            break;
          case 'payment_methods.require_receipt':
            $pdo->exec("ALTER TABLE payment_methods ADD COLUMN require_receipt TINYINT(1) DEFAULT 0 AFTER is_active");
            break;
          case 'payment_methods.sort_order':
            $pdo->exec("ALTER TABLE payment_methods ADD COLUMN sort_order INT DEFAULT 0 AFTER require_receipt");
            break;
          case 'users.name':
            $pdo->exec("ALTER TABLE users ADD COLUMN name VARCHAR(120) NOT NULL DEFAULT '' AFTER id");
            break;
          case 'users.role':
            $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'admin'");
            break;
          case 'users.active':
            $pdo->exec("ALTER TABLE users ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER role");
            break;
        }
      }
  }
}

  // ajustes de dados
  try {
    $pdo->exec("UPDATE users SET name = email WHERE (name IS NULL OR name = '') AND email <> ''");
  } catch (Throwable $e) {}
  try {
    $pdo->exec("UPDATE products SET shipping_cost = 7.00 WHERE shipping_cost IS NULL");
  } catch (Throwable $e) {}
  try {
    $orderIds = $pdo->query("SELECT id FROM orders WHERE track_token IS NULL OR track_token = ''")->fetchAll(PDO::FETCH_COLUMN);
    if ($orderIds) {
      $updTrack = $pdo->prepare("UPDATE orders SET track_token = ? WHERE id = ?");
      foreach ($orderIds as $orderId) {
        $token = bin2hex(random_bytes(16));
        $updTrack->execute([$token, (int)$orderId]);
      }
    }
  } catch (Throwable $e) {
    error_log('Falha ao gerar track_token: ' . $e->getMessage());
  }
  try {
    $existingSettings = [];
    try {
      $rows = $pdo->query("SELECT skey, svalue FROM settings");
      if ($rows) {
        foreach ($rows as $row) {
          if (isset($row['skey'])) {
            $existingSettings[$row['skey']] = $row['svalue'] ?? '';
          }
        }
      }
    } catch (Throwable $e) {}

    $storeCfg = $configData['store'] ?? [];
    $storeNameDefault = $existingSettings['store_name'] ?? ($storeCfg['name'] ?? 'Get Power');
    $storeEmailDefault = $existingSettings['store_email'] ?? ($storeCfg['support_email'] ?? 'contato@example.com');
    $storePhoneDefault = $existingSettings['store_phone'] ?? ($storeCfg['phone'] ?? '(00) 00000-0000');
    $storeAddressDefault = $existingSettings['store_address'] ?? ($storeCfg['address'] ?? 'Endereço não configurado');
    $footerDescriptionDefault = $existingSettings['footer_description'] ?? 'Sua farmácia online com experiência de app.';
    $footerCopyDefault = $existingSettings['footer_copy'] ?? '© {{year}} '.$storeNameDefault.'. Todos os direitos reservados.';
    $themeColorDefault = $existingSettings['theme_color'] ?? '#2060C8';
    $legacyBadge = trim((string)($existingSettings['home_featured_badge'] ?? ''));
    $featuredLabelDefault = $existingSettings['home_featured_label'] ?? 'Oferta destaque';
    $featuredBadgeTitleDefault = $existingSettings['home_featured_badge_title'] ?? ($existingSettings['home_featured_title'] ?? 'Seleção especial');
    $featuredBadgeTextDefault = $existingSettings['home_featured_badge_text'] ?? ($legacyBadge !== '' ? $legacyBadge : 'Selecionados com carinho para você');

    $emailCustomerSubjectDefault = "Seu pedido {{order_id}} foi recebido - {$storeNameDefault}";
    $emailCustomerBodyDefault = <<<HTML
<p>Olá {{customer_name}},</p>
<p>Recebemos seu pedido <strong>#{{order_id}}</strong> na {{store_name}}.</p>
<p><strong>Resumo do pedido:</strong></p>
{{order_items}}
<p><strong>Subtotal:</strong> {{order_subtotal}}<br>
<strong>Frete:</strong> {{order_shipping}}<br>
<strong>Total:</strong> {{order_total}}</p>
<p>Forma de pagamento: {{payment_method}}</p>
<p>Status e atualização: {{track_link}}</p>
<p>Qualquer dúvida, responda este e-mail ou fale com a gente em {{support_email}}.</p>
<p>Equipe {{store_name}}</p>
HTML;
    $emailAdminSubjectDefault = "Novo pedido #{{order_id}} - {$storeNameDefault}";
    $emailAdminBodyDefault = <<<HTML
<h2>Novo pedido recebido</h2>
<p><strong>Loja:</strong> {{store_name}}</p>
<p><strong>Pedido:</strong> #{{order_id}}</p>
<p><strong>Cliente:</strong> {{customer_name}} &lt;{{customer_email}}&gt; — {{customer_phone}}</p>
<p><strong>Total:</strong> {{order_total}} &nbsp;|&nbsp; <strong>Pagamento:</strong> {{payment_method}}</p>
{{order_items}}
<p><strong>Endereço:</strong><br>{{shipping_address}}</p>
<p><strong>Observações:</strong> {{order_notes}}</p>
<p>Painel: <a href="{{admin_order_url}}">{{admin_order_url}}</a></p>
HTML;

    $settingsDefaults = [
      'store_name'            => $storeNameDefault,
      'store_email'           => $storeEmailDefault,
      'store_phone'           => $storePhoneDefault,
      'store_address'         => $storeAddressDefault,
      'store_meta_title'      => $storeNameDefault.' | Loja',
      'home_hero_title'       => 'Tudo para sua saúde',
      'home_hero_subtitle'    => 'Experiência de app, rápida e segura.',
      'header_subline'        => 'Farmácia Online',
      'footer_title'          => $storeNameDefault,
      'footer_description'    => $footerDescriptionDefault,
      'footer_copy'           => $footerCopyDefault,
      'theme_color'           => $themeColorDefault,
      'home_featured_label'   => $featuredLabelDefault,
      'whatsapp_button_text'  => 'Fale com a gente',
      'whatsapp_message'      => 'Olá! Gostaria de tirar uma dúvida sobre os produtos.',
      'store_currency'        => 'USD',
      'pwa_name'              => $storeNameDefault,
      'pwa_short_name'        => 'Get Power',
      'home_featured_enabled' => '0',
      'home_featured_title'   => 'Ofertas em destaque',
      'home_featured_subtitle'=> 'Seleção especial com preços imperdíveis.',
      'home_featured_badge_title' => $featuredBadgeTitleDefault,
      'home_featured_badge_text'  => $featuredBadgeTextDefault,
      'email_customer_subject'=> $emailCustomerSubjectDefault,
      'email_customer_body'   => $emailCustomerBodyDefault,
      'email_admin_subject'   => $emailAdminSubjectDefault,
      'email_admin_body'      => $emailAdminBodyDefault,
    ];
    if ($settingsDefaults) {
      $keys = array_keys($settingsDefaults);
      $placeholders = implode(',', array_fill(0, count($keys), '?'));
      $existingStmt = $pdo->prepare("SELECT skey FROM settings WHERE skey IN ($placeholders)");
      $existingStmt->execute($keys);
      $existingKeys = $existingStmt->fetchAll(PDO::FETCH_COLUMN);
      $existingLookup = array_fill_keys($existingKeys, true);

      $insertStmt = $pdo->prepare("INSERT INTO settings (skey, svalue) VALUES (?, ?)");
      foreach ($settingsDefaults as $skey => $svalue) {
        if (!isset($existingLookup[$skey])) {
          $insertStmt->execute([$skey, $svalue]);
        }
      }
    }
  } catch (Throwable $e) {
    error_log('Seed settings falhou: ' . $e->getMessage());
  }

  // ===== Admin seed =====
  // As constantes ADMIN_EMAIL e ADMIN_PASS_HASH vêm do config.php
  $st = $pdo->prepare("INSERT IGNORE INTO users(email, pass, role) VALUES(?,?,?)");
  $st->execute([ADMIN_EMAIL, ADMIN_PASS_HASH, 'super_admin']);

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

  // ===== Seed payment methods =====
  try {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM payment_methods")->fetchColumn();
    if ($count === 0) {
      $paymentsCfg = $configData['payments'] ?? [];
      $defaults = [];

      if (!empty($paymentsCfg['pix'])) {
        $defaults[] = [
          'code' => 'pix',
          'name' => 'Pix',
          'instructions' => "Use o Pix para pagar seu pedido. Valor: {valor_pedido}.\\nChave: {pix_key}",
          'settings' => [
            'type' => 'pix',
            'account_label' => 'Chave Pix',
            'account_value' => $paymentsCfg['pix']['pix_key'] ?? '',
            'pix_key' => $paymentsCfg['pix']['pix_key'] ?? '',
            'merchant_name' => $paymentsCfg['pix']['merchant_name'] ?? '',
            'merchant_city' => $paymentsCfg['pix']['merchant_city'] ?? '',
            'currency' => 'BRL'
          ],
          'require_receipt' => 0,
          'sort_order' => 10
        ];
      }

      if (!empty($paymentsCfg['zelle'])) {
        $defaults[] = [
          'code' => 'zelle',
          'name' => 'Zelle',
          'instructions' => "Envie o valor de {valor_pedido} via Zelle para {account_value}. Anexe o comprovante se solicitado.",
          'settings' => [
            'type' => 'zelle',
            'account_label' => 'Conta Zelle',
            'account_value' => $paymentsCfg['zelle']['recipient_email'] ?? '',
            'recipient_name' => $paymentsCfg['zelle']['recipient_name'] ?? ''
          ],
          'require_receipt' => (int)($paymentsCfg['zelle']['require_receipt_upload'] ?? 1),
          'sort_order' => 20
        ];
      }

      if (!empty($paymentsCfg['venmo'])) {
        $defaults[] = [
          'code' => 'venmo',
          'name' => 'Venmo',
          'instructions' => "Pague {valor_pedido} via Venmo. Link: {venmo_link}.",
          'settings' => [
            'type' => 'venmo',
            'account_label' => 'Link Venmo',
            'venmo_link' => $paymentsCfg['venmo']['handle'] ?? ''
          ],
          'require_receipt' => 1,
          'sort_order' => 30
        ];
      }

      if (!empty($paymentsCfg['paypal'])) {
        $defaults[] = [
          'code' => 'paypal',
          'name' => 'PayPal',
          'instructions' => "Após finalizar, você será direcionado ao PayPal com o valor {valor_pedido}.",
          'settings' => [
            'type' => 'paypal',
            'business' => $paymentsCfg['paypal']['business'] ?? '',
            'account_value' => $paymentsCfg['paypal']['business'] ?? '',
            'currency' => $paymentsCfg['paypal']['currency'] ?? 'USD',
            'return_url' => $paymentsCfg['paypal']['return_url'] ?? '',
            'cancel_url' => $paymentsCfg['paypal']['cancel_url'] ?? ''
          ],
          'require_receipt' => 0,
          'sort_order' => 40
        ];
      }

      if (!empty($paymentsCfg['square'])) {
        $defaults[] = [
          'code' => 'square',
          'name' => 'Square',
          'instructions' => $paymentsCfg['square']['instructions'] ?? 'Abriremos o checkout Square em uma nova aba.',
          'settings' => [
            'type' => 'square',
            'mode' => 'square_product_link',
            'open_new_tab' => (bool)($paymentsCfg['square']['open_new_tab'] ?? true)
          ],
          'require_receipt' => 0,
          'sort_order' => 50
        ];
      }

      if ($defaults) {
        $ins = $pdo->prepare("INSERT INTO payment_methods(code,name,instructions,settings,require_receipt,sort_order) VALUES (?,?,?,?,?,?)");
        foreach ($defaults as $pm) {
          $settingsJson = json_encode($pm['settings'], JSON_UNESCAPED_UNICODE);
          $ins->execute([
            $pm['code'],
            $pm['name'],
            $pm['instructions'],
            $settingsJson,
            (int)$pm['require_receipt'],
            (int)$pm['sort_order']
          ]);
        }
      }
    }
  } catch (Throwable $e) {
    error_log('Seed payment_methods falhou: '.$e->getMessage());
  }

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
