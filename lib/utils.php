<?php
// lib/utils.php - Utilitários do sistema Farma Fácil (com settings, upload de logo e helpers)

/* =========================================================================
   Carregamento de configuração (cfg)
   ========================================================================= */
if (!function_exists('cfg')) {
    function cfg() {
        static $config = null;
        if ($config === null) {
            // config.php retorna um array (além de definir constantes)
            $config = require __DIR__ . '/../config.php';
        }
        return $config;
    }
}

/* =========================================================================
   Internacionalização
   ========================================================================= */
if (!function_exists('lang')) {
    function lang($key = null) {
        static $dict = null;

        if ($dict === null) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            $lang = $_SESSION['lang'] ?? (defined('DEFAULT_LANG') ? DEFAULT_LANG : 'pt_BR');
            $lang_code = substr($lang, 0, 2); // pt_BR -> pt

            $lang_files = [
                'pt' => __DIR__ . '/../i18n/pt.php',
                'en' => __DIR__ . '/../i18n/en.php',
                'es' => __DIR__ . '/../i18n/es.php',
            ];

            $file = $lang_files[$lang_code] ?? $lang_files['pt'];
            $dict = file_exists($file) ? require $file : get_default_dict();
            $dict['_lang'] = $lang_code;
        }

        return $key === null ? $dict : ($dict[$key] ?? $key);
    }
}

if (!function_exists('set_lang')) {
    function set_lang($lang) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $allowed = ['pt', 'pt_BR', 'en', 'en_US', 'es', 'es_ES'];
        if (in_array($lang, $allowed, true)) {
            $_SESSION['lang'] = $lang;
        }
    }
}

if (!function_exists('t')) {
    function t($key) { return lang($key); }
}

if (!function_exists('get_default_dict')) {
    function get_default_dict() {
        return [
            'title' => 'Farma Fácil',
            'cart' => 'Carrinho',
            'search' => 'Buscar',
            'lang' => 'Idioma',
            'products' => 'Produtos',
            'subtotal' => 'Subtotal',
            'checkout' => 'Finalizar Compra',
            'name' => 'Nome',
            'email' => 'E-mail',
            'phone' => 'Telefone',
            'address' => 'Endereço',
            'city' => 'Cidade',
            'state' => 'Estado',
            'zipcode' => 'CEP',
            'customer_info' => 'Dados do Cliente',
            'payment_info' => 'Pagamento',
            'order_details' => 'Resumo do Pedido',
            'continue_shopping' => 'Continuar Comprando',
            'thank_you_order' => 'Obrigado pelo seu pedido!',
            'zelle' => 'Zelle',
            'venmo' => 'Venmo',
            'pix'   => 'PIX',
            'paypal'=> 'PayPal',
            'square'=> 'Square',
            'upload_receipt' => 'Enviar Comprovante',
            'place_order' => 'Finalizar Pedido',
            'add_to_cart' => 'Adicionar ao Carrinho',
            'order_received' => 'Pedido Recebido',
            'status' => 'Status',
            'pending' => 'Pendente',
            'processing' => 'Processando',
            'completed' => 'Concluído',
            'cancelled' => 'Cancelado',
        ];
    }
}

/* =========================================================================
   CSRF
   ========================================================================= */
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_check')) {
    function csrf_check($token) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
    }
}

/* =========================================================================
   PIX - Payload EMV
   ========================================================================= */
if (!function_exists('pix_payload')) {
    function pix_payload($pix_key, $merchant_name, $merchant_city, $amount = 0.00, $txid = null) {
        // Payload Format Indicator
        $payload = "000201";

        // Point of Initiation Method
        if ($amount > 0) { $payload .= "010212"; } else { $payload .= "010211"; }

        // Merchant Account Information (GUI + chave + TXID opcional)
        $gui = "br.gov.bcb.pix";
        // GUI (00) + chave (01) + (02)TXID opcional
        $ma = "00" . sprintf("%02d", strlen($gui)) . $gui
            . "01" . sprintf("%02d", strlen($pix_key)) . $pix_key;
        if ($txid) {
            $ma .= "02" . sprintf("%02d", strlen($txid)) . $txid;
        }
        // ID 26 => Merchant Account Info template
        $payload .= "26" . sprintf("%02d", strlen($ma)) . $ma;

        // MCC
        $payload .= "52040000";

        // Currency BRL
        $payload .= "5303986";

        // Amount
        if ($amount > 0) {
            $amount_str = number_format((float)$amount, 2, '.', '');
            $payload .= "54" . sprintf("%02d", strlen($amount_str)) . $amount_str;
        }

        // Country
        $payload .= "5802BR";

        // Merchant Name (sem acentos, máx 25)
        $mname = substr(remove_accents($merchant_name), 0, 25);
        $payload .= "59" . sprintf("%02d", strlen($mname)) . $mname;

        // Merchant City (sem acentos, máx 15)
        $mcity = substr(remove_accents($merchant_city), 0, 15);
        $payload .= "60" . sprintf("%02d", strlen($mcity)) . $mcity;

        // CRC16
        $payload .= "6304";
        $crc = crc16_ccitt($payload);
        $payload .= strtoupper(sprintf("%04X", $crc));

        return $payload;
    }
}

if (!function_exists('remove_accents')) {
    function remove_accents($str) {
        $accents = [
            'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A',
            'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a',
            'È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
            'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
            'Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
            'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
            'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
            'Ç'=>'C','ç'=>'c','Ñ'=>'N','ñ'=>'n'
        ];
        return strtr($str, $accents);
    }
}

if (!function_exists('crc16_ccitt')) {
    function crc16_ccitt($data) {
        $crc = 0xFFFF;
        $poly = 0x1021;

        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= (ord($data[$i]) << 8) & 0xFFFF;
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ $poly) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }
        return $crc & 0xFFFF;
    }
}

/* =========================================================================
   Validações e sanitização
   ========================================================================= */
if (!function_exists('validate_email')) {
    function validate_email($email) { return (bool)filter_var($email, FILTER_VALIDATE_EMAIL); }
}
if (!function_exists('validate_phone')) {
    function validate_phone($phone) {
        $clean = preg_replace('/\D+/', '', (string)$phone);
        return strlen($clean) >= 10;
    }
}
if (!function_exists('sanitize_string')) {
    function sanitize_string($str, $max_length = 255) {
        $clean = trim(strip_tags((string)$str));
        return mb_substr($clean, 0, $max_length, 'UTF-8');
    }
}
if (!function_exists('sanitize_html')) {
    function sanitize_html($html) {
        return htmlspecialchars((string)$html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

/* =========================================================================
   Uploads seguros (genérico) + upload específico de logo
   ========================================================================= */
if (!function_exists('validate_file_upload')) {
    function validate_file_upload($file, $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], $max_size = 2097152) {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $err = isset($file['error']) ? (int)$file['error'] : -1;
            return ['success' => false, 'message' => 'Erro no upload: ' . $err];
        }
        if ((int)$file['size'] > (int)$max_size) {
            return ['success' => false, 'message' => 'Arquivo muito grande (máx: ' . formatBytes($max_size) . ').'];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed_types, true)) {
            return ['success' => false, 'message' => 'Tipo de arquivo não permitido.'];
        }
        return ['success' => true, 'mime_type' => $mime];
    }
}

if (!function_exists('save_logo_upload')) {
    function save_logo_upload(array $file) {
        // Salva a logo em storage/logo/logo.(png|jpg|jpeg|webp) e retorna caminho relativo ("storage/logo/logo.png")
        $validation = validate_file_upload($file, ['image/jpeg','image/png','image/webp'], 2 * 1024 * 1024);
        if (!$validation['success']) {
            return ['success' => false, 'message' => $validation['message']];
        }
        $cfg = cfg();
        $dir = $cfg['paths']['logo'] ?? (__DIR__ . '/../storage/logo');
        @mkdir($dir, 0775, true);

        // extensão segura
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
            // mapear pelo mime
            $map = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            $ext = $map[$validation['mime_type']] ?? 'png';
        }

        $filename = 'logo.' . $ext;
        $destAbs  = rtrim($dir, '/\\') . '/' . $filename;
        $destRel  = 'storage/logo/' . $filename;

        if (!@move_uploaded_file($file['tmp_name'], $destAbs)) {
            return ['success' => false, 'message' => 'Falha ao mover arquivo de logo.'];
        }
        // opcionalmente: apagar outras extensões antigas para evitar conflito visual/cache
        foreach (['png','jpg','jpeg','webp'] as $e) {
            $p = rtrim($dir, '/\\') . '/logo.' . $e;
            if ($e !== $ext && file_exists($p)) { @unlink($p); }
        }
        // Grava a referência nas settings (logo_path)
        setting_set('store_logo', $destRel);

        return ['success' => true, 'path' => $destRel];
    }
}

if (!function_exists('get_logo_path')) {
    function get_logo_path() {
        $stored = (string)setting_get('store_logo', '');
        if ($stored && file_exists(__DIR__ . '/../' . $stored)) {
            return $stored;
        }
        // fallback: procurar logo física
        $candidates = [
            'storage/logo/logo.png',
            'storage/logo/logo.jpg',
            'storage/logo/logo.jpeg',
            'storage/logo/logo.webp',
        ];
        foreach ($candidates as $c) {
            if (file_exists(__DIR__ . '/../' . $c)) {
                return $c;
            }
        }
        return ''; // sem logo
    }
}

/* =========================================================================
   Helpers de formatação
   ========================================================================= */
if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = (float)$bytes;
        if ($bytes <= 0) return "0 B";
        $pow = floor(log($bytes, 1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1024 ** $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

if (!function_exists('format_currency')) {
    function format_currency($amount, $currency = 'BRL') {
        $amount = (float)$amount;
        switch ($currency) {
            case 'USD': return '$' . number_format($amount, 2, '.', ',');
            case 'EUR': return '€' . number_format($amount, 2, ',', '.');
            case 'BRL':
            default:    return 'R$ ' . number_format($amount, 2, ',', '.');
        }
    }
}

if (!function_exists('format_date')) {
    function format_date($date, $format = 'd/m/Y') {
        if (empty($date)) return '-';
        return date($format, strtotime($date));
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime($datetime, $format = 'd/m/Y H:i') {
        if (empty($datetime)) return '-';
        return date($format, strtotime($datetime));
    }
}

if (!function_exists('slugify')) {
    function slugify($text) {
        $text = remove_accents((string)$text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        return $text ?: 'n-a';
    }
}

/* =========================================================================
   Sistema de Notificações
   ========================================================================= */
if (!function_exists('send_notification')) {
    function send_notification($type, $title, $message, $data = null) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("INSERT INTO notifications (type, title, message, data, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([
                (string)$type,
                (string)$title,
                (string)$message,
                $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null
            ]);
            return true;
        } catch (Throwable $e) {
            error_log("Failed to send notification: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('get_unread_notifications')) {
    function get_unread_notifications($limit = 10) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT ?");
            $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('mark_notifications_read')) {
    function mark_notifications_read($ids = null) {
        try {
            $pdo = db();
            if ($ids === null) {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
                $stmt->execute();
            } else {
                if (!is_array($ids)) $ids = [$ids];
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders)");
                $stmt->execute(array_map('intval', $ids));
            }
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

/* =========================================================================
   E-mail
   ========================================================================= */
if (!function_exists('send_email')) {
    function send_email($to, $subject, $body, $from = null) {
        $to = (string)$to;
        $subject = (string)$subject;
        $body = (string)$body;
        if (!$from) {
            $config = cfg();
            $from = $config['store']['support_email'] ?? 'no-reply@localhost';
        }
        $headers = [
            'From: ' . $from,
            'Reply-To: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8'
        ];
        return @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $body, implode("\r\n", $headers));
    }
}

if (!function_exists('send_order_confirmation')) {
    function send_order_confirmation($order_id, $customer_email) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("
                SELECT o.*, c.name AS customer_name 
                FROM orders o 
                LEFT JOIN customers c ON c.id = o.customer_id 
                WHERE o.id = ?
            ");
            $stmt->execute([(int)$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) { return false; }

            $items = json_decode($order['items_json'] ?? '[]', true);
            if (!is_array($items)) $items = [];

            $cfg     = cfg();
            $store   = $cfg['store'] ?? [];
            $sname   = $store['name'] ?? 'Farma Fácil';

            $subject = "Pedido #{$order_id} - {$sname}";

            $body  = "<h2>Pedido Confirmado!</h2>";
            $body .= "<p>Olá " . sanitize_html($order['customer_name'] ?? '') . ",</p>";
            $body .= "<p>Seu pedido #{$order_id} foi recebido e está sendo processado.</p>";
            $body .= "<h3>Itens do Pedido:</h3><ul>";

            foreach ($items as $item) {
                $nm = sanitize_html($item['name'] ?? '');
                $qt = (int)($item['qty'] ?? 0);
                $vl = (float)($item['price'] ?? 0);
                $body .= "<li>{$nm} - Qtd: {$qt} - " . format_currency($vl * $qt, $store['currency'] ?? 'BRL') . "</li>";
            }
            $body .= "</ul>";

            $body .= "<p><strong>Total: " . format_currency((float)$order['total'], $store['currency'] ?? 'BRL') . "</strong></p>";
            $body .= "<p><strong>Forma de Pagamento:</strong> " . sanitize_html($order['payment_method'] ?? '-') . "</p>";
            $body .= "<p>Acompanhe seu pedido por aqui: <a href='".htmlspecialchars((cfg()['store']['base_url'] ?? ''), ENT_QUOTES, 'UTF-8')."/index.php?route=track&code=".sanitize_html($order['track_token'] ?? '')."'>rastreamento do pedido</a></p>";
            $body .= "<p>Em breve você receberá atualizações sobre o status do seu pedido.</p>";
            $body .= "<p>Obrigado pela preferência!<br>Equipe " . sanitize_html($sname) . "</p>";

            return send_email($customer_email, $subject, $body);
        } catch (Throwable $e) {
            error_log("Failed to send order confirmation: " . $e->getMessage());
            return false;
        }
    }
}

/* =========================================================================
   SETTINGS (chave/valor) - usados no Admin > Configurações
   ========================================================================= */
if (!function_exists('setting_get')) {
    function setting_get($key, $default = null) {
        try {
            $pdo = db();
            $st  = $pdo->prepare("SELECT svalue FROM settings WHERE skey = ?");
            $st->execute([(string)$key]);
            $v = $st->fetchColumn();
            return ($v === false) ? $default : $v;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('setting_set')) {
    function setting_set($key, $value) {
        $pdo = db();
        $st  = $pdo->prepare(
            "INSERT INTO settings (skey, svalue) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue), updated_at = NOW()"
        );
        return $st->execute([(string)$key, (string)$value]);
    }
}

/* =========================================================================
   Helper para carregar nome/contatos exibidos na loja (com fallback)
   ========================================================================= */
if (!function_exists('store_info')) {
    function store_info() {
        $cfg = cfg();
        return [
            'name'   => setting_get('store_name',   $cfg['store']['name']   ?? 'Farma Fácil'),
            'email'  => setting_get('store_email',  $cfg['store']['support_email'] ?? 'contato@example.com'),
            'phone'  => setting_get('store_phone',  $cfg['store']['phone']  ?? '(00) 00000-0000'),
            'addr'   => setting_get('store_address',$cfg['store']['address']?? 'Endereço não configurado'),
            'logo'   => get_logo_path(),
            'currency' => $cfg['store']['currency'] ?? 'BRL',
        ];
    }
}
