<?php
$books = glob("books/*.epub");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>EPUB Library</title>
    <link rel="icon" type="image/x-icon" href="/img/favicon.ico">
    <link rel="stylesheet" href="index.css">
</head>
<body>

<header>
    <div class="header-row1">
        <h2>📚 EPUB</h2>
        <div class="search-wrap">
            <input type="text" id="searchBook" placeholder="Cari judul atau penulis..." autocomplete="off" spellcheck="false">
            <button id="clearBtn" title="Hapus">✕</button>
        </div>
        <a href="upload.html" class="btn-upload" title="Upload Buku">
            📤 <span class="btn-upload-text">Upload</span>
        </a>
    </div>
    <div class="header-row2">
        <div class="header-row2-left">
            <span class="ctrl-label">Urutkan</span>
            <div class="ctrl-select">
                <select id="sortSelect">
                    <option value="az">A → Z</option>
                    <option value="za">Z → A</option>
                    <option value="recent">Terakhir Dibaca</option>
                    <option value="progress">Progress</option>
                </select>
            </div>
            <div class="ctrl-divider"></div>
            <span class="ctrl-label">Filter</span>
            <div class="ctrl-select">
                <select id="filterSelect">
                    <option value="all">Semua</option>
                    <option value="reading">Sedang Dibaca</option>
                    <option value="unread">Belum Dibaca</option>
                    <option value="done">Selesai (≥95%)</option>
                </select>
            </div>
        </div>
        <div class="view-btns">
            <button id="viewGrid" onclick="setView('grid')" title="Grid">⊞</button>
            <button id="viewList" onclick="setView('list')" title="List">≡</button>
        </div>
    </div>
</header>

<div class="container">
    <!-- Search dropdown -->
    <div id="searchDropdown"></div>

    <div id="library" class="grid">
    <?php foreach ($books as $book):
            $name = basename($book, ".epub"); ?>
            <a class="book"
               href="reader.php?book=<?= urlencode($book) ?>"
               data-book="<?= htmlspecialchars($book) ?>"
               data-filename="<?= strtolower(htmlspecialchars($name)) ?>"
               data-title="<?= strtolower(htmlspecialchars($name)) ?>"
               data-author=""
               data-original-title="<?= htmlspecialchars($name) ?>"
               data-original-author="">
                <img class="cover loading" src="" alt="" loading="lazy">
                <div class="book-info">
                    <div class="book-title"><?= htmlspecialchars($name) ?></div>
                    <div class="book-author"></div>
                    <div class="book-filename"><?= htmlspecialchars($name) ?>.epub</div>
                    <div class="book-progress-wrap">
                        <div class="book-progress-bar"></div>
                    </div>
                    <div class="book-progress-label"></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <div id="emptyMsg" style="display:none">
        <p>😔 Tidak ada buku ditemukan.</p>
    </div>
</div>

<!-- Pagination -->
<div id="pagination">
    <button id="prevBtn" class="page-btn" onclick="changePage(-1)">← Prev</button>
    <span id="pageInfo"></span>
    <button id="nextBtn" class="page-btn" onclick="changePage(1)">Next →</button>
    <div class="goto-wrap">
        <label for="gotoInput">Ke hal.</label>
        <input type="number" id="gotoInput" min="1" value="1">
        <button class="page-btn" onclick="gotoPage()">→</button>
    </div>
    <div id="perPageWrap">
        <label for="perPageInput">Tampilkan:</label>
        <input type="number" id="perPageInput" min="10" max="100" step="10" value="10">
        <span>per halaman</span>
    </div>
</div>

<style>
/* ── Book author line ── */
.grid .book-author {
    font-size: 10.5px;
    color: var(--muted);
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.list .book-author {
    font-size: 11.5px;
    color: var(--muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Search dropdown ── */
#searchDropdown {
    position: relative;
    z-index: 200;
    margin-bottom: 0;
}
#searchDropdown.open {
    margin-bottom: 6px;
}

.search-results-panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 12px 36px rgba(0,0,0,0.45);
}

.search-result-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid var(--border);
    transition: background 0.12s;
    cursor: pointer;
}
.search-result-item:last-child { border-bottom: none; }
.search-result-item:hover,
.search-result-item.highlighted {
    background: rgba(201,146,58,0.1);
}

.sri-cover {
    width: 36px;
    height: 52px;
    object-fit: cover;
    border-radius: 4px;
    flex-shrink: 0;
    background: rgba(255,255,255,0.05);
}

.sri-info {
    flex: 1;
    min-width: 0;
}
.sri-title {
    font-size: 13.5px;
    font-weight: 500;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sri-title mark,
.sri-author mark {
    background: rgba(201,146,58,0.28);
    color: var(--accent2);
    border-radius: 3px;
    padding: 0 2px;
    font-style: normal;
    font-weight: 600;
}
.sri-author {
    font-size: 11.5px;
    color: var(--muted);
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sri-arrow {
    font-size: 14px;
    color: var(--muted);
    flex-shrink: 0;
}

.search-results-footer {
    padding: 8px 14px;
    font-size: 11.5px;
    color: var(--muted);
    border-top: 1px solid var(--border);
    text-align: center;
}

.search-no-result {
    padding: 20px;
    text-align: center;
    color: var(--muted);
    font-size: 13.5px;
}
</style>

<script>
const STORAGE_KEY_PER_PAGE = "epub-per-page"
const STORAGE_KEY_SORT     = "epub-sort"
const STORAGE_KEY_VIEW     = "epub-view"

let booksPerPage = parseInt(localStorage.getItem(STORAGE_KEY_PER_PAGE)) || 10
let currentPage  = 1
let filteredBooks = []
let currentSort   = localStorage.getItem(STORAGE_KEY_SORT) || "az"
let currentFilter = "all"
let metaMap = {}         // { "books/x.epub": { title, author } }
let metaLoaded = false
let dropdownIdx = -1     // keyboard nav index in dropdown

/* ── Restore view ── */
const savedView = localStorage.getItem(STORAGE_KEY_VIEW) || "grid"
document.getElementById("library").className = savedView
document.getElementById("viewGrid").classList.toggle("active", savedView === "grid")
document.getElementById("viewList").classList.toggle("active", savedView === "list")

/* ── Per-page input ── */
const perPageInput = document.getElementById("perPageInput")
perPageInput.value = booksPerPage
perPageInput.addEventListener("change", () => {
    let val = parseInt(perPageInput.value) || 10
    val = Math.round(val / 10) * 10
    val = Math.max(10, Math.min(100, val))
    perPageInput.value = val
    booksPerPage = val
    localStorage.setItem(STORAGE_KEY_PER_PAGE, val)
    renderPage(1, document.getElementById("searchBook").value.trim())
})

/* ── Sort select ── */
const sortSelect = document.getElementById("sortSelect")
sortSelect.value = currentSort
sortSelect.addEventListener("change", () => {
    currentSort = sortSelect.value
    localStorage.setItem(STORAGE_KEY_SORT, currentSort)
    renderPage(1, document.getElementById("searchBook").value.trim())
})

/* ── Filter select ── */
document.getElementById("filterSelect").addEventListener("change", e => {
    currentFilter = e.target.value
    renderPage(1, document.getElementById("searchBook").value.trim())
})

/* ────────────────────────────────────────────
   LOAD METADATA dari server
──────────────────────────────────────────── */
async function loadMeta() {
    try {
        const res = await fetch('get-meta.php')
        if (!res.ok) return
        const data = await res.json()
        data.forEach(m => {
            metaMap[m.file] = { title: m.title || '', author: m.author || '' }
        })
        metaLoaded = true
        // Update semua card dengan judul + penulis asli
        document.querySelectorAll(".book").forEach(el => {
            const m = metaMap[el.dataset.book]
            if (!m) return
            const titleLower  = m.title.toLowerCase()
            const authorLower = m.author.toLowerCase()
            el.dataset.title  = titleLower
            el.dataset.author = authorLower
            el.dataset.originalTitle  = m.title
            el.dataset.originalAuthor = m.author
            el.querySelector(".book-title").textContent  = m.title
            const authorEl = el.querySelector(".book-author")
            if (authorEl) authorEl.textContent = m.author
        })
        // Re-render jika ada query aktif
        const q = document.getElementById("searchBook").value.trim()
        if (q) renderPage(currentPage, q)
    } catch(e) {
        console.warn("Gagal memuat metadata:", e)
    }
}
loadMeta()

/* ────────────────────────────────────────────
   PROGRESS dari localStorage
──────────────────────────────────────────── */
function getProgress(bookUrl) {
    const key = "epub-" + bookUrl
    if (!localStorage.getItem(key)) return null
    const pct = localStorage.getItem("pct-" + bookUrl)
    return pct !== null ? parseInt(pct) : 1
}

function getLastRead(bookUrl) {
    const statsKey = "stats-" + bookUrl
    try {
        const stats = JSON.parse(localStorage.getItem(statsKey) || "{}")
        const keys = Object.keys(stats).sort()
        return keys.length ? keys[keys.length - 1] : null
    } catch { return null }
}

/* ── Init progress bar di semua card ── */
document.querySelectorAll(".book").forEach(el => {
    const url   = el.dataset.book
    const pct   = getProgress(url)
    const bar   = el.querySelector(".book-progress-bar")
    const label = el.querySelector(".book-progress-label")
    if (pct === null) {
        bar.style.width = "0%"
        label.textContent = ""
    } else if (pct >= 95) {
        bar.style.width = "100%"
        bar.classList.add("done")
        label.textContent = "✓ Selesai"
        label.classList.add("done")
    } else {
        bar.style.width = pct + "%"
        label.textContent = pct + "%"
    }
})

/* ── Badge "lanjut" ── */
document.querySelectorAll(".book").forEach(el => {
    if (getProgress(el.dataset.book) !== null) {
        const badge = document.createElement("span")
        badge.className = "last-read-badge"
        const pct = getProgress(el.dataset.book)
        badge.textContent = pct >= 95 ? "selesai" : "lanjut"
        if (pct >= 95) badge.classList.add("done")
        el.appendChild(badge)
    }
})

/* ────────────────────────────────────────────
   COVER CACHE (sessionStorage) — LRU eviction
──────────────────────────────────────────── */
const COVER_PFX     = "cover-"
const COVER_LRU_PFX = "cover-lru-"
const COVER_MAX     = 20

function getCached(url) {
    try {
        const val = sessionStorage.getItem(COVER_PFX + url)
        if (val) sessionStorage.setItem(COVER_LRU_PFX + url, Date.now())
        return val
    } catch { return null }
}

function setCached(url, dataUrl) {
    try {
        sessionStorage.setItem(COVER_PFX + url, dataUrl)
        sessionStorage.setItem(COVER_LRU_PFX + url, Date.now())
        _trimCoverCache()
    } catch {
        try {
            const lruEntries = Object.keys(sessionStorage)
                .filter(k => k.startsWith(COVER_LRU_PFX))
                .map(k => ({ key: k.slice(COVER_LRU_PFX.length), ts: parseInt(sessionStorage.getItem(k)) || 0 }))
                .sort((a, b) => a.ts - b.ts)
            lruEntries.slice(0, Math.ceil(lruEntries.length / 2)).forEach(({ key }) => {
                sessionStorage.removeItem(COVER_PFX + key)
                sessionStorage.removeItem(COVER_LRU_PFX + key)
            })
            sessionStorage.setItem(COVER_PFX + url, dataUrl)
            sessionStorage.setItem(COVER_LRU_PFX + url, Date.now())
        } catch {}
    }
}

function _trimCoverCache() {
    try {
        const entries = Object.keys(sessionStorage)
            .filter(k => k.startsWith(COVER_LRU_PFX))
            .map(k => ({ key: k.slice(COVER_LRU_PFX.length), ts: parseInt(sessionStorage.getItem(k)) || 0 }))
            .sort((a, b) => a.ts - b.ts)
        if (entries.length > COVER_MAX) {
            entries.slice(0, entries.length - COVER_MAX).forEach(({ key }) => {
                sessionStorage.removeItem(COVER_PFX + key)
                sessionStorage.removeItem(COVER_LRU_PFX + key)
            })
        }
    } catch {}
}

/* ────────────────────────────────────────────
   COVER LAZY LOAD
──────────────────────────────────────────── */
const coverObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            loadCover(entry.target)
            coverObserver.unobserve(entry.target)
        }
    })
}, { rootMargin: "200px" })

async function loadCover(el) {
    const url = el.dataset.book
    const img = el.querySelector(".cover")
    if (img.src && img.src !== window.location.href) return
    const cached = getCached(url)
    if (cached) {
        img.src = cached
        img.classList.replace("loading", "loaded")
        return
    }
    const coverUrl = `get-cover.php?book=${encodeURIComponent(url)}`
    try {
        const response = await fetch(coverUrl)
        if (!response.ok) throw new Error(`HTTP ${response.status}`)
        const blob = await response.blob()
        const dataUrl = await new Promise((resolve, reject) => {
            const reader = new FileReader()
            reader.onloadend = () => resolve(reader.result)
            reader.onerror = reject
            reader.readAsDataURL(blob)
        })
        img.src = dataUrl
        img.classList.replace("loading", "loaded")
        setCached(url, dataUrl)
    } catch (err) {
        console.warn('Gagal memuat cover', url, err)
        img.src = "img/image.png"
        img.classList.replace("loading", "loaded")
    }
}

function observeCover(el) { coverObserver.observe(el) }

/* ────────────────────────────────────────────
   SEARCH HELPERS
──────────────────────────────────────────── */
function normalizeStr(str) {
    return str
        .toLowerCase()
        .replace(/[-_]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
}

// Tokenized AND search: semua kata query harus ada di title atau author
function tokenMatch(text, query) {
    const tokens = query.split(/\s+/).filter(Boolean)
    return tokens.every(tok => text.includes(tok))
}

// Substring match (untuk highlight)
function highlightText(original, query) {
    if (!query) return escHtml(original)
    const tokens = query.split(/\s+/).filter(Boolean)
    let result = escHtml(original)
    tokens.forEach(tok => {
        if (!tok) return
        const escaped = tok.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
        result = result.replace(new RegExp(escaped, 'gi'), '<mark>$&</mark>')
    })
    return result
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')
}

/* ────────────────────────────────────────────
   SEARCH DROPDOWN (hasil instan di atas grid)
──────────────────────────────────────────── */
function buildSearchDropdown(query) {
    const panel = document.getElementById("searchDropdown")
    if (!query) { panel.innerHTML = ""; panel.classList.remove("open"); dropdownIdx = -1; return }

    const nq = normalizeStr(query)
    const allBooks = Array.from(document.querySelectorAll(".book"))

    const matches = allBooks
        .filter(el => {
            const combined = normalizeStr(el.dataset.title) + ' ' + normalizeStr(el.dataset.author)
            return tokenMatch(combined, nq)
        })
        .slice(0, 8)   // max 8 hasil di dropdown

    if (!matches.length) {
        panel.innerHTML = `<div class="search-results-panel"><div class="search-no-result">Tidak ditemukan untuk "<strong>${escHtml(query)}</strong>"</div></div>`
        panel.classList.add("open")
        dropdownIdx = -1
        return
    }

    const items = matches.map((el, i) => {
        const bookUrl = el.dataset.book
        const title   = el.dataset.originalTitle  || el.dataset.book
        const author  = el.dataset.originalAuthor || ''
        const coverSrc = getCached(bookUrl) || ''
        const href = `reader.php?book=${encodeURIComponent(bookUrl)}`

        return `
        <a class="search-result-item" href="${href}" data-idx="${i}" tabindex="-1">
            <img class="sri-cover" src="${escHtml(coverSrc)}" alt="" onerror="this.style.visibility='hidden'">
            <div class="sri-info">
                <div class="sri-title">${highlightText(title, nq)}</div>
                ${author ? `<div class="sri-author">${highlightText(author, nq)}</div>` : ''}
            </div>
            <span class="sri-arrow">→</span>
        </a>`
    }).join('')

    const footer = matches.length < allBooks.filter(el => {
        const combined = normalizeStr(el.dataset.title) + ' ' + normalizeStr(el.dataset.author)
        return tokenMatch(combined, nq)
    }).length
        ? `<div class="search-results-footer">Menampilkan ${matches.length} teratas — scroll grid untuk lebih</div>`
        : ''

    panel.innerHTML = `<div class="search-results-panel">${items}${footer}</div>`
    panel.classList.add("open")
    dropdownIdx = -1

    // Async: isi cover yang belum ada
    matches.forEach((el, i) => {
        const bookUrl = el.dataset.book
        if (!getCached(bookUrl)) {
            fetch(`get-cover.php?book=${encodeURIComponent(bookUrl)}`)
                .then(r => r.blob())
                .then(blob => new Promise((res, rej) => {
                    const fr = new FileReader(); fr.onloadend = () => res(fr.result); fr.onerror = rej; fr.readAsDataURL(blob)
                }))
                .then(dataUrl => {
                    setCached(bookUrl, dataUrl)
                    const imgEl = panel.querySelector(`.search-result-item[data-idx="${i}"] .sri-cover`)
                    if (imgEl) { imgEl.src = dataUrl; imgEl.style.visibility = '' }
                })
                .catch(() => {})
        }
    })
}

/* ────────────────────────────────────────────
   SORT HELPER
──────────────────────────────────────────── */
function sortBooks(books) {
    return [...books].sort((a, b) => {
        if (currentSort === "az") {
            const ta = normalizeStr(a.dataset.title)
            const tb = normalizeStr(b.dataset.title)
            return ta.localeCompare(tb, 'id')
        }
        if (currentSort === "za") {
            const ta = normalizeStr(a.dataset.title)
            const tb = normalizeStr(b.dataset.title)
            return tb.localeCompare(ta, 'id')
        }
        if (currentSort === "recent") {
            const da = getLastRead(a.dataset.book) || "0"
            const db = getLastRead(b.dataset.book) || "0"
            return db.localeCompare(da)
        }
        if (currentSort === "progress") {
            const pa = getProgress(a.dataset.book) ?? -1
            const pb = getProgress(b.dataset.book) ?? -1
            return pb - pa
        }
        return 0
    })
}

/* ────────────────────────────────────────────
   PAGINATION / RENDER
──────────────────────────────────────────── */
function renderPage(page, rawQuery) {
    const query   = rawQuery.trim()
    const nq      = normalizeStr(query)
    const allBooks = Array.from(document.querySelectorAll(".book"))

    // 1. Filter by status
    let pool = allBooks.filter(el => {
        const pct = getProgress(el.dataset.book)
        if (currentFilter === "unread")  return pct === null
        if (currentFilter === "reading") return pct !== null && pct < 95
        if (currentFilter === "done")    return pct !== null && pct >= 95
        return true
    })

    // 2. Filter by search (title + author, tokenized AND)
    if (nq) {
        pool = pool.filter(el => {
            const combined = normalizeStr(el.dataset.title) + ' ' + normalizeStr(el.dataset.author)
            return tokenMatch(combined, nq)
        })
    }

    // 3. Sort
    filteredBooks = sortBooks(pool)

    const totalPages = Math.max(1, Math.ceil(filteredBooks.length / booksPerPage))
    currentPage = Math.min(Math.max(1, page), totalPages)

    const start = (currentPage - 1) * booksPerPage
    const end   = start + booksPerPage

    // Reset semua card
    allBooks.forEach(el => {
        el.style.display = "none"
        const titleEl  = el.querySelector(".book-title")
        const authorEl = el.querySelector(".book-author")
        if (nq) {
            titleEl.innerHTML  = highlightText(el.dataset.originalTitle  || '', nq)
            if (authorEl) authorEl.innerHTML = highlightText(el.dataset.originalAuthor || '', nq)
        } else {
            titleEl.textContent  = el.dataset.originalTitle  || ''
            if (authorEl) authorEl.textContent = el.dataset.originalAuthor || ''
        }
    })

    // Tampilkan slice dan observe cover
    filteredBooks.slice(start, end).forEach(el => {
        el.style.display = ""
        observeCover(el)
    })

    const emptyMsg = document.getElementById("emptyMsg")
    emptyMsg.style.display = filteredBooks.length === 0 ? "" : "none"

    document.getElementById("pageInfo").textContent =
        filteredBooks.length === 0
            ? "Tidak ada hasil"
            : `Halaman ${currentPage} / ${totalPages}  (${filteredBooks.length} buku)`

    document.getElementById("prevBtn").disabled = currentPage <= 1
    document.getElementById("nextBtn").disabled = currentPage >= totalPages
    document.getElementById("gotoInput").value  = currentPage
    document.getElementById("gotoInput").max    = totalPages

    document.getElementById("pagination").style.display =
        filteredBooks.length === 0 ? "none" : "flex"

    window.scrollTo({ top: 0, behavior: "smooth" })
}

function changePage(dir) {
    const q = document.getElementById("searchBook").value
    renderPage(currentPage + dir, q)
}

function gotoPage() {
    const total = Math.max(1, Math.ceil(filteredBooks.length / booksPerPage))
    let val = parseInt(document.getElementById("gotoInput").value) || 1
    val = Math.max(1, Math.min(total, val))
    const q = document.getElementById("searchBook").value
    renderPage(val, q)
}

document.getElementById("gotoInput").addEventListener("keydown", e => {
    if (e.key === "Enter") gotoPage()
})

/* ────────────────────────────────────────────
   SEARCH INPUT HANDLING
──────────────────────────────────────────── */
let searchTimer = null
const searchInput = document.getElementById("searchBook")

searchInput.addEventListener("input", e => {
    clearTimeout(searchTimer)
    const q = e.target.value
    // Dropdown muncul segera (tanpa debounce) untuk responsivitas
    buildSearchDropdown(q)
    // Grid filter dengan debounce kecil
    searchTimer = setTimeout(() => {
        renderPage(1, q)
    }, 120)
})

searchInput.addEventListener("keydown", e => {
    const panel  = document.getElementById("searchDropdown")
    const items  = panel.querySelectorAll(".search-result-item")
    if (!items.length) return

    if (e.key === "ArrowDown") {
        e.preventDefault()
        dropdownIdx = Math.min(dropdownIdx + 1, items.length - 1)
        items.forEach((it, i) => it.classList.toggle("highlighted", i === dropdownIdx))
        items[dropdownIdx]?.scrollIntoView({ block: "nearest" })
    } else if (e.key === "ArrowUp") {
        e.preventDefault()
        dropdownIdx = Math.max(dropdownIdx - 1, 0)
        items.forEach((it, i) => it.classList.toggle("highlighted", i === dropdownIdx))
        items[dropdownIdx]?.scrollIntoView({ block: "nearest" })
    } else if (e.key === "Enter" && dropdownIdx >= 0) {
        e.preventDefault()
        items[dropdownIdx]?.click()
    } else if (e.key === "Escape") {
        closeDropdown()
        searchInput.blur()
    }
})

searchInput.addEventListener("focus", () => {
    if (searchInput.value.trim()) buildSearchDropdown(searchInput.value)
})

document.getElementById("clearBtn").addEventListener("click", () => {
    searchInput.value = ""
    closeDropdown()
    renderPage(1, "")
    searchInput.focus()
})

// Tutup dropdown klik di luar
document.addEventListener("click", e => {
    if (!e.target.closest("#searchDropdown") && !e.target.closest(".search-wrap")) {
        closeDropdown()
    }
})

function closeDropdown() {
    const panel = document.getElementById("searchDropdown")
    panel.innerHTML = ""
    panel.classList.remove("open")
    dropdownIdx = -1
}

/* ────────────────────────────────────────────
   GRID / LIST VIEW
──────────────────────────────────────────── */
function setView(mode) {
    document.getElementById("library").className = mode
    localStorage.setItem(STORAGE_KEY_VIEW, mode)
    document.getElementById("viewGrid").classList.toggle("active", mode === "grid")
    document.getElementById("viewList").classList.toggle("active", mode === "list")
}

/* ── INIT ── */
renderPage(1, "")
</script>
</body>
</html>