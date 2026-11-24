<?php
// ============ CONEXIÓN A MYSQL (XAMPP) ============
$host = 'localhost';
$db   = 'netshield';
$user = 'root';
$pass = '';

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die(json_encode(["error" => "Error de conexión a MySQL: " . $e->getMessage()]));
}

// Conexión de SOLO LECTURA para el agente (agente_datos.php).
// Por defecto usa el mismo usuario (root) pero la sesión se abre en modo solo lectura.
// Opcional (más seguro): crea un usuario con solo SELECT (ver database.sql) y ponlo aquí.
define('DB_RO_USER', '');
define('DB_RO_PASS', '');

// ============ AGENTE DE IA ============
// Opción GRATIS (recomendada): Google Gemini. Clave gratis en https://aistudio.google.com/apikey
// Pega tu clave entre las comillas. NO la subas a GitHub.
define('IA_PROVEEDOR', 'gemini');            // 'gemini' (gratis) o 'anthropic' (de pago)
define('IA_API_KEY', 'PEGA_AQUI_TU_CLAVE');
define('IA_MODELO', 'gemini-3.8-flash');   // si Google lo cambia, el mensaje de error te dice cuál usar
define('IA_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models');
// Si prefieres Anthropic: IA_PROVEEDOR 'anthropic', IA_MODELO 'claude-sonnet-5-5',
// IA_API_URL 'https://api.anthropic.com/v1/messages' y tu clave sk-ant-...

// Solo si en XAMPP/Windows sale "SSL certificate problem": ruta a un cacert.pem
// Ejemplo: 'C:/xampp/apache/bin/curl-ca-bundle.crt'
define('IA_CA_BUNDLE', '');
?>
