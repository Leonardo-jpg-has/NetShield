<?php
require_once 'config.php';

const NS_NIVELES = ['RIESGO ALTO', 'RIESGO MEDIO', 'SEGURO'];
const NS_CANALES = ['WhatsApp', 'SMS', 'Correo', 'URL', 'QR'];

function conexion_lectura() {
    static $c = null;
    if ($c) return $c;
    global $dsn, $user, $pass;
    $u = (defined('DB_RO_USER') && DB_RO_USER !== '') ? DB_RO_USER : $user;
    $p = (defined('DB_RO_USER') && DB_RO_USER !== '') ? DB_RO_PASS : $pass;
    $c = new PDO($dsn, $u, $p, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($c->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $c->exec('PRAGMA query_only = ON');
    } else {
        $c->exec('SET SESSION TRANSACTION READ ONLY');
    }
    return $c;
}

// ---- Traducciones entre el modelo del Sprint 3 y lo que muestra la página ----
// semaforo (Rojo/Amarillo/Verde) <-> estado de riesgo (RIESGO ALTO/MEDIO, SEGURO)
function estado_de_semaforo($s) {
    return $s === 'Rojo' ? 'RIESGO ALTO' : ($s === 'Amarillo' ? 'RIESGO MEDIO' : 'SEGURO');
}
function semaforo_de_estado($e) {
    return $e === 'RIESGO ALTO' ? 'Rojo' : ($e === 'RIESGO MEDIO' ? 'Amarillo' : 'Verde');
}
function codigo_scan($idAnalisis) { return '#NS-' . str_pad((string)(int)$idAnalisis, 4, '0', STR_PAD_LEFT); }
function fb_de($v) { return $v === 'Correcto' ? 'CORRECTO' : ($v === 'Incorrecto' ? 'INCORRECTO' : null); }

// Una fila de analisis_ia (+ su contenido y su feedback) convertida al formato que usa la página
function fila_para_pagina($f) {
    $dato = (string)$f['dato_ingresado'];
    return [
        'id' => (int)$f['id_analisis'],
        'id_scan' => codigo_scan($f['id_analisis']),
        'canal' => $f['canal'],
        'contenido_defanged' => mb_substr($dato, 0, 45) . (mb_strlen($dato) > 45 ? '...' : ''),
        'estado_riesgo' => estado_de_semaforo($f['semaforo']),
        'score' => (int)$f['score_riesgo'],
        'latencia' => number_format((float)$f['tiempo_respuesta'], 2) . 's',
        'fecha' => $f['fecha'],
        'feedback' => fb_de($f['veredicto_usuario']),
    ];
}

const SQL_BASE_ANALISIS = "FROM analisis_ia a
    JOIN contenido_sospechoso c ON c.id_contenido = a.id_contenido
    LEFT JOIN retroalimentacion r ON r.id_analisis = a.id_analisis";
const SQL_CAMPOS_ANALISIS = "a.id_analisis, a.score_riesgo, a.semaforo, a.tiempo_respuesta, c.canal, c.dato_ingresado, c.fecha_hora AS fecha, r.veredicto_usuario";

function datos_cuentas($c, $sql, $params = []) {
    $q = $c->prepare($sql);
    $q->execute($params);
    $out = [];
    foreach ($q->fetchAll() as $f) $out[$f['k']] = (int)$f['n'];
    return $out;
}

function datos_resumen($c) {
    $total = (int)$c->query("SELECT COUNT(*) FROM analisis_ia")->fetchColumn();
    $sem = datos_cuentas($c, "SELECT semaforo AS k, COUNT(*) AS n FROM analisis_ia GROUP BY semaforo");
    $can = datos_cuentas($c, "SELECT c.canal AS k, COUNT(*) AS n FROM analisis_ia a JOIN contenido_sospechoso c ON c.id_contenido = a.id_contenido GROUP BY c.canal");
    $fb  = datos_cuentas($c, "SELECT veredicto_usuario AS k, COUNT(*) AS n FROM retroalimentacion GROUP BY veredicto_usuario");
    $alert = datos_cuentas($c, "SELECT r.veredicto_usuario AS k, COUNT(*) AS n FROM retroalimentacion r JOIN analisis_ia a ON a.id_analisis = r.id_analisis WHERE a.semaforo IN (?, ?) GROUP BY r.veredicto_usuario", ['Rojo', 'Amarillo']);
    $segur = datos_cuentas($c, "SELECT r.veredicto_usuario AS k, COUNT(*) AS n FROM retroalimentacion r JOIN analisis_ia a ON a.id_analisis = r.id_analisis WHERE a.semaforo = ? GROUP BY r.veredicto_usuario", ['Verde']);
    $fpSobre = ($alert['Correcto'] ?? 0) + ($alert['Incorrecto'] ?? 0);
    $fnSobre = ($segur['Correcto'] ?? 0) + ($segur['Incorrecto'] ?? 0);
    $prom = $c->query("SELECT AVG(score_riesgo) FROM analisis_ia")->fetchColumn();
    $ult = $c->query("SELECT a.id_analisis, a.semaforo, c.fecha_hora AS fecha FROM analisis_ia a JOIN contenido_sospechoso c ON c.id_contenido = a.id_contenido ORDER BY a.id_analisis DESC LIMIT 1")->fetch();
    $fbTotal = ($fb['Correcto'] ?? 0) + ($fb['Incorrecto'] ?? 0);
    return [
        'total' => $total,
        'por_nivel' => ['alto' => $sem['Rojo'] ?? 0, 'medio' => $sem['Amarillo'] ?? 0, 'seguro' => $sem['Verde'] ?? 0],
        'por_canal' => $can,
        'feedback' => ['correcto' => $fb['Correcto'] ?? 0, 'incorrecto' => $fb['Incorrecto'] ?? 0, 'pendiente' => max(0, $total - $fbTotal)],
        'falsos_positivos' => ['cantidad' => $alert['Incorrecto'] ?? 0, 'sobre' => $fpSobre, 'porcentaje' => $fpSobre ? round(($alert['Incorrecto'] ?? 0) * 100 / $fpSobre, 1) : null],
        'falsos_negativos' => ['cantidad' => $segur['Incorrecto'] ?? 0, 'sobre' => $fnSobre, 'porcentaje' => $fnSobre ? round(($segur['Incorrecto'] ?? 0) * 100 / $fnSobre, 1) : null],
        'score_promedio' => $prom !== null && $prom !== false ? round((float)$prom, 1) : null,
        'ultimo_analisis' => $ult ? ['id_scan' => codigo_scan($ult['id_analisis']), 'estado_riesgo' => estado_de_semaforo($ult['semaforo']), 'fecha' => $ult['fecha']] : null,
    ];
}

function datos_historial($c, $args) {
    $donde = []; $par = [];
    if (!empty($args['nivel']) && in_array($args['nivel'], NS_NIVELES, true)) { $donde[] = 'a.semaforo = ?'; $par[] = semaforo_de_estado($args['nivel']); }
    if (!empty($args['canal']) && in_array($args['canal'], NS_CANALES, true)) { $donde[] = 'c.canal = ?'; $par[] = $args['canal']; }
    $where = $donde ? ' WHERE ' . implode(' AND ', $donde) : '';
    $n = max(1, min(20, (int)($args['limite'] ?? 8)));   // entero validado
    $q = $c->prepare("SELECT " . SQL_CAMPOS_ANALISIS . " " . SQL_BASE_ANALISIS . $where . " ORDER BY a.id_analisis DESC LIMIT " . $n);
    $q->execute($par);
    $filas = array_map('fila_para_pagina', $q->fetchAll());
    $qc = $c->prepare("SELECT COUNT(*) FROM analisis_ia a JOIN contenido_sospechoso c ON c.id_contenido = a.id_contenido" . $where);
    $qc->execute($par);
    return ['coincidencias' => (int)$qc->fetchColumn(), 'filas' => $filas];
}

function datos_feedback($c) {
    $q = $c->prepare("SELECT r.id_analisis, r.veredicto_usuario, r.fecha, a.semaforo, c.canal
                      FROM retroalimentacion r JOIN analisis_ia a ON a.id_analisis = r.id_analisis
                      JOIN contenido_sospechoso c ON c.id_contenido = a.id_contenido ORDER BY r.id_feedback DESC LIMIT 10");
    $q->execute();
    $ultimos = [];
    foreach ($q->fetchAll() as $f) {
        $ultimos[] = ['id_scan' => codigo_scan($f['id_analisis']), 'tipo' => fb_de($f['veredicto_usuario']), 'fecha' => $f['fecha'],
                      'estado_riesgo' => estado_de_semaforo($f['semaforo']), 'canal' => $f['canal']];
    }
    $r = datos_resumen($c);
    return ['resumen' => $r['feedback'], 'falsos_positivos' => $r['falsos_positivos'], 'falsos_negativos' => $r['falsos_negativos'], 'ultimos' => $ultimos];
}

function datos_analisis($c, $args) {
    $id = (string)($args['id_scan'] ?? '');
    if (!preg_match('/^#NS-(\d{1,6})$/', $id, $m)) return ['error' => 'ID inválido. Formato: #NS-0012'];
    $q = $c->prepare("SELECT " . SQL_CAMPOS_ANALISIS . ", a.explicacion, a.modo " . SQL_BASE_ANALISIS . " WHERE a.id_analisis = ?");
    $q->execute([(int)$m[1]]);
    $f = $q->fetch();
    if (!$f) return ['error' => 'No existe ese análisis.'];
    $r = fila_para_pagina($f);
    $r['modo'] = $f['modo'];
    $r['explicacion'] = mb_substr((string)$f['explicacion'], 0, 400);
    return $r;
}

// Único punto de entrada (lista blanca). Nunca ejecuta SQL recibido de fuera.
function consultar_datos($nombre, $args = []) {
    try {
        $c = conexion_lectura();
        switch ($nombre) {
            case 'resumen':   return datos_resumen($c);
            case 'historial': return datos_historial($c, is_array($args) ? $args : []);
            case 'feedback':  return datos_feedback($c);
            case 'analisis':  return datos_analisis($c, is_array($args) ? $args : []);
            default:          return ['error' => 'Consulta no permitida.'];
        }
    } catch (Exception $e) {
        return ['error' => 'No se pudo consultar la base de datos (¿importaste database.sql?).'];
    }
}

// Uso directo por HTTP: solo GET.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Esta ruta es de solo lectura (solo GET).']);
        exit;
    }
    $args = $_GET;
    unset($args['consulta']);
    echo json_encode(['status' => 'success', 'datos' => consultar_datos($_GET['consulta'] ?? '', $args)], JSON_UNESCAPED_UNICODE);
}
