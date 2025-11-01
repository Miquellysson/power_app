<?php
// lib/utils.php - Utilitários do sistema Get Power (com settings, upload de logo e helpers)

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
            'title' => 'Get Power',
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
   Admin helpers
   ========================================================================= */
if (!function_exists('set_admin_session')) {
    function set_admin_session(array $adminData) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $id    = isset($adminData['id']) ? (int)$adminData['id'] : 0;
        $email = $adminData['email'] ?? null;
        $role  = $adminData['role'] ?? 'admin';
        $name  = $adminData['name'] ?? null;

        $_SESSION['admin'] = [
            'id'    => $id,
            'email' => $email,
            'role'  => $role,
            'name'  => $name,
        ];

        // Mantém compatibilidade com verificações existentes
        $_SESSION['admin_id']      = $id ?: 1;
        $_SESSION['admin_user_id'] = $id ?: null;
        $_SESSION['admin_email']   = $email;
        $_SESSION['admin_role']    = $role;
        if ($name) {
            $_SESSION['admin_name'] = $name;
        }
    }
}

if (!function_exists('current_admin')) {
    function current_admin(): ?array {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!empty($_SESSION['admin']) && is_array($_SESSION['admin'])) {
            return $_SESSION['admin'];
        }
        if (!empty($_SESSION['admin_id'])) {
            return [
                'id'    => $_SESSION['admin_user_id'] ?? (int)$_SESSION['admin_id'],
                'email' => $_SESSION['admin_email'] ?? null,
                'role'  => $_SESSION['admin_role'] ?? 'admin',
                'name'  => $_SESSION['admin_name'] ?? null,
            ];
        }
        return null;
    }
}

if (!function_exists('current_admin_role')) {
    function current_admin_role(): string {
        $admin = current_admin();
        return $admin['role'] ?? 'admin';
    }
}

if (!function_exists('is_super_admin')) {
    function is_super_admin(): bool {
        return current_admin_role() === 'super_admin';
    }
}

if (!function_exists('require_super_admin')) {
    function require_super_admin(): void {
        if (!is_super_admin()) {
            admin_forbidden('Apenas super administradores podem executar esta ação.');
        }
    }
}

if (!function_exists('admin_forbidden')) {
    function admin_forbidden(string $message = 'Você não tem permissão para executar esta ação.'): void {
        http_response_code(403);
        if (function_exists('admin_header') && function_exists('admin_footer')) {
            admin_header('Acesso negado');
            echo '<div class="card p-6 mx-auto max-w-xl mt-10">';
            echo '<div class="card-title">Permissão negada</div>';
            echo '<div class="text-sm text-gray-600">'.sanitize_html($message).'</div>';
            echo '<div class="mt-4"><a class="btn" href="dashboard.php"><i class="fa-solid fa-arrow-left"></i> Voltar ao painel</a></div>';
            echo '</div>';
            admin_footer();
        } else {
            echo sanitize_html($message);
        }
        exit;
    }
}

if (!function_exists('admin_role_capabilities')) {
    function admin_role_capabilities(string $role): array {
        switch ($role) {
            case 'super_admin':
                return ['*'];
            case 'admin':
                return [
                    'manage_products',
                    'manage_categories',
                    'manage_orders',
                    'manage_customers',
                    'manage_settings',
                    'manage_payment_methods',
                    'manage_users',
                    'manage_builder',
                ];
            case 'manager':
                return [
                    'manage_products',
                    'manage_categories',
                    'manage_orders',
                    'manage_customers',
                ];
            case 'viewer':
            default:
                return [];
        }
    }
}

if (!function_exists('admin_can')) {
    function admin_can(string $capability): bool {
        if (is_super_admin()) {
            return true;
        }
        $role = current_admin_role();
        $caps = admin_role_capabilities($role);
        if (in_array('*', $caps, true)) {
            return true;
        }
        return in_array($capability, $caps, true);
    }
}

if (!function_exists('require_admin_capability')) {
    function require_admin_capability(string $capability): void {
        if (!admin_can($capability)) {
            admin_forbidden('Você não tem permissão para executar esta ação.');
        }
    }
}

if (!function_exists('normalize_hex_color')) {
    function normalize_hex_color(string $hex): string {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $hex = strtoupper(preg_replace('/[^0-9A-F]/i', '', $hex));
        if (strlen($hex) !== 6) {
            return '2060C8';
        }
        return $hex;
    }
}

if (!function_exists('adjust_color_brightness')) {
    function adjust_color_brightness(string $hex, float $factor): string {
        $hex = normalize_hex_color($hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $adjust = function ($channel) use ($factor) {
            if ($factor >= 0) {
                $channel = $channel + (255 - $channel) * $factor;
            } else {
                $channel = $channel * (1 + $factor);
            }
            return (int)max(0, min(255, round($channel)));
        };

        $r = $adjust($r);
        $g = $adjust($g);
        $b = $adjust($b);

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }
}

if (!function_exists('generate_brand_palette')) {
    function generate_brand_palette(string $baseColor): array {
        $base = '#'.normalize_hex_color($baseColor);
        return [
            '50'      => adjust_color_brightness($base, 0.85),
            '100'     => adjust_color_brightness($base, 0.7),
            '200'     => adjust_color_brightness($base, 0.5),
            '300'     => adjust_color_brightness($base, 0.3),
            '400'     => adjust_color_brightness($base, 0.15),
            '500'     => adjust_color_brightness($base, 0.05),
            '600'     => $base,
            '700'     => adjust_color_brightness($base, -0.15),
            '800'     => adjust_color_brightness($base, -0.25),
            '900'     => adjust_color_brightness($base, -0.35),
            'DEFAULT' => $base,
        ];
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

if (!function_exists('save_pwa_icon_upload')) {
    function save_pwa_icon_upload(array $file) {
        $validation = validate_file_upload($file, ['image/png'], 2 * 1024 * 1024);
        if (!$validation['success']) {
            return ['success' => false, 'message' => $validation['message'] ?? 'Arquivo inválido'];
        }

        $dir = __DIR__ . '/../storage/pwa';
        @mkdir($dir, 0775, true);

        $data = @file_get_contents($file['tmp_name']);
        if ($data === false) {
            return ['success' => false, 'message' => 'Falha ao ler o arquivo enviado.'];
        }

        $targets = [
            512 => $dir . '/icon-512.png',
            192 => $dir . '/icon-192.png',
            180 => $dir . '/icon-180.png',
        ];

        $generated = [];
        $canResize = function_exists('imagecreatefromstring') && function_exists('imagecreatetruecolor') && function_exists('imagepng');

        if ($canResize) {
            $src = @imagecreatefromstring($data);
            if ($src !== false) {
                $srcWidth  = imagesx($src);
                $srcHeight = imagesy($src);
                $square    = min($srcWidth, $srcHeight);
                foreach ($targets as $size => $path) {
                    $canvas = imagecreatetruecolor($size, $size);
                    imagealphablending($canvas, false);
                    imagesavealpha($canvas, true);
                    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                    imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);
                    imagecopyresampled(
                        $canvas,
                        $src,
                        0, 0,
                        ($srcWidth > $srcHeight) ? (int)(($srcWidth - $square) / 2) : 0,
                        ($srcHeight > $srcWidth) ? (int)(($srcHeight - $square) / 2) : 0,
                        $size, $size,
                        $square, $square
                    );
                    if (!@imagepng($canvas, $path, 9)) {
                        imagedestroy($canvas);
                        imagedestroy($src);
                        $canResize = false;
                        break;
                    }
                    $generated[] = $path;
                    imagedestroy($canvas);
                }
                imagedestroy($src);
            } else {
                $canResize = false;
            }
        }

        if (!$canResize) {
            $target512 = $targets[512];
            if (!@move_uploaded_file($file['tmp_name'], $target512)) {
                return ['success' => false, 'message' => 'Falha ao salvar ícone do app.'];
            }
            @copy($target512, $targets[192]);
            @copy($target512, $targets[180]);
        }

        setting_set('pwa_icon_last_update', (string)time());

        return ['success' => true];
    }
}

if (!function_exists('get_pwa_icon_paths')) {
    function get_pwa_icon_paths(): array {
        $defaults = [
            512 => 'assets/icons/farma-512.png',
        192 => 'assets/icons/farma-192.png',
        180 => 'assets/icons/farma-192.png',
        ];
        $paths = [];
        foreach ($defaults as $size => $fallback) {
            $custom = 'storage/pwa/icon-' . $size . '.png';
            $rel = $fallback;
            if (file_exists(__DIR__ . '/../' . $custom)) {
                $rel = $custom;
            }
            $paths[$size] = [
                'relative' => $rel,
                'absolute' => __DIR__ . '/../' . $rel
            ];
        }
        return $paths;
    }
}

if (!function_exists('get_pwa_icon_path')) {
    function get_pwa_icon_path(int $size = 512): string {
        $icons = get_pwa_icon_paths();
        return $icons[$size]['relative'] ?? '';
    }
}

if (!function_exists('pwa_icon_url')) {
    function pwa_icon_url(int $size = 512): string {
        $icons = get_pwa_icon_paths();
        if (!isset($icons[$size])) {
            return '';
        }
        $rel = $icons[$size]['relative'];
        $abs = $icons[$size]['absolute'];
        $url = '/' . ltrim($rel, '/');
        if (file_exists($abs)) {
            $url .= '?v=' . filemtime($abs);
        }
        return $url;
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

if (!function_exists('email_template_defaults')) {
    function email_template_defaults($storeName = null) {
        $info = store_info();
        $detectedName = $storeName ?: ($info['name'] ?? 'Sua Loja');

        $customerSubject = "Seu pedido {{order_id}} foi recebido - {$detectedName}";
        $customerBody = <<<HTML
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

        $adminSubject = "Novo pedido #{{order_id}} - {$detectedName}";
        $adminBody = <<<HTML
<h2>Novo pedido recebido</h2>
<p><strong>Loja:</strong> {{store_name}}</p>
<p><strong>Pedido:</strong> #{{order_id}}</p>
<p><strong>Cliente:</strong> {{customer_name}} &lt;{{customer_email}}&gt; — {{customer_phone}}</p>
<p><strong>Total:</strong> {{order_total}} &nbsp;|&nbsp; <strong>Pagamento:</strong> {{payment_method}}</p>
{{order_items}}
<p><strong>Endereço:</strong><br>{{shipping_address}}</p>
<p><strong>Observações:</strong> {{order_notes}}</p>
<p>Acesse o painel: <a href="{{admin_order_url}}">{{admin_order_url}}</a></p>
HTML;

        return [
            'customer_subject' => $customerSubject,
            'customer_body' => $customerBody,
            'admin_subject' => $adminSubject,
            'admin_body' => $adminBody,
        ];
    }
}

if (!function_exists('email_render_template')) {
    function email_render_template($template, array $vars) {
        $replacements = [];
        foreach ($vars as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string)$value;
        }
        return strtr((string)$template, $replacements);
    }
}

if (!function_exists('send_order_confirmation')) {
    function send_order_confirmation($order_id, $customer_email) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("
                SELECT o.*,
                       c.name   AS customer_name,
                       c.email  AS customer_email,
                       c.phone  AS customer_phone,
                       c.address AS customer_address,
                       c.city    AS customer_city,
                       c.state   AS customer_state,
                       c.zipcode AS customer_zipcode
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
            $storeInfo = store_info();
            $storeName = $storeInfo['name'] ?? 'Sua Loja';
            $currency = $storeInfo['currency'] ?? ($cfg['store']['currency'] ?? 'USD');

            $defaults = email_template_defaults($storeName);
            $subjectTpl = setting_get('email_customer_subject', $defaults['customer_subject']);
            $bodyTpl = setting_get('email_customer_body', $defaults['customer_body']);

            $itemsHtml = '<ul style="padding-left:18px;margin:0;">';
            foreach ($items as $item) {
                $nm = sanitize_html($item['name'] ?? '');
                $qt = (int)($item['qty'] ?? 0);
                $vl = (float)($item['price'] ?? 0);
                $itemsHtml .= '<li>'.$nm.' — Qtd: '.$qt.' — '.format_currency($vl * $qt, $currency).'</li>';
            }
            $itemsHtml .= '</ul>';

            $subtotalVal = (float)($order['subtotal'] ?? 0);
            $shippingVal = (float)($order['shipping_cost'] ?? 0);
            $totalVal    = (float)($order['total'] ?? 0);

            $baseUrl = rtrim($cfg['store']['base_url'] ?? '', '/');
            $trackToken = trim((string)($order['track_token'] ?? ''));
            $trackUrl = $trackToken ? ($baseUrl ? $baseUrl : '').'/index.php?route=track&code='.urlencode($trackToken) : '';
            if ($trackUrl && strpos($trackUrl, 'http') !== 0) {
                $trackUrl = '/' . ltrim($trackUrl, '/');
            }
            $trackLink = $trackUrl ? '<a href="'.sanitize_html($trackUrl).'">clique aqui</a>' : '';

            $paymentLabel = $order['payment_method'] ?? '-';
            try {
                $pm = $pdo->prepare("SELECT name FROM payment_methods WHERE code = ? LIMIT 1");
                $pm->execute([$order['payment_method'] ?? '']);
                $pmName = $pm->fetchColumn();
                if ($pmName) {
                    $paymentLabel = $pmName;
                }
            } catch (Throwable $e) {}

            $shippingParts = array_filter([
                $order['customer_name'] ?? '',
                $order['customer_address'] ?? '',
                $order['customer_city'] ?? '',
                $order['customer_state'] ?? '',
                $order['customer_zipcode'] ?? ''
            ], fn($v) => trim((string)$v) !== '');
            $shippingHtml = $shippingParts ? implode('<br>', array_map('sanitize_html', $shippingParts)) : '—';

            $vars = [
                'store_name' => sanitize_html($storeName),
                'customer_name' => sanitize_html($order['customer_name'] ?? ''),
                'customer_email' => sanitize_html($order['customer_email'] ?? $customer_email),
                'customer_phone' => sanitize_html($order['customer_phone'] ?? ''),
                'order_id' => (string)$order_id,
                'order_total' => format_currency($totalVal, $currency),
                'order_subtotal' => format_currency($subtotalVal, $currency),
                'order_shipping' => format_currency($shippingVal, $currency),
                'order_items' => $itemsHtml,
                'payment_method' => sanitize_html($paymentLabel),
                'payment_reference' => sanitize_html($order['payment_ref'] ?? ''),
                'order_notes' => sanitize_html($order['notes'] ?? '—'),
                'track_link' => $trackLink ?: sanitize_html($trackUrl),
                'track_url' => sanitize_html($trackUrl),
                'support_email' => sanitize_html($storeInfo['email'] ?? ($cfg['store']['support_email'] ?? '')),
                'shipping_address' => $shippingHtml,
            ];

            $subjectVars = $vars;
            $subjectVars['order_items'] = '';
            $subjectVars['track_link'] = $vars['track_url'];

            $subject = email_render_template($subjectTpl, $subjectVars);
            $body = email_render_template($bodyTpl, $vars);

            return send_email($customer_email, $subject, $body);
        } catch (Throwable $e) {
            error_log("Failed to send order confirmation: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('send_order_admin_alert')) {
    function send_order_admin_alert($order_id, $extraEmails = null) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("
                SELECT o.*,
                       c.name    AS customer_name,
                       c.email   AS customer_email,
                       c.phone   AS customer_phone,
                       c.address AS customer_address,
                       c.city    AS customer_city,
                       c.state   AS customer_state,
                       c.zipcode AS customer_zipcode
                FROM orders o
                LEFT JOIN customers c ON c.id = o.customer_id
                WHERE o.id = ?
            ");
            $stmt->execute([(int)$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) { return false; }

            $items = json_decode($order['items_json'] ?? '[]', true);
            if (!is_array($items)) { $items = []; }

            $cfg = cfg();
            $storeInfo = store_info();
            $storeName = $storeInfo['name'] ?? ($cfg['store']['name'] ?? 'Sua Loja');
            $currency = $storeInfo['currency'] ?? ($cfg['store']['currency'] ?? 'USD');

            $defaults = email_template_defaults($storeName);
            $subjectTpl = setting_get('email_admin_subject', $defaults['admin_subject']);
            $bodyTpl = setting_get('email_admin_body', $defaults['admin_body']);

            $itemsHtml = '<ul style="padding-left:18px;margin:0;">';
            foreach ($items as $item) {
                $nm = sanitize_html($item['name'] ?? '');
                $qt = (int)($item['qty'] ?? 0);
                $vl = (float)($item['price'] ?? 0);
                $itemsHtml .= '<li>'.$nm.' — Qtd: '.$qt.' — '.format_currency($vl * $qt, $currency).'</li>';
            }
            $itemsHtml .= '</ul>';

            $subtotalVal = (float)($order['subtotal'] ?? 0);
            $shippingVal = (float)($order['shipping_cost'] ?? 0);
            $totalVal    = (float)($order['total'] ?? 0);

            $baseUrl = rtrim($cfg['store']['base_url'] ?? '', '/');
            $adminOrderUrl = $baseUrl ? $baseUrl.'/admin.php?route=orders&action=view&id='.$order_id : 'admin.php?route=orders&action=view&id='.$order_id;

            $paymentLabel = $order['payment_method'] ?? '-';
            try {
                $pm = $pdo->prepare("SELECT name FROM payment_methods WHERE code = ? LIMIT 1");
                $pm->execute([$order['payment_method'] ?? '']);
                $pmName = $pm->fetchColumn();
                if ($pmName) {
                    $paymentLabel = $pmName;
                }
            } catch (Throwable $e) {}

            $shippingParts = array_filter([
                $order['customer_address'] ?? '',
                $order['customer_city'] ?? '',
                $order['customer_state'] ?? '',
                $order['customer_zipcode'] ?? ''
            ], fn($v) => trim((string)$v) !== '');
            $shippingHtml = $shippingParts ? implode('<br>', array_map('sanitize_html', $shippingParts)) : '—';

            $vars = [
                'store_name' => sanitize_html($storeName),
                'order_id' => (string)$order_id,
                'customer_name' => sanitize_html($order['customer_name'] ?? ''),
                'customer_email' => sanitize_html($order['customer_email'] ?? ''),
                'customer_phone' => sanitize_html($order['customer_phone'] ?? ''),
                'order_total' => format_currency($totalVal, $currency),
                'order_subtotal' => format_currency($subtotalVal, $currency),
                'order_shipping' => format_currency($shippingVal, $currency),
                'payment_method' => sanitize_html($paymentLabel),
                'payment_reference' => sanitize_html($order['payment_ref'] ?? ''),
                'order_items' => $itemsHtml,
                'order_notes' => sanitize_html($order['notes'] ?? '—'),
                'shipping_address' => $shippingHtml,
                'admin_order_url' => sanitize_html($adminOrderUrl),
            ];

            $subjectVars = $vars;
            $subjectVars['order_items'] = '';

            $subject = email_render_template($subjectTpl, $subjectVars);
            $body = email_render_template($bodyTpl, $vars);

            $recipients = [];
            if ($extraEmails) {
                if (is_array($extraEmails)) {
                    $recipients = array_merge($recipients, $extraEmails);
                } else {
                    $recipients[] = (string)$extraEmails;
                }
            }

            $supportEmail = $storeInfo['email'] ?? ($cfg['store']['support_email'] ?? null);
            if (!$supportEmail && defined('ADMIN_EMAIL')) {
                $supportEmail = ADMIN_EMAIL;
            }
            if ($supportEmail) {
                $recipients[] = $supportEmail;
            }

            $recipients = array_filter(array_unique(array_map('trim', $recipients)));
            $success = true;
            foreach ($recipients as $recipient) {
                if (!validate_email($recipient)) {
                    continue;
                }
                if (!send_email($recipient, $subject, $body)) {
                    $success = false;
                }
            }
            return $success;
        } catch (Throwable $e) {
            error_log("Failed to send admin alert: " . $e->getMessage());
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
            'name'   => setting_get('store_name',   $cfg['store']['name']   ?? 'Get Power'),
            'email'  => setting_get('store_email',  $cfg['store']['support_email'] ?? 'contato@example.com'),
            'phone'  => setting_get('store_phone',  $cfg['store']['phone']  ?? '(00) 00000-0000'),
            'addr'   => setting_get('store_address',$cfg['store']['address']?? 'Endereço não configurado'),
            'logo'   => get_logo_path(),
            'currency' => $cfg['store']['currency'] ?? 'BRL',
        ];
    }
}
