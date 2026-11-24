-- NetShield · Base de datos (modelo del Sprint 3: 5 tablas)
-- Es seguro ejecutarlo varias veces: usa IF NOT EXISTS y NO borra nada.
--
--   usuario 1 --- N contenido_sospechoso 1 --- N analisis_ia N --- 1 metrica_calidad
--                                                analisis_ia 1 --- 0..1 retroalimentacion
CREATE DATABASE IF NOT EXISTS netshield CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE netshield;

-- 1) USUARIO
CREATE TABLE IF NOT EXISTS usuario (
  id_usuario   INT AUTO_INCREMENT PRIMARY KEY,
  tipo_usuario VARCHAR(30) NOT NULL
);

-- 2) METRICA_CALIDAD (cuenta de aciertos y errores del sistema)
CREATE TABLE IF NOT EXISTS metrica_calidad (
  id_metrica            INT AUTO_INCREMENT PRIMARY KEY,
  pct_falsos_positivos  DECIMAL(5,2) NOT NULL DEFAULT 0,   -- meta: < 5%
  pct_falsos_negativos  DECIMAL(5,2) NOT NULL DEFAULT 0,
  total_evaluaciones    INT NOT NULL DEFAULT 0
);

-- 3) CONTENIDO_SOSPECHOSO (lo que la gente manda a revisar)
CREATE TABLE IF NOT EXISTS contenido_sospechoso (
  id_contenido   INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario     INT NOT NULL,
  canal          ENUM('WhatsApp','SMS','Correo','URL','QR') NOT NULL,
  tipo           ENUM('Texto','Enlace','Imagen') NOT NULL,
  dato_ingresado TEXT NOT NULL,
  fecha_hora     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
);

-- 4) ANALISIS_IA (el resultado que da NetShield)
CREATE TABLE IF NOT EXISTS analisis_ia (
  id_analisis      INT AUTO_INCREMENT PRIMARY KEY,
  id_contenido     INT NOT NULL,
  id_metrica       INT NOT NULL,
  modo             ENUM('Rápido','Detallado') NOT NULL DEFAULT 'Rápido',
  score_riesgo     TINYINT UNSIGNED NOT NULL,              -- 0 a 100
  semaforo         ENUM('Rojo','Amarillo','Verde') NOT NULL,
  explicacion      TEXT NOT NULL,                          -- máx. 3 líneas (rápido)
  tiempo_respuesta DECIMAL(5,2) NOT NULL,                  -- en segundos
  FOREIGN KEY (id_contenido) REFERENCES contenido_sospechoso(id_contenido),
  FOREIGN KEY (id_metrica)   REFERENCES metrica_calidad(id_metrica),
  CHECK (score_riesgo <= 100)
);

-- 5) RETROALIMENTACION (botón Correcto / Incorrecto)
CREATE TABLE IF NOT EXISTS retroalimentacion (
  id_feedback       INT AUTO_INCREMENT PRIMARY KEY,
  id_analisis       INT NOT NULL UNIQUE,                   -- UNIQUE = relación 1 a (0 o 1)
  veredicto_usuario ENUM('Correcto','Incorrecto') NOT NULL,
  fecha             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_analisis) REFERENCES analisis_ia(id_analisis)
);

-- NOTA: la pagina agrega sola (la primera vez) un usuario "Normal" y una fila de metrica_calidad.
-- Las tablas analisis_historial y feedback_analisis de la version anterior ya no se usan;
-- si existen en tu base puedes dejarlas (no estorban).

-- OPCIONAL: usuario de solo lectura para el agente (luego ponlo en DB_RO_USER / DB_RO_PASS de config.php)
-- CREATE USER IF NOT EXISTS 'netshield_ro'@'localhost' IDENTIFIED BY 'cambia_esta_clave';
-- GRANT SELECT ON netshield.analisis_ia TO 'netshield_ro'@'localhost';
-- GRANT SELECT ON netshield.contenido_sospechoso TO 'netshield_ro'@'localhost';
-- GRANT SELECT ON netshield.retroalimentacion TO 'netshield_ro'@'localhost';
-- FLUSH PRIVILEGES;
