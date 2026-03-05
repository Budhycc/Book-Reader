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
const BOOK_URL = <?= json_encode($book) ?>

/* ── SETTINGS ── */
let fontSize   = parseInt(localStorage.getItem("reader-fontSize"))  || 100
let fontFamily = localStorage.getItem("reader-fontFamily") || "serif"
let darkMode   = localStorage.getItem("reader-darkMode") === "true"

document.getElementById("fontSelect").value = fontFamily

/* ── INIT ── */
const book      = ePub(BOOK_URL)
const rendition = book.renderTo("viewer", {
    width: "100%", height: "100%",
    spread: "none", flow: "paginated"
})

applySettings()

/* ── TITLE ── */
book.loaded.metadata.then(m => {
    document.getElementById("bookTitle").innerText = m.title || <?= json_encode($name) ?>
})

/* ── TOC ── */
book.loaded.navigation.then(toc => {
    const frag = document.createDocumentFragment()
    toc.forEach(ch => {
        const d = document.createElement("div")
        d.textContent = ch.label
        d.onclick = () => { rendition.display(ch.href); closeSidebar() }
        frag.appendChild(d)
    })
    document.getElementById("toc").replaceChildren(frag)
})

/* ── SWIPE (attach inside iframe after each render) ── */
rendition.on("rendered", (section, view) => {
    try { view.window.addEventListener("keydown", handleKey) } catch {}
    const doc = view.document
    if (!doc) return
    let tx = 0, ty = 0
    doc.addEventListener("touchstart", e => {
        tx = e.touches[0].clientX
        ty = e.touches[0].clientY
    }, { passive: true })
    doc.addEventListener("touchend", e => {
        const dx = e.changedTouches[0].clientX - tx
        const dy = e.changedTouches[0].clientY - ty
        if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.2) {
            dx < 0 ? rendition.next() : rendition.prev()
        }
    }, { passive: true })
})

/* ── DISPLAY + RESTORE ── */
book.ready.then(() => {
    const last = localStorage.getItem("epub-" + BOOK_URL)

    const doDisplay = cfi => cfi
        ? rendition.display(cfi).catch(() => rendition.display())
        : rendition.display()

    doDisplay(last).then(() => {
        // Hide loading screen once first page is rendered
        document.getElementById("loadingScreen").classList.add("hidden")
        // Generate locations in background for progress %
        book.locations.generate(2048).then(() => updateProgress())
    })
})

/* ── SAVE on every page turn (no gating) ── */
rendition.on("relocated", loc => {
    localStorage.setItem("epub-" + BOOK_URL, loc.start.cfi)
    updateProgress()
})

function updateProgress() {
    try {
        const loc = rendition.currentLocation()
        if (loc?.start && book.locations?.percentageFromCfi) {
            const pct = Math.floor(book.locations.percentageFromCfi(loc.start.cfi) * 100)
            document.getElementById("progress").innerText = pct + "%"
        }
    } catch {}
}

/* ── SETTINGS ── */
function applySettings() {
    rendition.themes.fontSize(fontSize + "%")
    rendition.themes.font(fontFamily)
    rendition.themes.override("background", darkMode ? "#111" : "#fff")
    rendition.themes.override("color",      darkMode ? "#eee" : "#000")
}
function biggerText()  { setFontSize(fontSize + 10) }
function smallerText() { setFontSize(fontSize - 10) }
function resetFont()   { setFontSize(100) }
function setFontSize(s) {
    fontSize = Math.max(70, s)
    rendition.themes.fontSize(fontSize + "%")
    localStorage.setItem("reader-fontSize", fontSize)
}
function changeFont(f) {
    fontFamily = f
    rendition.themes.font(f)
    localStorage.setItem("reader-fontFamily", f)
}
function toggleTheme() {
    darkMode = !darkMode
    localStorage.setItem("reader-darkMode", darkMode)
    applySettings()
}

/* ── TOOLBAR MORE ── */
function toggleMore() {
    document.getElementById("toolbarMore").classList.toggle("open")
}

/* ── SIDEBAR ── */
function toggleSidebar() {
    const isOpen = document.getElementById("sidebar").classList.toggle("open")
    document.getElementById("sidebarBackdrop").classList.toggle("open", isOpen)
    if (window.innerWidth >= 700)
        document.getElementById("viewer").classList.toggle("shift", isOpen)
}
function closeSidebar() {
    document.getElementById("sidebar").classList.remove("open")
    document.getElementById("sidebarBackdrop").classList.remove("open")
    document.getElementById("viewer").classList.remove("shift")
}

/* ── NAVIGATION ── */
function prevPage() { rendition.prev() }
function nextPage() { rendition.next() }

/* ── KEYBOARD ── */
function handleKey(e) {
    if (!rendition) return
    if (["ArrowRight","ArrowDown"," "].includes(e.key))  { e.preventDefault(); rendition.next() }
    else if (["ArrowLeft","ArrowUp"].includes(e.key))    { e.preventDefault(); rendition.prev() }
    else if (e.key === "Escape")                         { window.location.href = "index.php" }
}
window.addEventListener("keydown", handleKey)
</script>
</body>
</html>