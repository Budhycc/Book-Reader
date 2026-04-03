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
    <link rel="icon" type="image/x-icon" href="/img/favicon.ico">
    <script src="js/jszip.min.js"></script>
    <script src="js/epub.min.js"></script>
    <link rel="stylesheet" href="reader.css">
</head>
<body>

<div id="loadingScreen">
    <div class="spinner"></div>
    <div>Membuka buku...</div>
</div>

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

    <div class="toolbar" id="toolbar">
        <button class="t-btn" onclick="toggleSidebar()" title="Chapters">☰</button>
        <a class="t-btn" href="index.php" title="Library">🏠</a>
        <button class="t-btn" onclick="prevPage()" title="Sebelumnya">◀</button>
        <button class="t-btn" onclick="nextPage()" title="Berikutnya">▶</button>
        <span id="bookTitle"></span>
        <span id="progress"></span>
        <button class="t-btn" onclick="toggleBookmarkCurrent()" id="bmBtn" title="Bookmark">🔖</button>
        <button class="t-btn" onclick="openSearch()" title="Cari">🔍</button>
        <button class="t-btn" onclick="toggleSettings()" title="Pengaturan">⚙</button>
    </div>

    <div id="progressBar">
        <div id="progressFill"></div>
        <div id="etaLabel"></div>
    </div>

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
            <label>Swipe</label>
            <div class="btn-group">
                <button class="t-btn" id="swipeToggleBtn" onclick="toggleSwipe()">👆 Aktif</button>
            </div>
            <span id="swipeStatusLabel" style="font-size:11px;color:var(--muted);margin-left:4px;align-self:center;"></span>
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

    <!-- ── SCROLL-MODE FOOTER NAV ── -->
    <div id="scrollFooter">
        <button id="sfPrev" class="sf-btn" onclick="prevPage()">
            <span class="sf-arrow">←</span>
            <span class="sf-texts">
                <span class="sf-name" id="sfPrevLabel">sebelumnya</span>
            </span>
        </button>

        <div id="sfCenter">
            <div id="sfChapterName"></div>
            <div id="sfChapterIndex"></div>
        </div>

        <button id="sfNext" class="sf-btn" onclick="nextPage()">
            <span class="sf-texts sf-texts-right">
                <span class="sf-name" id="sfNextLabel">berikutnya</span>
            </span>
            <span class="sf-arrow">→</span>
        </button>
    </div>

</div>

<div id="sidebarBackdrop" onclick="closeSidebar()"></div>
<div id="sidebar">
    <div id="sidebarHeader">
        <span>📑 Chapters</span>
        <button onclick="closeSidebar()">✕</button>
    </div>
    <div id="toc"></div>
</div>

<div id="toast"></div>

<script>
const BOOK_URL = <?= json_encode($book) ?>;
const BOOK_KEY = "epub-" + BOOK_URL;

/* ── CUSTOM REQUEST ── */
const customRequest = (url, type) => {
    const endpoint = `get-epub-part.php?book=${encodeURIComponent(BOOK_URL)}&file=${encodeURIComponent(url)}`;
    return fetch(endpoint).then(r => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        if (type === 'xml')  return r.text().then(s => new DOMParser().parseFromString(s, 'application/xml'));
        if (type === 'json') return r.json();
        if (type === 'blob') return r.blob();
        return r.text();
    });
};

/* ── STATE ── */
let fontSize    = parseInt(localStorage.getItem("reader-fontSize"))      || 100;
let fontFamily  = localStorage.getItem("reader-fontFamily")              || "serif";
let theme       = localStorage.getItem("reader-theme")                   || "light";
let flowMode    = localStorage.getItem("reader-flow")                    || "paginated";
let lineSpacing = parseFloat(localStorage.getItem("reader-lineSpacing")) || 1.6;
let margin      = parseInt(localStorage.getItem("reader-margin"))        ?? 4;
let currentPct  = 0;
let swipeEnabled = localStorage.getItem("reader-swipe") !== "false";
let tocItems    = [];

const THEMES = {
    light: { bg: "#ffffff", color: "#1a1a1a" },
    sepia: { bg: "#f4ecd8", color: "#3b2f1e" },
    dark:  { bg: "#111111", color: "#e8e0d5" }
};

/* ── INIT UI ── */
document.getElementById("fontSelect").value = fontFamily;
document.getElementById("lineSpacingSlider").value = lineSpacing;
document.getElementById("lineSpacingVal").textContent = lineSpacing.toFixed(1);
document.getElementById("marginSlider").value = margin;
document.getElementById("marginVal").textContent = margin + "%";
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

/* ────────────────────────────────────────────
   SETTINGS — apply all at once via a single
   injected stylesheet. This is the reliable
   way to style epub.js iframe content.
──────────────────────────────────────────── */
function buildCss() {
    const t = THEMES[theme] || THEMES.light;
    return `
        html, body {
            background: ${t.bg} !important;
            color: ${t.color} !important;
        }
        body {
            font-family: ${fontFamily} !important;
            font-size: ${fontSize}% !important;
            line-height: ${lineSpacing} !important;
            padding-left: ${margin}% !important;
            padding-right: ${margin}% !important;
            margin: 0 !important;
        }
        p, div, span, li, td, th, blockquote {
            line-height: ${lineSpacing} !important;
        }
        img, svg {
            max-width: 100% !important;
            height: auto !important;
        }
    `;
}

function applySettings() {
    rendition.themes.default({ "body": {} }); // ensure theme system is init'd
    rendition.themes.override("background", (THEMES[theme] || THEMES.light).bg);
    rendition.themes.override("color",      (THEMES[theme] || THEMES.light).color);
    // inject via addStylesheetRules to all loaded contents
    rendition.getContents().forEach(contents => {
        injectCssToContents(contents);
    });
}

function injectCssToContents(contents) {
    if (!contents) return;
    try {
        contents.addStylesheetCss(buildCss(), "reader-overrides");
    } catch(e) {
        console.warn("injectCss error", e);
    }
}

/* Hook into content load so settings apply to every new chapter */
rendition.hooks.content.register(contents => {
    injectCssToContents(contents);
});

applySettings();
updateFlowBtn();
updateThemeBtns();
updateSwipeBtn();
syncScrollFooterVisibility();

/* ── TITLE ── */
book.loaded.metadata.then(m => {
    document.getElementById("bookTitle").innerText = m.title || <?= json_encode($name) ?>;
}).catch(() => {});

/* ── TOC ── */
book.loaded.navigation.then(toc => {
    tocItems = toc;
    const frag = document.createDocumentFragment();
    toc.forEach(ch => {
        const d = document.createElement("div");
        d.textContent = ch.label;
        d.onclick = () => { rendition.display(ch.href).catch(() => {}); closeSidebar(); };
        frag.appendChild(d);
    });
    document.getElementById("toc").replaceChildren(frag);
}).catch(() => {});

/* ── RENDERED (swipe + tap) ── */
rendition.on("rendered", (section, view) => {
    try { view.window.addEventListener("keydown", handleKey); } catch {}
    const doc = view.document;
    if (!doc) return;
    let tx = 0, ty = 0, tStarted = false;
    doc.addEventListener("touchstart", e => {
        tx = e.touches[0].clientX; ty = e.touches[0].clientY; tStarted = true;
    }, { passive: true });
    doc.addEventListener("touchend", e => {
        if (!tStarted) return; tStarted = false;
        if (!swipeEnabled) return;
        const dx = e.changedTouches[0].clientX - tx;
        const dy = e.changedTouches[0].clientY - ty;
        if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.2)
            dx < 0 ? rendition.next() : rendition.prev();
    }, { passive: true });
    doc.addEventListener("click", e => {
        const w = doc.documentElement.clientWidth;
        if (e.clientX > w * 0.3 && e.clientX < w * 0.7) toggleToolbarVisibility();
    });
});

/* ── DISPLAY + RESTORE ── */
book.ready
    .then(() => {
        const last = localStorage.getItem(BOOK_KEY);
        const displayPromise = last
            ? safeDisplay(last)
            : rendition.display();
        book.locations.generate(2048)
            .then(() => {
                console.log("Locations ready");
                updateProgress();
            })
            .catch(err => {
                console.warn("Locations gagal:", err);
            });

        return displayPromise;
    })
    .then(() => {
        document.getElementById("loadingScreen").classList.add("hidden");
    })
    .catch(err => {
        document.getElementById("loadingScreen").classList.add("hidden");
        alert('Gagal membuka buku: ' + err.message);
    });

function safeDisplay(cfi) {
    return rendition.display(cfi).catch(() => {
        console.warn("Retry display...");
        return new Promise(res => setTimeout(res, 200))
            .then(() => rendition.display(cfi));
    });
}

document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "hidden") {
        const loc = rendition.currentLocation();
        if (loc?.start?.cfi) {
            localStorage.setItem(BOOK_KEY, loc.start.cfi);
        }
    }
});

/* ── RELOCATED ── */
rendition.on("relocated", loc => {
    localStorage.setItem(BOOK_KEY, loc.start.cfi);
    updateProgress();
    localStorage.setItem("pct-" + BOOK_URL, currentPct);
    updateBookmarkBtn();
    trackReadingTime();
    if (isScrollMode()) updateScrollFooterLabels();
});

/* ────────────────────────────────────────────
   PROGRESS BAR
──────────────────────────────────────────── */
function updateProgress() {
    try {
        if (!book.locations || !book.locations.percentageFromCfi) return;
        const loc = rendition.currentLocation();
        if (!loc?.start) return;
        const pct = Math.floor(book.locations.percentageFromCfi(loc.start.cfi) * 100);
        currentPct = pct;
        document.getElementById("progress").innerText = pct + "%";
        document.getElementById("progressFill").style.width = pct + "%";
    } catch {}
}

/* ────────────────────────────────────────────
   SCROLL-MODE FOOTER
──────────────────────────────────────────── */
function isScrollMode() {
    return flowMode === "scrolled-doc" || flowMode === "scrolled-continuous" || flowMode === "scrolled";
}

function syncScrollFooterVisibility() {
    document.getElementById("scrollFooter").classList.toggle("sf-visible", isScrollMode());
}

function labelForSection(sec) {
    if (!sec) return null;
    const href = (sec.href || "").split("#")[0];
    const hit = tocItems.find(t => {
        const th = (t.href || "").split("#")[0];
        return th === href || href.endsWith(th) || th.endsWith(href);
    });
    return hit ? hit.label.trim() : null;
}

function updateScrollFooterLabels() {
    try {
        const loc = rendition.currentLocation();
        if (!loc?.start) return;
        const spine   = book.spine;
        const section = spine.get(loc.start.cfi);
        if (!section) return;
        const prevSec = section.prev ? section.prev() : null;
        const nextSec = section.next ? section.next() : null;
        const curLabel = labelForSection(section);
        const spineItems = spine.spineItems ? spine.spineItems.filter(s => s.linear) : [];
        const total      = spineItems.length;
        const idx        = spineItems.findIndex(s => s.index === section.index) + 1;
        document.getElementById("sfChapterName").textContent  = curLabel || "";
        document.getElementById("sfChapterIndex").textContent = total ? `${idx} / ${total}` : "";
        const prevBtn   = document.getElementById("sfPrev");
        const prevLblEl = document.getElementById("sfPrevLabel");
        if (prevSec) {
            prevBtn.disabled    = false;
            prevLblEl.textContent = labelForSection(prevSec) || "Chapter sebelumnya";
        } else {
            prevBtn.disabled    = true;
            prevLblEl.textContent = "Awal buku";
        }
        const nextBtn   = document.getElementById("sfNext");
        const nextLblEl = document.getElementById("sfNextLabel");
        if (nextSec) {
            nextBtn.disabled    = false;
            nextLblEl.textContent = labelForSection(nextSec) || "Chapter berikutnya";
        } else {
            nextBtn.disabled    = true;
            nextLblEl.textContent = "Akhir buku";
        }
    } catch(e) {
        console.warn("scrollFooter update error:", e);
    }
}

/* ────────────────────────────────────────────
   INDIVIDUAL SETTING FUNCTIONS
   Each one updates state, saves to localStorage,
   rebuilds CSS, and re-injects into all iframes.
──────────────────────────────────────────── */

function reInjectAll() {
    rendition.getContents().forEach(contents => {
        injectCssToContents(contents);
    });
}

function biggerText()  { setFontSize(fontSize + 10); }
function smallerText() { setFontSize(fontSize - 10); }
function resetFont()   { setFontSize(100); }
function setFontSize(s) {
    fontSize = Math.max(70, Math.min(200, s));
    localStorage.setItem("reader-fontSize", fontSize);
    reInjectAll();
}

function changeFont(f) {
    fontFamily = f;
    localStorage.setItem("reader-fontFamily", f);
    reInjectAll();
}

function setLineSpacing(val) {
    lineSpacing = val;
    document.getElementById("lineSpacingVal").textContent = val.toFixed(1);
    localStorage.setItem("reader-lineSpacing", val);
    reInjectAll();
}

function setMargin(val) {
    margin = val;
    document.getElementById("marginVal").textContent = val + "%";
    localStorage.setItem("reader-margin", val);
    reInjectAll();
}

/* ── Theme ── */
function setTheme(t) {
    theme = t;
    localStorage.setItem("reader-theme", t);
    document.body.setAttribute("data-theme", t);
    updateThemeBtns();
    reInjectAll();
}
function updateThemeBtns() {
    document.querySelectorAll(".theme-btn").forEach(b =>
        b.classList.toggle("active", b.dataset.theme === theme));
}

/* ── Swipe ── */
function toggleSwipe() {
    swipeEnabled = !swipeEnabled;
    localStorage.setItem("reader-swipe", swipeEnabled ? "true" : "false");
    updateSwipeBtn();
    showToast(swipeEnabled ? "Swipe diaktifkan 👆" : "Swipe dinonaktifkan ✋");
}
function updateSwipeBtn() {
    const btn   = document.getElementById("swipeToggleBtn");
    const label = document.getElementById("swipeStatusLabel");
    if (swipeEnabled) {
        btn.textContent = "👆 Aktif";
        btn.classList.add("active-btn");
        label.textContent = "Geser kiri/kanan untuk berpindah halaman";
    } else {
        btn.textContent = "✋ Nonaktif";
        btn.classList.remove("active-btn");
        label.textContent = "Gunakan tombol ◀ ▶ untuk navigasi";
    }
}

/* ── Flow ── */
function toggleFlow() {
    flowMode = flowMode === "paginated" ? "scrolled-doc" : "paginated";
    localStorage.setItem("reader-flow", flowMode);
    const cfi = rendition.currentLocation()?.start?.cfi;
    rendition.flow(flowMode);
    if (cfi) rendition.display(cfi).catch(() => {});
    updateFlowBtn();
    syncScrollFooterVisibility();
    if (isScrollMode()) updateScrollFooterLabels();
}
function updateFlowBtn() {
    document.getElementById("flowBtn").textContent =
        flowMode === "paginated" ? "📄 Scroll" : "📖 Halaman";
}

/* ── Brightness ── */
function setBrightness(val) {
    document.getElementById("brightnessOverlay").style.opacity = val / 100;
}

/* ── Toolbar auto-hide ── */
let toolbarVisible = true, toolbarTimeout = null;
function toggleToolbarVisibility() {
    toolbarVisible = !toolbarVisible;
    document.getElementById("toolbar").classList.toggle("hidden", !toolbarVisible);
    document.getElementById("progressBar").classList.toggle("toolbar-hidden", !toolbarVisible);
}
function resetToolbarTimer() {
    clearTimeout(toolbarTimeout);
    if (!document.getElementById("settingsDrawer").classList.contains("open"))
        toolbarTimeout = setTimeout(() => { if (toolbarVisible) toggleToolbarVisibility(); }, 5000);
}
document.getElementById("viewer").addEventListener("touchstart", resetToolbarTimer, { passive: true });

function toggleSettings() {
    document.getElementById("settingsDrawer").classList.toggle("open");
    clearTimeout(toolbarTimeout);
}

function closeSettings() {
    document.getElementById("settingsDrawer").classList.remove("open");
}

/* ── Sidebar ── */
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

/* ── Bookmarks ── */
function getBookmarks() { try { return JSON.parse(localStorage.getItem("bm-" + BOOK_URL) || "[]"); } catch { return []; } }
function saveBookmarks(bms) { localStorage.setItem("bm-" + BOOK_URL, JSON.stringify(bms)); }
function toggleBookmarkCurrent() {
    const loc = rendition.currentLocation();
    if (!loc?.start?.cfi) return;
    const cfi = loc.start.cfi;
    let bms = getBookmarks();
    const idx = bms.findIndex(b => b.cfi === cfi);
    if (idx >= 0) { bms.splice(idx, 1); showToast("Bookmark dihapus"); }
    else { bms.push({ cfi, pct: currentPct, label: `Hal. ${currentPct}%`, time: Date.now() }); showToast("Bookmark ditambahkan 🔖"); }
    saveBookmarks(bms);
    updateBookmarkBtn();
}
function updateBookmarkBtn() {
    const loc = rendition.currentLocation();
    if (!loc?.start?.cfi) return;
    document.getElementById("bmBtn").classList.toggle("active-btn",
        getBookmarks().some(b => b.cfi === loc.start.cfi));
}
function openBookmarks() {
    closeSettings();
    const bms = getBookmarks();
    const list = document.getElementById("bookmarkList");
    const empty = document.getElementById("bookmarkEmpty");
    list.innerHTML = "";
    if (!bms.length) { empty.style.display = ""; return; }
    empty.style.display = "none";
    bms.sort((a,b) => a.pct - b.pct).forEach(bm => {
        const d = document.createElement("div"); d.className = "bm-item";
        d.innerHTML = `<span onclick="jumpToCfi('${bm.cfi}')">📖 ${bm.pct}% &mdash; <small>${new Date(bm.time).toLocaleDateString("id-ID")}</small></span>
                       <button onclick="deleteBookmark('${bm.cfi}')">🗑</button>`;
        list.appendChild(d);
    });
    document.getElementById("bookmarkOverlay").classList.add("open");
}
function closeBookmarks() { document.getElementById("bookmarkOverlay").classList.remove("open"); }
function deleteBookmark(cfi) { saveBookmarks(getBookmarks().filter(b => b.cfi !== cfi)); openBookmarks(); updateBookmarkBtn(); }
function jumpToCfi(cfi) { rendition.display(cfi).catch(() => {}); closeBookmarks(); closeSearch(); }

/* ── Search ── */
let searchDebounce = null;
function openSearch() { closeSettings(); document.getElementById("searchOverlay").classList.add("open"); setTimeout(() => document.getElementById("searchInput").focus(), 100); }
function closeSearch() { document.getElementById("searchOverlay").classList.remove("open"); }

async function doSearch() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(async () => {
        const q = document.getElementById("searchInput").value.trim().toLowerCase();
        const resultsEl = document.getElementById("searchResults");
        const countEl   = document.getElementById("searchCount");
        if (q.length < 2) {
            resultsEl.innerHTML = "";
            countEl.textContent = "";
            return;
        }
        resultsEl.innerHTML = '<div class="search-loading">Mencari...</div>';
        try {
            let results = [];
            await book.ready;
            await book.loaded.spine;
            const spineItems = book.spine.spineItems;
            for (let item of spineItems) {
                try {
                    const textRaw = await customRequest(item.href, 'text');
                    if (!textRaw) continue;
                    const doc = new DOMParser().parseFromString(textRaw, "text/html");
                    const text = doc.body?.textContent?.toLowerCase() || "";
                    if (!text) continue;
                    let idx = text.indexOf(q);
                    if (idx !== -1) {
                        const excerpt = text.substring(Math.max(0, idx - 50), idx + 100);
                        results.push({ cfi: item.cfiBase, excerpt: excerpt });
                    }
                } catch (e) {
                    console.warn("Search error:", item.href, e);
                }
            }
            countEl.textContent = results.length ? `${results.length} hasil` : "Tidak ditemukan";
            if (!results.length) {
                resultsEl.innerHTML = '<div class="search-loading">Tidak ditemukan.</div>';
                return;
            }
            resultsEl.innerHTML = "";
            results.forEach(r => {
                const d = document.createElement("div");
                d.className = "search-item";
                d.innerHTML = `<p>${r.excerpt.replace(new RegExp(q, "gi"), m => `<mark>${m}</mark>`)}</p>`;
                d.onclick = () => { rendition.display(r.cfi); closeSearch(); };
                resultsEl.appendChild(d);
            });
        } catch (e) {
            console.error(e);
            resultsEl.innerHTML = '<div class="search-loading">Gagal mencari.</div>';
        }
    }, 400);
}

/* ── Stats ── */
let sessionPages = 0, _statsBuffer = null, _statsFlushTimer = null;
function trackReadingTime() {
    sessionPages++;
    const today = new Date().toISOString().slice(0,10);
    const key   = "stats-" + BOOK_URL;
    if (!_statsBuffer) { try { _statsBuffer = JSON.parse(localStorage.getItem(key) || "{}"); } catch { _statsBuffer = {}; } }
    if (!_statsBuffer[today]) _statsBuffer[today] = { minutes: 0, pages: 0 };
    _statsBuffer[today].pages++;
    if (sessionPages % 3 === 0) _statsBuffer[today].minutes++;
    if (sessionPages % 5 === 0) { _flushStats(key); return; }
    clearTimeout(_statsFlushTimer);
    _statsFlushTimer = setTimeout(() => _flushStats(key), 30000);
}
function _flushStats(key) { if (_statsBuffer) try { localStorage.setItem(key, JSON.stringify(_statsBuffer)); } catch {} }
window.addEventListener("pagehide",     () => { _flushStats("stats-" + BOOK_URL); localStorage.setItem("pct-" + BOOK_URL, currentPct); });
window.addEventListener("beforeunload", () => { _flushStats("stats-" + BOOK_URL); localStorage.setItem("pct-" + BOOK_URL, currentPct); });

function openStats() {
    closeSettings();
    const key    = "stats-" + BOOK_URL;
    const stats  = _statsBuffer || (() => { try { return JSON.parse(localStorage.getItem(key) || "{}"); } catch { return {}; } })();
    const keys   = Object.keys(stats).sort().reverse();
    const totPg  = keys.reduce((s,k) => s + (stats[k].pages||0), 0);
    const totMin = keys.reduce((s,k) => s + (stats[k].minutes||0), 0);
    let html = `<div class="stat-card"><div class="stat-num">${currentPct}%</div><div class="stat-lbl">Progress</div></div>
                <div class="stat-card"><div class="stat-num">${totPg}</div><div class="stat-lbl">Halaman</div></div>
                <div class="stat-card"><div class="stat-num">${totMin>=60?Math.floor(totMin/60)+"j "+totMin%60+"m":totMin+"m"}</div><div class="stat-lbl">Waktu</div></div>
                <div class="stat-card"><div class="stat-num">${calcStreak(keys)} 🔥</div><div class="stat-lbl">Streak</div></div>
                <h4 style="margin:16px 0 8px;font-size:13px;color:var(--muted)">RIWAYAT 7 HARI</h4>`;
    const today   = new Date().toISOString().slice(0,10);
    const maxPg   = Math.max(1, ...keys.map(k => stats[k]?.pages||0));
    for (let i = 0; i < 7; i++) {
        const d  = new Date(); d.setDate(d.getDate()-i);
        const dk = d.toISOString().slice(0,10);
        const s  = stats[dk] || { pages:0 };
        const lbl = dk===today ? "Hari ini" : d.toLocaleDateString("id-ID",{weekday:"short",day:"numeric",month:"short"});
        html += `<div class="stat-day"><span class="stat-day-label">${lbl}</span><div class="stat-bar-wrap"><div class="stat-bar" style="width:${Math.min(100,s.pages/maxPg*100)}%"></div></div><span class="stat-day-val">${s.pages}h</span></div>`;
    }
    document.getElementById("statsContent").innerHTML = html;
    document.getElementById("statsOverlay").classList.add("open");
}
function closeStats() { document.getElementById("statsOverlay").classList.remove("open"); }
function calcStreak(desc) {
    if (!desc.length) return 0;
    let n = 0, check = new Date().toISOString().slice(0,10);
    for (const day of desc) {
        if (day !== check) break;
        n++;
        const d = new Date(check); d.setDate(d.getDate()-1); check = d.toISOString().slice(0,10);
    }
    return n;
}

/* ── Navigation ── */
function nextPage() { rendition.next().catch(() => {}); }
function prevPage() { rendition.prev().catch(() => {}); }
function handleKey(e) {
    if (e.key === "ArrowRight" || e.key === " ") { e.preventDefault(); nextPage(); }
    if (e.key === "ArrowLeft")  { e.preventDefault(); prevPage(); }
    if (e.key === "Escape")     { window.location.href = "index.php"; }
    if (e.key === "b" || e.key === "B") toggleBookmarkCurrent();
    if (e.key === "f" || e.key === "F") openSearch();
    if (e.key === "s" || e.key === "S") toggleSwipe();
}
document.addEventListener("keydown", handleKey);

/* ── Toast ── */
let toastTimer = null;
function showToast(msg) {
    const t = document.getElementById("toast");
    t.textContent = msg; t.classList.add("show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove("show"), 2200);
}
</script>
</body>
</html>
