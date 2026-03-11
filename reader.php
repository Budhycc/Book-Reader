<?php
// Validate and sanitize the book path
$book = isset($_GET['book']) ? $_GET['book'] : '';

// Security: only allow files inside books/ directory with .epub extension
if (!preg_match('/^books\/[^\/]+\.epub$/', $book) || !file_exists($book)) {
    http_response_code(404);
    exit('Book not found.');
}

$name = basename($book, ".epub");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title><?= htmlspecialchars($name) ?> — EPUB Reader</title>
    <script src="js/jszip.min.js"></script>
    <script src="js/epub.min.js"></script>
    <link rel="stylesheet" href="reader.css">
</head>
<body>

<!-- Loading screen shown until book renders -->
<div id="loadingScreen">
    <div class="spinner"></div>
    <div>Membuka buku...</div>
</div>

<div id="app">

    <div class="toolbar">
        <button class="t-btn" onclick="toggleSidebar()">☰</button>
        <a class="t-btn" href="index.php" title="Kembali ke library">🏠</a>
        <a class="t-btn" id="downloadBtn" title="Download buku">⬇</a>
        <button class="t-btn" onclick="prevPage()">◀</button>
        <button class="t-btn" onclick="nextPage()">▶</button>
        <span id="bookTitle"></span>
        <span id="progress"></span>
        <button class="t-btn" onclick="toggleMore()">⚙</button>
    </div>

    <div id="toolbarMore">
        <button class="t-btn" onclick="smallerText()">A−</button>
        <button class="t-btn" onclick="biggerText()">A+</button>
        <button class="t-btn" onclick="resetFont()">↺</button>
        <select id="fontSelect" onchange="changeFont(this.value)">
            <option value="serif">Serif</option>
            <option value="sans-serif">Sans</option>
            <option value="Georgia">Georgia</option>
            <option value="Times New Roman">Times</option>
        </select>
        <button class="t-btn" onclick="toggleTheme()">🌙</button>
    </div>

    <div id="viewer"></div>

</div>

<!-- Sidebar (outside #app so it overlays correctly) -->
<div id="sidebarBackdrop" onclick="closeSidebar()"></div>
<div id="sidebar">
    <div id="sidebarHeader">
        <b>📑 Chapters</b>
        <span class="closeSidebar" onclick="closeSidebar()">✕</span>
    </div>
    <div id="toc"></div>
</div>

<script>
const BOOK_URL = <?= json_encode($book) ?>;

/* ── CUSTOM REQUEST FUNCTION (DENGAN PARAMETER BOOK YANG BENAR) ── */
const customRequest = (url, type) => {
    // Tampilkan log untuk debugging (bisa dihapus jika sudah stabil)
    console.log('Requesting:', url, 'type:', type);
    // Perbaiki: tambahkan parameter 'book=' di query string
    const endpoint = `get-epub-part.php?book=${encodeURIComponent(BOOK_URL)}&file=${encodeURIComponent(url)}`;
    return fetch(endpoint)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status} - ${response.statusText}`);
            }
            // Baca response berdasarkan tipe yang diminta
            if (type === 'xml') {
                return response.text().then(str => {
                    // Ubah string XML menjadi dokumen XML
                    return new DOMParser().parseFromString(str, 'application/xml');
                });
            } else if (type === 'json') {
                return response.json();
            } else if (type === 'blob') {
                return response.blob();
            } else {
                // default: teks biasa
                return response.text();
            }
        })
        .catch(error => {
            console.error('Custom request failed:', error);
            throw error;
        });
};

/* ── SETTINGS ── */
let fontSize   = parseInt(localStorage.getItem("reader-fontSize"))  || 100;
let fontFamily = localStorage.getItem("reader-fontFamily") || "serif";
let darkMode   = localStorage.getItem("reader-darkMode") === "true";

document.getElementById("fontSelect").value = fontFamily;

/* ── DOWNLOAD BUTTON ── */
const dlBtn = document.getElementById("downloadBtn");
dlBtn.href = BOOK_URL;
dlBtn.setAttribute("download", BOOK_URL.split("/").pop());

/* ── INIT ── */
// Gunakan object konfigurasi dengan properti 'request'
const book = ePub(BOOK_URL, { request: customRequest });
const rendition = book.renderTo("viewer", {
    width: "100%", height: "100%",
    spread: "none", flow: "paginated",
    sandbox: 'allow-same-origin allow-scripts'
});

applySettings();

/* ── TITLE ── */
book.loaded.metadata.then(m => {
    document.getElementById("bookTitle").innerText = m.title || <?= json_encode($name) ?>;
}).catch(err => console.error('Metadata error:', err));

/* ── TOC ── */
book.loaded.navigation.then(toc => {
    const frag = document.createDocumentFragment();
    toc.forEach(ch => {
        const d = document.createElement("div");
        d.textContent = ch.label;
        d.onclick = () => { rendition.display(ch.href).catch(e => console.error(e)); closeSidebar(); };
        frag.appendChild(d);
    });
    document.getElementById("toc").replaceChildren(frag);
}).catch(err => console.error('TOC error:', err));

/* ── SWIPE (attach inside iframe after each render) ── */
rendition.on("rendered", (section, view) => {
    try { view.window.addEventListener("keydown", handleKey); } catch {}
    const doc = view.document;
    if (!doc) return;
    let tx = 0, ty = 0;
    doc.addEventListener("touchstart", e => {
        tx = e.touches[0].clientX;
        ty = e.touches[0].clientY;
    }, { passive: true });
    doc.addEventListener("touchend", e => {
        const dx = e.changedTouches[0].clientX - tx;
        const dy = e.changedTouches[0].clientY - ty;
        if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.2) {
            dx < 0 ? rendition.next() : rendition.prev();
        }
    }, { passive: true });
});

/* ── DISPLAY + RESTORE ── */
book.ready
    .then(() => {
        const last = localStorage.getItem("epub-" + BOOK_URL);
        const displayPromise = last ? rendition.display(last) : rendition.display();
        return displayPromise;
    })
    .then(() => {
        // Sembunyikan loading setelah buku tampil
        document.getElementById("loadingScreen").classList.add("hidden");
        // Generate locations untuk progress bar
        return book.locations.generate(2048);
    })
    .then(() => updateProgress())
    .catch(err => {
        console.error('Display error:', err);
        // Tetap sembunyikan loading agar tidak stuck
        document.getElementById("loadingScreen").classList.add("hidden");
        alert('Gagal membuka buku: ' + err.message);
    });

/* ── SAVE on every page turn ── */
rendition.on("relocated", loc => {
    localStorage.setItem("epub-" + BOOK_URL, loc.start.cfi);
    updateProgress();
});

function updateProgress() {
    try {
        const loc = rendition.currentLocation();
        if (loc?.start && book.locations?.percentageFromCfi) {
            const pct = Math.floor(book.locations.percentageFromCfi(loc.start.cfi) * 100);
            document.getElementById("progress").innerText = pct + "%";
        }
    } catch {}
}

/* ── SETTINGS ── */
function applySettings() {
    rendition.themes.fontSize(fontSize + "%");
    rendition.themes.font(fontFamily);
    rendition.themes.override("background", darkMode ? "#111" : "#fff");
    rendition.themes.override("color",      darkMode ? "#eee" : "#000");
}
function biggerText()  { setFontSize(fontSize + 10); }
function smallerText() { setFontSize(fontSize - 10); }
function resetFont()   { setFontSize(100); }
function setFontSize(s) {
    fontSize = Math.max(70, s);
    rendition.themes.fontSize(fontSize + "%");
    localStorage.setItem("reader-fontSize", fontSize);
}
function changeFont(f) {
    fontFamily = f;
    rendition.themes.font(f);
    localStorage.setItem("reader-fontFamily", f);
}
function toggleTheme() {
    darkMode = !darkMode;
    localStorage.setItem("reader-darkMode", darkMode);
    applySettings();
}

/* ── TOOLBAR MORE ── */
function toggleMore() {
    document.getElementById("toolbarMore").classList.toggle("open");
}

/* ── SIDEBAR ── */
function toggleSidebar() {
    const isOpen = document.getElementById("sidebar").classList.toggle("open");
    document.getElementById("sidebarBackdrop").classList.toggle("open", isOpen);
    if (window.innerWidth >= 700)
        document.getElementById("viewer").classList.toggle("shift", isOpen);
}
function closeSidebar() {
    document.getElementById("sidebar").classList.remove("open");
    document.getElementById("sidebarBackdrop").classList.remove("open");
    document.getElementById("viewer").classList.remove("shift");
}

/* ── NAVIGATION ── */
function prevPage() { rendition.prev(); }
function nextPage() { rendition.next(); }

/* ── KEYBOARD ── */
function handleKey(e) {
    if (!rendition) return;
    if (["ArrowRight","ArrowDown"," "].includes(e.key))  { e.preventDefault(); rendition.next(); }
    else if (["ArrowLeft","ArrowUp"].includes(e.key))    { e.preventDefault(); rendition.prev(); }
    else if (e.key === "Escape")                         { window.location.href = "index.php"; }
}
window.addEventListener("keydown", handleKey);
</script>
</body>
</html>