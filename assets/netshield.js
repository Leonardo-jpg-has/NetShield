// NetShield v2.0 - Lógica del cliente
// Conceptos de clase: DOM, eventos, fetch con async/await, localStorage.
// Extras: OCR con Tesseract.js (CDN), lectura de QR con jsQR (CDN), chat con el agente, accesibilidad.

const TESSERACT_URL = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
const JSQR_URL = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';
const MAX_BYTES = 5 * 1024 * 1024;
const NIVEL_TXT = { high: 'RIESGO ALTO', medium: 'RIESGO MEDIO', safe: 'SIN SEÑALES DE ESTAFA' };
const TEXTO_EJEMPLO = 'URGENTE: Tu cuenta será bloqueada hoy. Verifica tus datos en https://banco-falso.xyz para evitar la suspensión.';

const $ = (id) => document.getElementById(id);

// ---------- Utilidades ----------
function guardar(clave, valor) { try { localStorage.setItem(clave, valor); } catch (e) { /* sin almacenamiento */ } }
function leer(clave) { try { return localStorage.getItem(clave); } catch (e) { return null; } }

// Anuncia un texto a lectores de pantalla (TalkBack, NVDA, VoiceOver) sin mostrarlo
function anunciar(texto) {
    const r = $('anuncios');
    r.textContent = '';
    setTimeout(() => { r.textContent = texto; }, 60);
}

function cargarScript(url, globalName) {
    return new Promise((resolve, reject) => {
        if (window[globalName]) { resolve(); return; }
        const s = document.createElement('script');
        s.src = url;
        s.onload = () => (window[globalName] ? resolve() : reject(new Error('Librería no disponible')));
        s.onerror = () => reject(new Error('No se pudo cargar ' + globalName));
        document.head.appendChild(s);
    });
}

async function api(cuerpo) {
    const r = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(cuerpo)
    });
    return r.json();
}

// ---------- Estado ----------
let canalActivo = 'WhatsApp';
let imagenParaAgente = null;   // {media_type, data} solo si el OCR no pudo leer bien
let procesandoOCR = false;
let ultimoResultado = null;    // contexto que se le da al agente
let historialChat = [];

document.addEventListener('DOMContentLoaded', () => {
    iniciarPreferencias();
    iniciarModales();
    iniciarPestanas();
    iniciarFormulario();
    iniciarChat();
    cargarEstadisticas();
    cargarHistorial();
});

// =====================================================================
// PREFERENCIAS DE ACCESIBILIDAD (tema, tamaño de texto, contraste)
// =====================================================================
function iniciarPreferencias() {
    const body = document.body;

    function aplicarTema(claro) {
        body.classList.toggle('light-mode', claro);
        $('iconoTema').src = claro ? 'assets/icons/sol.png' : 'assets/icons/luna.png';
        $('btnTema').setAttribute('aria-pressed', String(claro));
        $('btnTema').setAttribute('aria-label', claro ? 'Tema: claro activado. Cambiar a oscuro' : 'Tema: oscuro activado. Cambiar a claro');
    }
    function aplicarContraste(on) {
        body.classList.toggle('colorblind-mode', on);
        $('btnContraste').setAttribute('aria-pressed', String(on));
        $('btnContraste').setAttribute('aria-label', on ? 'Contraste: alto contraste activado. Desactivar' : 'Contraste: activar alto contraste');
    }
    function aplicarTexto(nivel) {   // 0 normal, 1 grande, 2 muy grande
        body.classList.toggle('font-large', nivel === 1);
        body.classList.toggle('font-xlarge', nivel === 2);
        const nombres = ['normal', 'grande', 'muy grande'];
        $('btnTexto').setAttribute('aria-label', 'Texto: cambiar tamaño. Actual: ' + nombres[nivel]);
    }

    let tema = leer('ns_tema') === 'claro';
    let contraste = leer('ns_contraste') === '1';
    let texto = parseInt(leer('ns_texto') || '0', 10) || 0;
    aplicarTema(tema); aplicarContraste(contraste); aplicarTexto(texto);

    $('btnTema').addEventListener('click', () => {
        tema = !tema; aplicarTema(tema); guardar('ns_tema', tema ? 'claro' : 'oscuro');
        anunciar(tema ? 'Tema claro activado' : 'Tema oscuro activado');
    });
    $('btnContraste').addEventListener('click', () => {
        contraste = !contraste; aplicarContraste(contraste); guardar('ns_contraste', contraste ? '1' : '0');
        anunciar(contraste ? 'Alto contraste activado' : 'Alto contraste desactivado');
    });
    $('btnTexto').addEventListener('click', () => {
        texto = (texto + 1) % 3; aplicarTexto(texto); guardar('ns_texto', String(texto));
        anunciar('Tamaño de texto: ' + ['normal', 'grande', 'muy grande'][texto]);
    });
}

// =====================================================================
// MODALES: foco atrapado, Escape, devuelve el foco a quien los abrió
// =====================================================================
let modalAbierto = null;
let abridor = null;
const FOCUSABLES = 'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), summary, [tabindex]:not([tabindex="-1"])';

function abrirModal(id, desde) {
    cerrarModal();
    const m = $(id);
    abridor = desde || document.activeElement;
    m.hidden = false;
    modalAbierto = m;
    const app = $('app');
    app.inert = true;                       // el resto de la página deja de ser navegable
    app.setAttribute('aria-hidden', 'true');
    const primero = m.querySelector('[data-cerrar]') || m.querySelector(FOCUSABLES);
    if (primero) primero.focus();
}

function cerrarModal() {
    if (!modalAbierto) return;
    modalAbierto.hidden = true;
    modalAbierto = null;
    const app = $('app');
    app.inert = false;
    app.removeAttribute('aria-hidden');
    if (abridor && document.contains(abridor)) abridor.focus();
    abridor = null;
}

function iniciarModales() {
    $('btnAyuda').addEventListener('click', (e) => abrirModal('helpModal', e.currentTarget));
    $('btnAcerca').addEventListener('click', (e) => abrirModal('aboutModal', e.currentTarget));

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => { if (e.target === overlay) cerrarModal(); });
        overlay.querySelectorAll('[data-cerrar]').forEach(b => b.addEventListener('click', cerrarModal));
    });

    document.addEventListener('keydown', (e) => {
        if (!modalAbierto) return;
        if (e.key === 'Escape') { e.preventDefault(); cerrarModal(); return; }
        if (e.key === 'Tab') {                // atrapa el foco dentro del modal
            const items = Array.from(modalAbierto.querySelectorAll(FOCUSABLES)).filter(el => el.offsetParent !== null);
            if (!items.length) return;
            const primero = items[0], ultimo = items[items.length - 1];
            if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
            else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
            else if (!modalAbierto.contains(document.activeElement)) { e.preventDefault(); primero.focus(); }
        }
    });
}

// =====================================================================
// PESTAÑAS DE CANAL (roles ARIA + flechas del teclado)
// =====================================================================
function iniciarPestanas() {
    const tabs = Array.from(document.querySelectorAll('.tab-btn'));

    function activar(tab, enfocar) {
        tabs.forEach(t => {
            const on = t === tab;
            t.classList.toggle('active', on);
            t.setAttribute('aria-selected', String(on));
            t.tabIndex = on ? 0 : -1;
        });
        canalActivo = tab.getAttribute('data-ch');
        $('formVerificar').setAttribute('aria-labelledby', tab.id);
        if (enfocar) tab.focus();
    }

    tabs.forEach((tab, i) => {
        tab.addEventListener('click', () => activar(tab, false));
        tab.addEventListener('keydown', (e) => {
            let destino = null;
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') destino = tabs[(i + 1) % tabs.length];
            else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') destino = tabs[(i - 1 + tabs.length) % tabs.length];
            else if (e.key === 'Home') destino = tabs[0];
            else if (e.key === 'End') destino = tabs[tabs.length - 1];
            if (destino) { e.preventDefault(); activar(destino, true); }
        });
    });
}

// =====================================================================
// FORMULARIO: texto, captura (OCR / QR), análisis, feedback
// =====================================================================
function iniciarFormulario() {
    const input = $('textoInput');
    const contador = $('textoContador');
    const dropzone = $('dropzone');
    const fileInput = $('fileUpload');

    input.addEventListener('input', () => { contador.textContent = input.value.length + ' / 2000'; });
    $('btnEjemplo').addEventListener('click', () => {
        input.value = TEXTO_EJEMPLO;
        input.dispatchEvent(new Event('input'));
        input.focus();
    });

    // Subir captura: clic, teclado (el botón ya responde a Enter/Espacio) o arrastrar
    dropzone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => { if (fileInput.files[0]) procesarArchivo(fileInput.files[0]); });
    ['dragenter', 'dragover'].forEach(ev => dropzone.addEventListener(ev, (e) => { e.preventDefault(); dropzone.classList.add('over'); }));
    ['dragleave', 'drop'].forEach(ev => dropzone.addEventListener(ev, (e) => { e.preventDefault(); dropzone.classList.remove('over'); }));
    dropzone.addEventListener('drop', (e) => { if (e.dataTransfer.files[0]) procesarArchivo(e.dataTransfer.files[0]); });

    $('btnQuitarImg').addEventListener('click', () => {
        fileInput.value = '';
        $('previewBox').hidden = true;
        imagenParaAgente = null;
        anunciar('Captura quitada');
        dropzone.focus();
    });

    $('btnAnalizar').addEventListener('click', analizar);
    $('btnCorrecto').addEventListener('click', () => enviarFeedback('CORRECTO'));
    $('btnIncorrecto').addEventListener('click', () => enviarFeedback('INCORRECTO'));
    $('btnExplicar').addEventListener('click', () => {
        preguntarAlAgente('Explícame el último veredicto paso a paso');
        $('chatLog').scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

function estadoOCR(texto) { $('ocrEstado').textContent = texto; }

async function procesarArchivo(archivo) {
    const box = $('previewBox');
    box.hidden = false;
    $('previewImg').src = URL.createObjectURL(archivo);
    imagenParaAgente = null;

    if (!/^image\/(png|jpeg|webp)$/.test(archivo.type)) { estadoOCR('✖ Formato no permitido. Usa PNG o JPG.'); return; }
    if (archivo.size > MAX_BYTES) { estadoOCR('✖ La imagen pesa más de 5 MB.'); return; }

    procesandoOCR = true;
    $('btnAnalizar').disabled = true;
    const input = $('textoInput');

    // Canal QR: primero intenta leer el código QR
    if (canalActivo === 'QR') {
        estadoOCR('Buscando un código QR…');
        try {
            await cargarScript(JSQR_URL, 'jsQR');
            const img = await cargarImagen(archivo);
            const c = document.createElement('canvas');
            c.width = img.width; c.height = img.height;
            const ctx = c.getContext('2d');
            ctx.drawImage(img, 0, 0);
            const qr = window.jsQR(ctx.getImageData(0, 0, c.width, c.height).data, c.width, c.height);
            if (qr && qr.data) {
                input.value = qr.data.slice(0, 2000);
                input.dispatchEvent(new Event('input'));
                estadoOCR('✔ Código QR leído. Su contenido está en el cuadro de texto (no se abrió).');
                finOCR();
                return;
            }
        } catch (e) { /* si falla, sigue con OCR */ }
    }

    estadoOCR('Preparando OCR (la primera vez descarga el motor)…');
    let textoOCR = '', confianza = 0;
    try {
        await cargarScript(TESSERACT_URL, 'Tesseract');
        const r = await window.Tesseract.recognize(archivo, 'spa+eng', {
            logger: m => { if (m.status === 'recognizing text') estadoOCR('Leyendo texto… ' + Math.round(m.progress * 100) + '%'); }
        });
        textoOCR = (r.data.text || '').trim();
        confianza = r.data.confidence || 0;
    } catch (e) { textoOCR = ''; }

    if (textoOCR.length >= 15 && confianza >= 60) {
        input.value = textoOCR.slice(0, 2000);
        input.dispatchEvent(new Event('input'));
        estadoOCR('✔ Texto extraído con OCR (confianza ' + Math.round(confianza) + '%). Puedes editarlo.');
    } else {
        if (textoOCR) { input.value = textoOCR.slice(0, 2000); input.dispatchEvent(new Event('input')); }
        try {
            imagenParaAgente = await reducirImagen(archivo);
            estadoOCR('⚠ El OCR no leyó bien. El agente de IA leerá la imagen al verificar.');
        } catch (e) { estadoOCR('✖ No se pudo procesar la imagen.'); }
    }
    finOCR();
}

function finOCR() {
    procesandoOCR = false;
    $('btnAnalizar').disabled = false;
    anunciar($('ocrEstado').textContent);
}

function cargarImagen(archivo) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = URL.createObjectURL(archivo);
    });
}

// Reduce la imagen (máx. 1400 px, JPEG) para enviarla al agente sin que pese demasiado
async function reducirImagen(archivo) {
    const img = await cargarImagen(archivo);
    const escala = Math.min(1, 1400 / Math.max(img.width, img.height));
    const c = document.createElement('canvas');
    c.width = Math.round(img.width * escala);
    c.height = Math.round(img.height * escala);
    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
    return { media_type: 'image/jpeg', data: c.toDataURL('image/jpeg', 0.85).split(',')[1] };
}

// ---------- Analizar ----------
async function analizar() {
    if (procesandoOCR) return;
    const btn = $('btnAnalizar');
    const texto = $('textoInput').value.trim();
    if (!texto && !imagenParaAgente) {
        anunciar('Escribe un mensaje o sube una captura para verificar.');
        $('textoInput').focus();
        $('etiquetaResultado').textContent = '● ESCRIBE O SUBE ALGO PARA VERIFICAR';
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Verificando…';
    $('feedbackContainer').hidden = true;
    anunciar('Verificando el contenido');

    try {
        const cuerpo = {
            texto, canal: canalActivo,
            modo: document.querySelector('input[name="modo"]:checked').value
        };
        if (imagenParaAgente) cuerpo.imagen = imagenParaAgente;
        const res = await api(cuerpo);

        if (res.status === 'success') {
            mostrarResultado(res);
            if (res.texto_extraido && !texto) { $('textoInput').value = res.texto_extraido; $('textoInput').dispatchEvent(new Event('input')); }
            cargarEstadisticas();
            cargarHistorial();
        } else {
            $('etiquetaResultado').textContent = '● ERROR';
            anunciar(res.message || 'La API devolvió un error.');
            alert(res.message || 'La API devolvió un error.');
        }
    } catch (err) {
        anunciar('Error al conectar con la API de NetShield.');
        alert('Error al conectar con la API de NetShield.');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Verificar';
    }
}

function titular(res) {
    if (res.nivel === 'high') return (res.urls && res.urls.length) ? 'No abras este enlace.' : 'No respondas ni compartas datos.';
    if (res.nivel === 'medium') return 'Desconfía antes de actuar.';
    return 'No se detectaron señales de estafa.';
}

function mostrarResultado(res) {
    $('cajaResultado').className = 'verdict ' + res.nivel;
    $('etiquetaResultado').textContent = '● RESULTADO ACTUAL · ' + res.id_scan;
    $('pingTime').textContent = res.latencia;
    const badge = $('badgeRiesgo');
    badge.className = 'badge ' + res.nivel;
    badge.textContent = NIVEL_TXT[res.nivel];
    $('tituloResultado').textContent = titular(res);
    $('scoreNum').textContent = String(res.score).padStart(2, '0');

    const urls = res.urls || [];
    $('linkDefanged').textContent = urls.length
        ? urls[0].url + (urls.length > 1 ? '  (+' + (urls.length - 1) + ' más)' : '')
        : (res.defanged_completo || res.defanged).slice(0, 90);

    const razones = $('descResultado');
    razones.innerHTML = '';
    res.razones.forEach(t => { const li = document.createElement('li'); li.textContent = t; razones.appendChild(li); });

    const lista = $('listaLinks');
    lista.innerHTML = '';
    urls.forEach(u => {
        const li = document.createElement('li');
        const b = document.createElement('span');
        b.className = 'badge ' + u.nivel;
        b.textContent = u.nivel === 'high' ? 'ALTO' : (u.nivel === 'medium' ? 'MEDIO' : 'SIN SEÑALES');
        li.appendChild(b);
        li.appendChild(document.createTextNode(u.url));
        const why = document.createElement('span');
        why.className = 'why';
        why.textContent = u.razones.join(' ');
        li.appendChild(why);
        lista.appendChild(li);
    });
    lista.hidden = !urls.length;

    $('recomendacion').textContent = '💡 ' + res.recomendacion;
    $('recomendacion').hidden = !res.recomendacion;

    const pasos = $('listaPasos');
    pasos.innerHTML = '';
    (res.pasos || []).forEach(t => { const li = document.createElement('li'); li.textContent = t; pasos.appendChild(li); });
    $('pasosBox').hidden = res.modo !== 'detallado';
    $('pasosBox').open = true;

    $('motor').textContent = res.motor + (res.ia_error ? ' — IA no disponible: ' + res.ia_error
        : (res.ia_configurada === false ? ' — falta la clave de IA en config.php' : ''));

    // Feedback listo para este análisis
    $('cajaResultado').dataset.analisisId = res.id;
    $('feedbackContainer').hidden = false;
    $('feedbackMsg').textContent = '';
    $('btnCorrecto').setAttribute('aria-pressed', 'false');
    $('btnIncorrecto').setAttribute('aria-pressed', 'false');

    ultimoResultado = {
        id_scan: res.id_scan, canal: canalActivo, estado_riesgo: res.estado_riesgo, score: res.score,
        razones: res.razones, recomendacion: res.recomendacion, motor: res.motor,
        defanged: (res.defanged_completo || res.defanged).slice(0, 300),
        urls: urls.map(u => ({ url: u.url, nivel: u.nivel, razones: u.razones }))
    };

    anunciar('Resultado: ' + NIVEL_TXT[res.nivel] + '. Score ' + res.score + ' de 100. ' + titular(res) + ' Motivos: ' + res.razones.slice(0, 3).join(' '));
}

async function enviarFeedback(tipo) {
    const msg = $('feedbackMsg');
    const id = $('cajaResultado').dataset.analisisId;
    try {
        const res = await api({ action: 'feedback', id: Number(id), tipo });
        if (res.status !== 'success') throw new Error(res.message);
        $('btnCorrecto').setAttribute('aria-pressed', String(tipo === 'CORRECTO'));
        $('btnIncorrecto').setAttribute('aria-pressed', String(tipo === 'INCORRECTO'));
        msg.textContent = tipo === 'CORRECTO'
            ? '✔ ¡Gracias! Feedback guardado para mejora continua (ISO 25010).'
            : '✔ Reporte guardado como falso positivo / error.';
        cargarEstadisticas();
        cargarHistorial();
    } catch (e) {
        msg.textContent = 'No se pudo guardar el feedback: ' + e.message;
    }
}

// =====================================================================
// ESTADÍSTICAS E HISTORIAL (datos reales de MySQL)
// =====================================================================
const fmtPct = (n) => n.toLocaleString('es-CO', { maximumFractionDigits: 1 }) + '%';

async function cargarEstadisticas() {
    try {
        const r = await (await fetch('api.php?action=stats')).json();
        if (r.status !== 'success') throw new Error(r.message);
        const s = r.resumen;
        $('statTotal').textContent = s.total.toLocaleString('es-CO');
        $('statTotalSub').textContent = 'Alto: ' + s.por_nivel.alto + ' · Medio: ' + s.por_nivel.medio + ' · Seguro: ' + s.por_nivel.seguro;

        const fp = s.falsos_positivos;
        $('statFalsos').textContent = fp.porcentaje === null ? 'Sin datos' : fmtPct(fp.porcentaje);
        $('statFalsosSub').textContent = fp.sobre ? fp.cantidad + ' / ' + fp.sobre + ' alertas con feedback marcadas como incorrectas' : 'Aún sin feedback sobre alertas';
        const meta = $('metaFp');
        const fuera = fp.porcentaje !== null && fp.porcentaje >= 5;
        meta.classList.toggle('fuera', fuera);
        meta.textContent = fuera ? 'FUERA DE META <5%' : 'META <5%';

        const fn = s.falsos_negativos;
        $('statFn').textContent = fn.porcentaje === null ? 'Sin datos' : fmtPct(fn.porcentaje);
        $('statFnSub').textContent = fn.sobre ? fn.cantidad + ' / ' + fn.sobre + ' resultados seguros con feedback marcados como incorrectos' : 'Aún sin feedback sobre resultados seguros';
    } catch (e) {
        $('statTotalSub').textContent = 'No se pudieron cargar las estadísticas.';
    }
}

function celda(texto, etiqueta, clase) {
    const td = document.createElement('td');
    td.textContent = texto;
    td.setAttribute('data-label', etiqueta);
    if (clase) td.className = clase;
    return td;
}

async function cargarHistorial() {
    const tbody = $('tablaHistorial');
    try {
        const r = await (await fetch('api.php?action=historial')).json();
        if (r.status !== 'success') throw new Error(r.message);
        tbody.innerHTML = '';
        if (!r.filas.length) {
            const tr = document.createElement('tr');
            const td = celda('Aún no hay evaluaciones. Verifica un mensaje para empezar.', 'Estado');
            td.colSpan = 6;
            tr.appendChild(td);
            tbody.appendChild(tr);
        }
        r.filas.forEach(f => {
            const tr = document.createElement('tr');
            tr.appendChild(celda(f.id_scan, 'ID', 'mono'));
            tr.appendChild(celda(f.canal, 'Canal'));
            tr.appendChild(celda(f.contenido_defanged, 'Contenido', 'mono'));

            const nivel = f.estado_riesgo === 'RIESGO ALTO' ? 'high' : (f.estado_riesgo === 'RIESGO MEDIO' ? 'medium' : 'safe');
            const corto = nivel === 'high' ? 'Alto' : (nivel === 'medium' ? 'Medio' : 'Sin señales');
            const tdScore = document.createElement('td');
            tdScore.setAttribute('data-label', 'Score');
            const b = document.createElement('span');
            b.className = 'badge ' + nivel;
            b.textContent = (f.score === null || f.score === undefined ? '—' : String(f.score).padStart(2, '0')) + ' · ' + corto;
            tdScore.appendChild(b);
            tr.appendChild(tdScore);

            tr.appendChild(celda(f.latencia, 'Tiempo', 'mono'));
            tr.appendChild(celda(f.feedback === 'CORRECTO' ? 'Correcto' : (f.feedback === 'INCORRECTO' ? 'Incorrecto' : 'Pendiente'), 'Feedback'));
            tbody.appendChild(tr);
        });
        $('histConteo').textContent = 'Mostrando ' + r.filas.length + ' de ' + r.total.toLocaleString('es-CO') + ' evaluaciones';
    } catch (e) {
        tbody.innerHTML = '';
        const tr = document.createElement('tr');
        const td = celda('Sin conexión a la base de datos', 'Estado');
        td.colSpan = 6;
        tr.appendChild(td);
        tbody.appendChild(tr);
    }
}

// =====================================================================
// CHAT DEL AGENTE (burbujas, indicador de escritura, sugerencias)
// =====================================================================
function iniciarChat() {
    agregarMensaje('agent', 'Hola, soy el agente de NetShield. Puedo contarte cuántos análisis hubo por nivel de riesgo, revisar las estadísticas y el feedback, mostrarte los últimos enlaces peligrosos o explicarte un veredicto paso a paso. ¿Qué quieres saber?');

    $('chatForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const t = $('chatInput').value.trim();
        if (t) { $('chatInput').value = ''; preguntarAlAgente(t); }
    });
    document.querySelectorAll('.chip').forEach(c => c.addEventListener('click', () => preguntarAlAgente(c.getAttribute('data-q'))));
}

function agregarMensaje(rol, texto, meta) {
    const log = $('chatLog');
    const d = document.createElement('div');
    d.className = 'msg ' + rol;
    const q = document.createElement('span');
    q.className = 'quien';
    q.textContent = rol === 'agent' ? 'AGENTE' : 'TÚ';
    d.appendChild(q);
    d.appendChild(document.createTextNode(texto));
    if (meta) {
        const m = document.createElement('span');
        m.className = 'meta';
        m.textContent = meta;
        d.appendChild(m);
    }
    log.appendChild(d);
    log.scrollTop = log.scrollHeight;
}

function escribiendo(on) {
    const t = $('chatTyping');
    t.innerHTML = '';
    if (on) {
        const dots = document.createElement('span');
        dots.className = 'dots';
        dots.setAttribute('aria-hidden', 'true');
        dots.innerHTML = '<i></i><i></i><i></i>';
        t.appendChild(dots);
        t.appendChild(document.createTextNode('El agente está escribiendo…'));
    }
}

let chatOcupado = false;
async function preguntarAlAgente(pregunta) {
    if (chatOcupado) return;
    chatOcupado = true;
    $('chatEnviar').disabled = true;
    agregarMensaje('user', pregunta);
    escribiendo(true);
    $('chatLog').scrollTop = $('chatLog').scrollHeight;

    try {
        const res = await api({ action: 'chat', mensaje: pregunta, historial: historialChat.slice(-8), contexto: ultimoResultado });
        escribiendo(false);
        if (res.status === 'success') {
            agregarMensaje('agent', res.respuesta, 'Motor: ' + res.motor + (res.ia_error ? ' · ' + res.ia_error : ''));
            historialChat.push({ rol: 'user', texto: pregunta }, { rol: 'agent', texto: res.respuesta });
        } else {
            agregarMensaje('agent', res.message || 'No pude responder en este momento.');
        }
    } catch (e) {
        escribiendo(false);
        agregarMensaje('agent', 'No pude conectarme con el servidor de NetShield. Revisa que XAMPP (Apache y MySQL) esté encendido.');
    } finally {
        chatOcupado = false;
        $('chatEnviar').disabled = false;
    }
}
