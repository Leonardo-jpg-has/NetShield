
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
  id_analisis       INT NOT NULL UNIQUE,                  
  veredicto_usuario ENUM('Correcto','Incorrecto') NOT NULL,
  fecha             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_analisis) REFERENCES analisis_ia(id_analisis)
);

