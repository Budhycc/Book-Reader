<?php
$book = isset($_GET['book']) ? $_GET['book'] : '';
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

<div id="loadingScreen">
    <div class="spinner"></div>
    <div>Membuka buku...</div>
</div>

<!-- Brightness overlay -->
<div id="brightnessOverlay"></div>

<!-- Search overlay -->
<div id="searchOverlay" class="overlay-panel">
    <div class="overlay-header">
        <span>🔍 Cari Teks</span>
        <button onclick="closeSearch()">✕</button>
    </div>
    <div class="search-input-wrap">
        <input type="text" id="searchInput" placeholder="Ketik kata/frasa..." oninput="doSearch()">
        <span id="searchCount"></span>
    </div>
    <div id="searchResults"></div>
</div>

<!-- Bookmark overlay -->
<div id="bookmarkOverlay" class="overlay-panel">
    <div class="overlay-header">
        <span>🔖 Bookmark</span>
        <button onclick="closeBookmarks()">✕</button>
    </div>
    <div id="bookmarkList"></div>
    <div id="bookmarkEmpty" style="display:none">Belum ada bookmark.</div>
</div>

<!-- Stats overlay -->
<div id="statsOverlay" class="overlay-panel">
    <div class="overlay-header">
        <span>📊 Statistik Baca</span>
        <button onclick="closeStats()">✕</button>
    </div>
    <div id="statsContent"></div>
</div>

<div id="app">

    <!-- Main toolbar -->
    <div class="toolbar" id="toolbar">
        <button class="t-btn" onclick="toggleSidebar()" title="Chapters">☰</button>
        <a class="t-btn" href="index.php" title="Library">🏠</a>
        <button class="t-btn" onclick="prevPage()" title="Sebelumnya">◀</button>
        <button class="t-btn" onclick="nextPage()" title="Berikutnya">▶</button>
        <span id="bookTitle"></span>
        <span id="progress"></span>
        <button class="t-btn" onclick="toggleBookmarkCurrent()" id="bmBtn" title="Bookmark halaman ini">🔖</button>
        <button class="t-btn" onclick="openSearch()" title="Cari teks">🔍</button>
        <button class="t-btn" onclick="toggleSettings()" title="Pengaturan">⚙</button>
    </div>

    <!-- Progress bar -->
    <div id="progressBar">
        <div id="progressFill"></div>
        <div id="etaLabel"></div>
    </div>

    <!-- Settings drawer -->
    <div id="settingsDrawer">
        <div class="settings-row">
            <label>Ukuran</label>
            <div class="btn-group">
                <button class="t-btn" onclick="smallerText()">A−</button>
                <button class="t-btn" onclick="biggerText()">A+</button>
                <button class="t-btn" onclick="resetFont()">↺</button>
            </div>
        </div>
        <div class="settings-row">
            <label>Font</label>
            <select id="fontSelect" onchange="changeFont(this.value)">
                <option value="serif">Serif</option>
                <option value="sans-serif">Sans</option>
                <option value="Georgia">Georgia</option>
                <option value="Times New Roman">Times</option>
            </select>
        </div>
        <div class="settings-row">
            <label>Tema</label>
            <div class="btn-group theme-btns">
                <button class="theme-btn" data-theme="light" onclick="setTheme('light')">☀ Terang</button>
                <button class="theme-btn" data-theme="sepia" onclick="setTheme('sepia')">📜 Sepia</button>
                <button class="theme-btn" data-theme="dark" onclick="setTheme('dark')">🌙 Gelap</button>
            </div>
        </div>
        <div class="settings-row">
            <label>Mode</label>
            <div class="btn-group">
                <button class="t-btn" id="flowBtn" onclick="toggleFlow()">📄 Scroll</button>
                <a class="t-btn" id="downloadBtn" title="Download">⬇ Unduh</a>
            </div>
        </div>
        <div class="settings-row">
            <label>Redup</label>
            <input type="range" id="brightnessSlider" min="0" max="80" value="0" oninput="setBrightness(this.value)">
        </div>
        <div class="settings-row">
            <label>Spasi</label>
            <input type="range" id="lineSpacingSlider" min="1.0" max="3.0" step="0.1" value="1.6"
                   oninput="setLineSpacing(parseFloat(this.value))">
            <span id="lineSpacingVal" class="slider-val">1.6</span>
        </div>
        <div class="settings-row">
            <label>Margin</label>
            <input type="range" id="marginSlider" min="0" max="10" step="1" value="4"
                   oninput="setMargin(parseInt(this.value))">
            <span id="marginVal" class="slider-val">4%</span>
        </div>
        <div class="settings-row actions-row">
            <button class="action-btn" onclick="openBookmarks()">🔖 Bookmark</button>
            <button class="action-btn" onclick="openStats()">📊 Statistik</button>
        </div>
    </div>

    <div id="viewer"></div>

</div>

<!-- Sidebar -->
<div id="sidebarBackdrop" onclick="closeSidebar()"></div>
<div id="sidebar">
    <div id="sidebarHeader">
        <span>📑 Chapters</span>
        <button onclick="closeSidebar()">✕</button>
    </div>
    <div id="toc"></div>
</div>

<!-- Toast notification -->
<div id="toast"></div>

<script>
const BOOK_URL = <?= json_encode($book) ?>;
const BOOK_KEY = "epub-" + BOOK_URL;

/* ── CUSTOM REQUEST ── */
const customRequest = (url, type) => {
    const endpoint = `get-epub-part.php?book=${encodeURIComponent(BOOK_URL)}&file=${encodeURIComponent(url)}`;
    return fetch(endpoint).then(response => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        if (type === 'xml') return response.text().then(str => new DOMParser().parseFromString(str, 'application/xml'));
        if (type === 'json') return response.json();
        if (type === 'blob') return response.blob();
        return response.text();
    });
};

/* ── SETTINGS STATE ── */
let fontSize    = parseInt(localStorage.getItem("reader-fontSize"))    || 100;
let fontFamily  = localStorage.getItem("reader-fontFamily")            || "serif";
let theme       = localStorage.getItem("reader-theme")                 || "light";
let flowMode    = localStorage.getItem("reader-flow")                  || "paginated";
let lineSpacing = parseFloat(localStorage.getItem("reader-lineSpacing")) || 1.6;
let margin      = parseInt(localStorage.getItem("reader-margin"))      ?? 4;
let currentPct  = 0;

/* ── THEME CONFIGS ── */
const THEMES = {
    light: { bg: "#ffffff", color: "#1a1a1a" },
    sepia: { bg: "#f4ecd8", color: "#3b2f1e" },
    dark:  { bg: "#111111", color: "#e8e0d5" }
};

document.getElementById("fontSelect").value = fontFamily;

/* ── Sinkronkan slider dengan nilai tersimpan ── */
document.getElementById("lineSpacingSlider").value = lineSpacing;
document.getElementById("lineSpacingVal").textContent = lineSpacing.toFixed(1);
document.getElementById("marginSlider").value = margin;
document.getElementById("marginVal").textContent = margin + "%";

/* ── DOWNLOAD BUTTON ── */
const dlBtn = document.getElementById("downloadBtn");
dlBtn.href = BOOK_URL;
dlBtn.setAttribute("download", BOOK_URL.split("/").pop());

/* ── EPUB INIT ── */
const book = ePub(BOOK_URL, { request: customRequest });
const rendition = book.renderTo("viewer", {
    width: "100%", height: "100%",
    spread: "none", flow: flowMode,
    sandbox: 'allow-same-origin allow-scripts'
});

applySettings();
updateFlowBtn();
updateThemeBtns();

/* ── TITLE ── */
book.loaded.metadata.then(m => {
    document.getElementById("bookTitle").innerText = m.title || <?= json_encode($name) ?>;
}).catch(() => {});

/* ── TOC ── */
book.loaded.navigation.then(toc => {
    const frag = document.createDocumentFragment();
    toc.forEach(ch => {
        const d = document.createElement("div");
        d.textContent = ch.label;
        d.onclick = () => { rendition.display(ch.href).catch(() => {}); closeSidebar(); };
        frag.appendChild(d);
    });
    document.getElementById("toc").replaceChildren(frag);
}).catch(() => {});

/* ── SWIPE ── */
rendition.on("rendered", (section, view) => {
    try { view.window.addEventListener("keydown", handleKey); } catch {}
    const doc = view.document;
    if (!doc) return;
    let tx = 0, ty = 0;
    doc.addEventListener("touchstart", e => { tx = e.touches[0].clientX; ty = e.touches[0].clientY; }, { passive: true });
    doc.addEventListener("touchend", e => {
        const dx = e.changedTouches[0].clientX - tx;
        const dy = e.changedTouches[0].clientY - ty;
        if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.2) dx < 0 ? rendition.next() : rendition.prev();
    }, { passive: true });
    // Tap tengah layar: toggle toolbar (auto-hide)
    doc.addEventListener("click", e => {
        const w = doc.documentElement.clientWidth;
        if (e.clientX > w * 0.3 && e.clientX < w * 0.7) toggleToolbarVisibility();
    });
});

/* ── DISPLAY + RESTORE ── */
book.ready
    .then(() => {
        const last = localStorage.getItem(BOOK_KEY);
        return last ? rendition.display(last) : rendition.display();
    })
    .then(() => {
        document.getElementById("loadingScreen").classList.add("hidden");
        return book.locations.generate(2048);
    })
    .then(() => updateProgress())
    .catch(err => {
        document.getElementById("loadingScreen").classList.add("hidden");
        alert('Gagal membuka buku: ' + err.message);
    });

/* ── RELOCATED ── */
rendition.on("relocated", loc => {
    localStorage.setItem(BOOK_KEY, loc.start.cfi);
    updateProgress();
    updateBookmarkBtn();
    trackReadingTime();
});

/* ────────────────────────────────────────────
   PROGRESS BAR + ETA
──────────────────────────────────────────── */
function updateProgress() {
    try {
        const loc = rendition.currentLocation();
        if (loc?.start && book.locations?.percentageFromCfi) {
            const pct = Math.floor(book.locations.percentageFromCfi(loc.start.cfi) * 100);
            currentPct = pct;
            document.getElementById("progress").innerText = pct + "%";
            document.getElementById("progressFill").style.width = pct + "%";
            const remaining = 100 - pct;
            const estMin = Math.round(remaining * 1.8);
            if (estMin > 0) {
                document.getElementById("etaLabel").innerText =
                    estMin >= 60 ? `±${Math.floor(estMin/60)}j ${estMin%60}m` : `±${estMin} mnt tersisa`;
            } else {
                document.getElementById("etaLabel").innerText = "Hampir selesai!";
            }
        }
    } catch {}
}

/* ────────────────────────────────────────────
   SETTINGS APPLY
──────────────────────────────────────────── */
function applySettings() {
    const t = THEMES[theme] || THEMES.light;
    rendition.themes.fontSize(fontSize + "%");
    rendition.themes.font(fontFamily);
    rendition.themes.override("background", t.bg);
    rendition.themes.override("color", t.color);
    rendition.themes.override("line-height", lineSpacing.toString());
    rendition.themes.override("padding-left",  margin + "%");
    rendition.themes.override("padding-right", margin + "%");
    document.body.setAttribute("data-theme", theme);
}

function biggerText()  { setFontSize(fontSize + 10); }
function smallerText() { setFontSize(fontSize - 10); }
function resetFont()   { setFontSize(100); }
function setFontSize(s) {
    fontSize = Math.max(70, Math.min(200, s));
    rendition.themes.fontSize(fontSize + "%");
    localStorage.setItem("reader-fontSize", fontSize);
}
function changeFont(f) {
    fontFamily = f;
    rendition.themes.font(f);
    localStorage.setItem("reader-fontFamily", f);
}
function setLineSpacing(val) {
    lineSpacing = val;
    document.getElementById("lineSpacingVal").textContent = val.toFixed(1);
    rendition.themes.override("line-height", val.toString());
    localStorage.setItem("reader-lineSpacing", val);
}
function setMargin(val) {
    margin = val;
    document.getElementById("marginVal").textContent = val + "%";
    rendition.themes.override("padding-left",  val + "%");
    rendition.themes.override("padding-right", val + "%");
    localStorage.setItem("reader-margin", val);
}

/* ────────────────────────────────────────────
   THEME (light / sepia / dark)
──────────────────────────────────────────── */
function setTheme(t) {
    theme = t;
    localStorage.setItem("reader-theme", t);
    applySettings();
    updateThemeBtns();
}
function updateThemeBtns() {
    document.querySelectorAll(".theme-btn").forEach(b => {
        b.classList.toggle("active", b.dataset.theme === theme);
    });
}

/* ────────────────────────────────────────────
   FLOW MODE (paginated ↔ scroll)
──────────────────────────────────────────── */
function toggleFlow() {
    flowMode = flowMode === "paginated" ? "scrolled-doc" : "paginated";
    localStorage.setItem("reader-flow", flowMode);
    const loc = rendition.currentLocation();
    const cfi = loc?.start?.cfi;
    rendition.flow(flowMode);
    if (cfi) rendition.display(cfi).catch(() => {});
    updateFlowBtn();
}
function updateFlowBtn() {
    const btn = document.getElementById("flowBtn");
    btn.textContent = flowMode === "paginated" ? "📄 Scroll" : "📖 Halaman";
}

/* ────────────────────────────────────────────
   BRIGHTNESS OVERLAY
──────────────────────────────────────────── */
function setBrightness(val) {
    const alpha = val / 100;
    document.getElementById("brightnessOverlay").style.opacity = alpha;
}

/* ────────────────────────────────────────────
   AUTO-HIDE TOOLBAR
──────────────────────────────────────────── */
let toolbarVisible = true;
let toolbarTimeout = null;
function toggleToolbarVisibility() {
    toolbarVisible = !toolbarVisible;
    const toolbar = document.getElementById("toolbar");
    const pb = document.getElementById("progressBar");
    toolbar.classList.toggle("hidden", !toolbarVisible);
    pb.classList.toggle("toolbar-hidden", !toolbarVisible);
}
function resetToolbarTimer() {
    clearTimeout(toolbarTimeout);
    if (!document.getElementById("settingsDrawer").classList.contains("open")) {
        toolbarTimeout = setTimeout(() => {
            if (toolbarVisible) toggleToolbarVisibility();
        }, 5000);
    }
}
document.getElementById("viewer").addEventListener("touchstart", resetToolbarTimer, { passive: true });

/* ────────────────────────────────────────────
   SETTINGS DRAWER
──────────────────────────────────────────── */
function toggleSettings() {
    document.getElementById("settingsDrawer").classList.toggle("open");
    clearTimeout(toolbarTimeout);
}
function closeSettings() {
    document.getElementById("settingsDrawer").classList.remove("open");
}

/* ────────────────────────────────────────────
   SIDEBAR / TOC
──────────────────────────────────────────── */
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

/* ────────────────────────────────────────────
   BOOKMARK
──────────────────────────────────────────── */
function getBookmarks() {
    try { return JSON.parse(localStorage.getItem("bm-" + BOOK_URL) || "[]"); } catch { return []; }
}
function saveBookmarks(bms) {
    localStorage.setItem("bm-" + BOOK_URL, JSON.stringify(bms));
}
function toggleBookmarkCurrent() {
    const loc = rendition.currentLocation();
    if (!loc?.start?.cfi) return;
    const cfi = loc.start.cfi;
    let bms = getBookmarks();
    const idx = bms.findIndex(b => b.cfi === cfi);
    if (idx >= 0) {
        bms.splice(idx, 1);
        showToast("Bookmark dihapus");
    } else {
        bms.push({ cfi, pct: currentPct, label: `Hal. ${currentPct}%`, time: Date.now() });
        showToast("Bookmark ditambahkan 🔖");
    }
    saveBookmarks(bms);
    updateBookmarkBtn();
}
function updateBookmarkBtn() {
    const loc = rendition.currentLocation();
    if (!loc?.start?.cfi) return;
    const cfi = loc.start.cfi;
    const bms = getBookmarks();
    document.getElementById("bmBtn").classList.toggle("active-btn", bms.some(b => b.cfi === cfi));
}
function openBookmarks() {
    closeSettings();
    const bms = getBookmarks();
    const list = document.getElementById("bookmarkList");
    const empty = document.getElementById("bookmarkEmpty");
    list.innerHTML = "";
    if (bms.length === 0) { empty.style.display = ""; }
    else {
        empty.style.display = "none";
        bms.sort((a,b) => a.pct - b.pct).forEach(bm => {
            const d = document.createElement("div");
            d.className = "bm-item";
            const date = new Date(bm.time).toLocaleDateString("id-ID");
            d.innerHTML = `<span onclick="jumpToCfi('${bm.cfi}')">📖 ${bm.pct}% &mdash; <small>${date}</small></span>
                           <button onclick="deleteBookmark('${bm.cfi}')">🗑</button>`;
            list.appendChild(d);
        });
    }
    document.getElementById("bookmarkOverlay").classList.add("open");
}
function closeBookmarks() { document.getElementById("bookmarkOverlay").classList.remove("open"); }
function deleteBookmark(cfi) {
    let bms = getBookmarks().filter(b => b.cfi !== cfi);
    saveBookmarks(bms);
    openBookmarks();
    updateBookmarkBtn();
}
function jumpToCfi(cfi) {
    rendition.display(cfi).catch(() => {});
    closeBookmarks();
    closeSearch();
}

/* ────────────────────────────────────────────
   SEARCH
──────────────────────────────────────────── */
let searchDebounce = null;
function openSearch() {
    closeSettings();
    document.getElementById("searchOverlay").classList.add("open");
    setTimeout(() => document.getElementById("searchInput").focus(), 100);
}
function closeSearch() { document.getElementById("searchOverlay").classList.remove("open"); }
function doSearch() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(async () => {
        const q = document.getElementById("searchInput").value.trim();
        const resultsEl = document.getElementById("searchResults");
        const countEl   = document.getElementById("searchCount");
        if (q.length < 2) { resultsEl.innerHTML = ""; countEl.textContent = ""; return; }
        resultsEl.innerHTML = '<div class="search-loading">Mencari...</div>';
        try {
            const results = await book.search(q);
            countEl.textContent = results.length ? `${results.length} hasil` : "Tidak ditemukan";
            if (!results.length) { resultsEl.innerHTML = '<div class="search-loading">Tidak ditemukan.</div>'; return; }
            resultsEl.innerHTML = "";
            results.slice(0, 50).forEach(r => {
                const d = document.createElement("div");
                d.className = "search-item";
                const excerpt = r.excerpt.replace(new RegExp(q, "gi"), m => `<mark>${m}</mark>`);
                d.innerHTML = `<p>${excerpt}</p>`;
                d.onclick = () => { rendition.display(r.cfi).catch(() => {}); closeSearch(); };
                resultsEl.appendChild(d);
            });
        } catch(e) {
            resultsEl.innerHTML = '<div class="search-loading">Gagal mencari.</div>';
        }
    }, 400);
}

/* ────────────────────────────────────────────
   READING STATS
──────────────────────────────────────────── */
let sessionStart = Date.now();
let sessionPages = 0;
function trackReadingTime() {
    sessionPages++;
    const today = new Date().toISOString().slice(0,10);
    const statsKey = "stats-" + BOOK_URL;
    let stats = {};
    try { stats = JSON.parse(localStorage.getItem(statsKey) || "{}"); } catch {}
    if (!stats[today]) stats[today] = { minutes: 0, pages: 0 };
    if (sessionPages % 3 === 0) stats[today].minutes++;
    stats[today].pages++;
    localStorage.setItem(statsKey, JSON.stringify(stats));
}
function openStats() {
    closeSettings();
    const statsKey = "stats-" + BOOK_URL;
    let stats = {};
    try { stats = JSON.parse(localStorage.getItem(statsKey) || "{}"); } catch {}
    const keys = Object.keys(stats).sort().reverse();
    const totalPages = keys.reduce((s,k) => s + (stats[k].pages||0), 0);
    const totalMin   = keys.reduce((s,k) => s + (stats[k].minutes||0), 0);
    const streak     = calcStreak(keys);
    let html = `
        <div class="stat-card">
            <div class="stat-num">${currentPct}%</div>
            <div class="stat-lbl">Progress buku</div>
        </div>
        <div class="stat-card">
            <div class="stat-num">${totalPages}</div>
            <div class="stat-lbl">Halaman dibaca</div>
        </div>
        <div class="stat-card">
            <div class="stat-num">${totalMin >= 60 ? Math.floor(totalMin/60)+"j "+totalMin%60+"m" : totalMin+"m"}</div>
            <div class="stat-lbl">Total waktu</div>
        </div>
        <div class="stat-card">
            <div class="stat-num">${streak} 🔥</div>
            <div class="stat-lbl">Hari berturut</div>
        </div>
        <h4 style="margin:16px 0 8px;font-size:13px;color:var(--muted)">RIWAYAT 7 HARI</h4>
    `;
    const today = new Date().toISOString().slice(0,10);
    for (let i = 0; i < 7; i++) {
        const d = new Date(); d.setDate(d.getDate() - i);
        const dk = d.toISOString().slice(0,10);
        const s = stats[dk] || { pages: 0, minutes: 0 };
        const label = dk === today ? "Hari ini" : d.toLocaleDateString("id-ID", {weekday:"short", day:"numeric", month:"short"});
        const barW = Math.min(100, s.pages * 5);
        html += `<div class="stat-day">
            <span class="stat-day-lbl">${label}</span>
            <div class="stat-bar-wrap"><div class="stat-bar" style="width:${barW}%"></div></div>
            <span class="stat-day-val">${s.pages}p</span>
        </div>`;
    }
    document.getElementById("statsContent").innerHTML = html;
    document.getElementById("statsOverlay").classList.add("open");
}
function closeStats() { document.getElementById("statsOverlay").classList.remove("open"); }
function calcStreak(sortedDays) {
    if (!sortedDays.length) return 0;
    let streak = 0;
    const today = new Date().toISOString().slice(0,10);
    let cur = new Date(today);
    for (const dk of [...sortedDays].sort().reverse()) {
        const expected = cur.toISOString().slice(0,10);
        if (dk === expected) { streak++; cur.setDate(cur.getDate()-1); }
        else break;
    }
    return streak;
}

/* ────────────────────────────────────────────
   NAVIGATION
──────────────────────────────────────────── */
function prevPage() { rendition.prev(); }
function nextPage() { rendition.next(); }

/* ── KEYBOARD ── */
function handleKey(e) {
    if (!rendition) return;
    if (["ArrowRight","ArrowDown"," "].includes(e.key)) { e.preventDefault(); rendition.next(); }
    else if (["ArrowLeft","ArrowUp"].includes(e.key))   { e.preventDefault(); rendition.prev(); }
    else if (e.key === "Escape") window.location.href = "index.php";
    else if (e.key === "f" || e.key === "F") openSearch();
    else if (e.key === "b" || e.key === "B") toggleBookmarkCurrent();
}
window.addEventListener("keydown", handleKey);

/* ────────────────────────────────────────────
   TOAST
──────────────────────────────────────────── */
let toastTimeout = null;
function showToast(msg) {
    const t = document.getElementById("toast");
    t.textContent = msg;
    t.classList.add("show");
    clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => t.classList.remove("show"), 2500);
}
</script>
</body>
</html>