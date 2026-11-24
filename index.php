<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark light">
    <title>NetShield v2.0 (MVP) · Antes de hacer clic, verifica</title>
    <link rel="stylesheet" href="assets/netshield.css?v=<?= @filemtime(__DIR__ . '/assets/netshield.css') ?>">
</head>
<body id="mainBody">

<a class="skip-link" href="#contenido">Saltar al contenido principal</a>

<!-- Región invisible que anuncia veredictos y avisos a lectores de pantalla (TalkBack, NVDA, VoiceOver) -->
<div id="anuncios" class="sr-only" role="status" aria-live="polite" aria-atomic="true"></div>

<div id="app" class="container">

    <!-- CABECERA -->
    <header class="top">
        <div class="top-main">
            <h1>Antes de hacer clic, verifica.</h1>
            <p class="lead">Sistema de detección multicanal de phishing (WhatsApp, SMS, Correo, QR, OCR)</p>
        </div>
        <div class="top-side">
            <p class="console-tag">CONSOLA ANTI-PHISHING / 2.0</p>
            <p class="muted small">Datos reales desde MySQL · Agente de IA</p>
            <nav class="toolbar" aria-label="Opciones de la página">
                <button type="button" class="tool-btn" id="btnAyuda" aria-haspopup="dialog" aria-label="Ayuda: abrir el manual de uso">
                    <img src="assets/icons/preguntando.png" class="pixel-retro" alt=""> <span>Ayuda</span>
                </button>
                <button type="button" class="tool-btn" id="btnAcerca" aria-haspopup="dialog" aria-label="Acerca de: versión, fecha e integrantes">
                    <span>Acerca de</span>
                </button>
                <button type="button" class="tool-btn" id="btnTexto" aria-label="Texto: cambiar tamaño. Actual: normal">
                    <span>Texto</span>
                </button>
                <button type="button" class="tool-btn" id="btnContraste" aria-pressed="false" aria-label="Contraste: activar alto contraste">
                    <span>Contraste</span>
                </button>
                <button type="button" class="tool-btn" id="btnTema" aria-pressed="false" aria-label="Tema: cambiar a claro">
                    <img id="iconoTema" src="assets/icons/luna.png" class="pixel-retro" alt=""> <span>Tema</span>
                </button>
            </nav>
        </div>
    </header>

    <main id="contenido" tabindex="-1">

        <div class="grid-2">

            <!-- 01 · ENTRADA -->
            <section class="panel" aria-labelledby="t-entrada">
                <div class="panel-head">
                    <h2 id="t-entrada"><span class="hico" aria-hidden="true">🔍</span> Verificar contenido</h2>
                    <span class="tag" aria-hidden="true">ENTRADA / 01</span>
                </div>
                <div class="panel-body">

                    <div class="channels" role="tablist" aria-label="Canal por el que llegó el mensaje">
                        <button type="button" class="tab-btn active" role="tab" id="tab-WhatsApp" data-ch="WhatsApp" aria-selected="true" aria-controls="formVerificar" tabindex="0"><span aria-hidden="true">💬</span> WhatsApp</button>
                        <button type="button" class="tab-btn" role="tab" id="tab-SMS" data-ch="SMS" aria-selected="false" aria-controls="formVerificar" tabindex="-1"><img src="assets/icons/celular.png" class="pixel-retro" alt=""> SMS</button>
                        <button type="button" class="tab-btn" role="tab" id="tab-Correo" data-ch="Correo" aria-selected="false" aria-controls="formVerificar" tabindex="-1"><img src="assets/icons/correo.png" class="pixel-retro" alt=""> Correo</button>
                        <button type="button" class="tab-btn" role="tab" id="tab-URL" data-ch="URL" aria-selected="false" aria-controls="formVerificar" tabindex="-1"><span aria-hidden="true">🔗</span> URL</button>
                        <button type="button" class="tab-btn" role="tab" id="tab-QR" data-ch="QR" aria-selected="false" aria-controls="formVerificar" tabindex="-1"><span aria-hidden="true">▦</span> QR</button>
                    </div>

                    <div id="formVerificar" role="tabpanel" aria-labelledby="tab-WhatsApp">
                        <div class="field-row">
                            <label for="textoInput" class="field-label">Pega el mensaje sospechoso…</label>
                            <span class="muted small" aria-hidden="true">Máximo 2000 caracteres</span>
                        </div>
                        <div class="textarea-wrap">
                            <textarea id="textoInput" maxlength="2000" aria-describedby="textoAyuda textoContador"
                                placeholder="Ejemplo: URGENTE: Tu cuenta será bloqueada hoy. Verifica tus datos en https://banco-falso.xyz para evitar la suspensión."></textarea>
                            <div class="textarea-foot">
                                <span id="textoAyuda" class="muted small">Los enlaces se analizan sin abrirlos. <button type="button" class="link-btn" id="btnEjemplo">Insertar ejemplo</button></span>
                                <span id="textoContador" class="muted small" aria-label="Caracteres escritos">0 / 2000</span>
                            </div>
                        </div>

                        <button type="button" class="dropzone" id="dropzone" aria-describedby="dropAyuda">
                            <span class="drop-text">
                                <strong>Subir captura para OCR</strong>
                                <span id="dropAyuda" class="muted small">Arrastra una imagen o selecciona un archivo · PNG, JPG · Máx. 5 MB</span>
                            </span>
                            <img src="assets/icons/camara.png" class="pixel-retro drop-ico" alt="">
                        </button>
                        <input type="file" id="fileUpload" accept="image/png,image/jpeg,image/webp" hidden aria-label="Elegir una captura de pantalla para leer su texto">

                        <div id="previewBox" class="preview-box" hidden>
                            <img id="previewImg" alt="Vista previa de la captura subida">
                            <div class="preview-info">
                                <span id="ocrEstado" role="status" aria-live="polite">Esperando imagen…</span>
                                <button type="button" class="tool-btn" id="btnQuitarImg" aria-label="Quitar la captura subida">✕ Quitar</button>
                            </div>
                        </div>

                        <fieldset class="modes">
                            <legend class="sr-only">Modo de análisis</legend>
                            <label class="mode"><input type="radio" name="modo" value="rapido" checked> <span>Modo Rápido</span></label>
                            <label class="mode"><input type="radio" name="modo" value="detallado"> <span>Modo Detallado</span></label>
                        </fieldset>

                        <button type="button" class="btn-primary" id="btnAnalizar">Verificar</button>
                        <p class="muted small privacy">Privacidad primero: elimina contraseñas y datos personales antes de verificar.</p>
                    </div>
                </div>
            </section>

            <!-- 02 · SALIDA -->
            <section class="panel" aria-labelledby="t-salida">
                <div class="panel-head">
                    <h2 id="t-salida"><span class="hico" aria-hidden="true">🛡️</span> Resultado de la verificación</h2>
                    <span class="tag" aria-hidden="true">SALIDA / 02</span>
                </div>
                <div class="panel-body">

                    <div id="cajaResultado" class="verdict wait">
                        <div class="verdict-top">
                            <span class="tag-small" id="etiquetaResultado">● RESULTADO ACTUAL</span>
                            <span class="small">Respuesta: <span id="pingTime">0.00s</span></span>
                        </div>
                        <div class="verdict-main">
                            <div class="verdict-text">
                                <span id="badgeRiesgo" class="badge wait">EN ESPERA</span>
                                <p id="tituloResultado" class="verdict-title">Ingresa un mensaje para verificarlo.</p>
                            </div>
                            <div class="score">
                                <span id="scoreNum" class="score-num">—</span>
                                <span class="score-label">SCORE / 100</span>
                            </div>
                        </div>
                        <div class="defang">
                            <code id="linkDefanged">N/A</code>
                            <span class="defang-tag">DESACTIVADO</span>
                        </div>
                        <ul id="descResultado" class="reasons">
                            <li>Aquí verás por qué el mensaje es o no sospechoso.</li>
                        </ul>
                        <ul id="listaLinks" class="link-list" aria-label="Enlaces encontrados" hidden></ul>
                        <p id="recomendacion" class="recomendacion" hidden></p>
                        <details id="pasosBox" class="pasos" hidden>
                            <summary>Explicación paso a paso</summary>
                            <ol id="listaPasos"></ol>
                        </details>
                        <p class="small muted motor">Motor: <span id="motor">—</span></p>
                    </div>

                    <div id="feedbackContainer" class="feedback" hidden>
                        <p id="feedbackPregunta" class="feedback-q">¿El resultado es correcto?</p>
                        <div class="feedback-buttons" role="group" aria-labelledby="feedbackPregunta">
                            <button type="button" class="btn-sec" id="btnCorrecto" aria-label="Marcar el resultado como correcto">
                                <img src="assets/icons/pulgar_arriba.png" class="pixel-retro" alt=""> Correcto
                            </button>
                            <button type="button" class="btn-sec" id="btnIncorrecto" aria-label="Marcar el resultado como incorrecto o falso positivo">
                                <img src="assets/icons/pulgar_abajo.png" class="pixel-retro" alt=""> Incorrecto
                            </button>
                            <button type="button" class="btn-sec" id="btnExplicar" aria-label="Explicar este veredicto paso a paso con el agente">
                                Explicar
                            </button>
                        </div>
                        <p id="feedbackMsg" class="feedback-msg" role="status" aria-live="polite"></p>
                    </div>

                    <div class="legend">
                        <p class="legend-title">GUÍA DE ESTADOS · EL COLOR SIEMPRE TIENE TEXTO</p>
                        <div class="legend-badges">
                            <span class="badge high">RIESGO ALTO</span>
                            <span class="badge medium">RIESGO MEDIO</span>
                            <span class="badge safe">SIN SEÑALES DE ESTAFA</span>
                        </div>
                    </div>
                    <p class="muted small">Sin señales no garantiza seguridad. Verifica siempre por canales oficiales.</p>
                </div>
            </section>
        </div>

        <!-- 03 · AGENTE (CHAT) -->
        <section class="panel chat" aria-labelledby="t-chat">
            <div class="panel-head">
                <h2 id="t-chat"><span class="hico" aria-hidden="true">🤖</span> Agente NetShield</h2>
                <span class="tag" aria-hidden="true">CHAT / 03</span>
            </div>
            <div class="panel-body">
                <div id="chatLog" class="chat-log" role="log" aria-live="polite" aria-relevant="additions" aria-label="Conversación con el agente NetShield" tabindex="0"></div>
                <p id="chatTyping" class="typing" role="status" aria-live="polite"></p>
                <div class="chips" role="group" aria-label="Preguntas sugeridas">
                    <button type="button" class="chip" data-q="¿Cuántos análisis de riesgo alto hubo?">¿Cuántos de riesgo alto hubo?</button>
                    <button type="button" class="chip" data-q="Explícame el último veredicto paso a paso">Explica el último veredicto</button>
                    <button type="button" class="chip" data-q="¿Cómo van las estadísticas y el feedback?">Estadísticas y feedback</button>
                    <button type="button" class="chip" data-q="¿Cuáles fueron los últimos enlaces peligrosos?">Últimos enlaces peligrosos</button>
                    <button type="button" class="chip" data-q="¿Qué hago si ya hice clic en el enlace?">Ya hice clic, ¿qué hago?</button>
                </div>
                <form id="chatForm" class="chat-form" autocomplete="off">
                    <label for="chatInput" class="sr-only">Escribe tu pregunta para el agente</label>
                    <input type="text" id="chatInput" maxlength="500" placeholder="Pregunta por tus análisis, estadísticas o enlaces…">
                    <button type="submit" class="btn-primary" id="chatEnviar" aria-label="Enviar pregunta al agente">Enviar</button>
                </form>
            </div>
        </section>

        <!-- CALIDAD -->
        <section aria-labelledby="t-calidad">
            <div class="section-head">
                <h2 id="t-calidad" class="section-title">CALIDAD DEL MODELO / ISO 25010</h2>
                <span class="muted small">Calculado en vivo desde la base de datos</span>
            </div>
            <div class="grid-3">
                <article class="stat" aria-label="Total de evaluaciones">
                    <h3>Total Evaluaciones</h3>
                    <p class="stat-num" id="statTotal">—</p>
                    <p class="muted small" id="statTotalSub">Cargando…</p>
                </article>
                <article class="stat" aria-label="Porcentaje de falsos positivos">
                    <h3>% Falsos Positivos (&lt;5%) <span class="meta-tag" id="metaFp">META &lt;5%</span></h3>
                    <p class="stat-num" id="statFalsos">—</p>
                    <p class="muted small" id="statFalsosSub">Alertas marcadas como incorrectas</p>
                </article>
                <article class="stat" aria-label="Porcentaje de falsos negativos">
                    <h3>% Falsos Negativos</h3>
                    <p class="stat-num" id="statFn">—</p>
                    <p class="muted small" id="statFnSub">Seguros marcados como incorrectos</p>
                </article>
            </div>
        </section>

        <!-- HISTORIAL -->
        <section class="panel" aria-labelledby="t-hist">
            <div class="panel-head">
                <h2 id="t-hist"><span class="hico" aria-hidden="true">🗄️</span> Historial de evaluaciones</h2>
                <span class="tag" aria-hidden="true">ÚLTIMOS 8 REGISTROS</span>
            </div>
            <div class="hist-sub"><span class="small">Vista MySQL · registros reales de netshield</span><span class="muted small">Orden: más recientes primero</span></div>
            <div class="table-wrap">
                <table class="hist">
                    <caption class="sr-only">Últimas 8 evaluaciones guardadas, de la más reciente a la más antigua</caption>
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Canal</th>
                            <th scope="col">Contenido defanged</th>
                            <th scope="col">Score</th>
                            <th scope="col">Tiempo</th>
                            <th scope="col">Feedback</th>
                        </tr>
                    </thead>
                    <tbody id="tablaHistorial">
                        <tr><td colspan="6">Cargando historial…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="hist-foot"><span id="histConteo" class="muted small">—</span><span class="muted small">hxxp / hxxps = enlace desactivado · No navegable</span></div>
        </section>

    </main>

    <footer class="footer">
        <span>NETSHIELD v2.0 · MVP ACADÉMICO · USTA</span>
        <span class="muted">Agente de IA con respaldo de reglas · Datos reales de MySQL</span>
    </footer>
</div>

<!-- MODAL: AYUDA -->
<div id="helpModal" class="modal-overlay" hidden>
    <div class="modal-window" role="dialog" aria-modal="true" aria-labelledby="helpTitulo">
        <div class="modal-header">
            <h2 id="helpTitulo">📖 Manual de ayuda · NetShield v2.0</h2>
            <button type="button" class="tool-btn" data-cerrar aria-label="Cerrar ayuda">✕ Cerrar</button>
        </div>
        <div class="modal-body">
            <h3>¿Cómo usar NetShield de forma segura?</h3>
            <p class="muted">NetShield analiza mensajes, correos, enlaces, códigos QR y capturas con un agente de IA y reglas de respaldo, sin abrir ni arriesgar tus dispositivos. Los enlaces se verifican solo por su texto y se muestran desarticulados (defanged).</p>
            <ul>
                <li><strong>Elige el canal</strong> por el que te llegó el mensaje.</li>
                <li><strong>Pega el texto o el enlace</strong>, o sube una captura: se lee con OCR y, si no se entiende, el agente de IA la lee.</li>
                <li><strong>Lee el veredicto:</strong> nivel de riesgo, score de 0 a 100, enlaces revisados y qué hacer.</li>
                <li><strong>Feedback:</strong> dinos si fue correcto; alimenta las estadísticas (ISO 25010).</li>
                <li><strong>Agente:</strong> pregúntale por tus análisis, estadísticas y enlaces, o pídele que explique un veredicto paso a paso.</li>
            </ul>
            <p class="note"><strong>Accesibilidad:</strong> botones de tamaño de texto, alto contraste y tema claro/oscuro en la barra superior. Todo funciona con teclado y lector de pantalla; Escape cierra las ventanas.</p>
        </div>
    </div>
</div>

<!-- MODAL: ACERCA DE -->
<div id="aboutModal" class="modal-overlay" hidden>
    <div class="modal-window" role="dialog" aria-modal="true" aria-labelledby="aboutTitulo">
        <div class="modal-header">
            <h2 id="aboutTitulo">ℹ️ Acerca de NetShield</h2>
            <button type="button" class="tool-btn" data-cerrar aria-label="Cerrar Acerca de">✕ Cerrar</button>
        </div>
        <div class="modal-body">
            <h3>🛡️ NetShield</h3>
            <p class="muted">Sistema de Detección Multicanal de Fraude y Phishing</p>
            <dl class="about-list">
                <div><dt>Versión</dt><dd>2.0 (MVP)</dd></div>
                <div><dt>Fecha de actualización</dt><dd>22 de noviembre de 2026</dd></div>
                <div><dt>Universidad</dt><dd>Universidad Santo Tomás (USTA)</dd></div>
                <div><dt>Marco de calidad</dt><dd>ISO 25010</dd></div>
            </dl>
            <h3>👥 Integrantes</h3>
            <ul>
                <li>Leonardo Martínez Medina</li>
                <li>Alejandra Rincón Espitia</li>
                <li>Angélica López Ortiz</li>
            </ul>
        </div>
    </div>
</div>

<script src="assets/netshield.js?v=<?= @filemtime(__DIR__ . '/assets/netshield.js') ?>"></script>
</body>
</html>
