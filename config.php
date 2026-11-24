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
define('DB_RO_USER', '');
define('DB_RO_PASS', '');

// ============ AGENTE DE IA ============
define('IA_PROVEEDOR', 'gemini');            
define('IA_API_KEY', 'PEGA_AQUI_TU_CLAVE');
define('IA_MODELO', 'gemini-3.8-flash');  
define('IA_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models');

define('IA_CA_BUNDLE', '');
?>
