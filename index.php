<?php
$books = glob("books/*.epub");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>EPUB Library</title>
    <link rel="stylesheet" href="index.css">
</head>
<body>

<header>
    <div class="header-row1">
        <h2>📚 EPUB</h2>
        <div class="search-wrap">
            <input type="text" id="searchBook" placeholder="Cari judul...">
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
    <div id="library" class="grid">
    <?php foreach ($books as $book):
            $name = basename($book, ".epub"); ?>
            <a class="book"
               href="reader.php?book=<?= urlencode($book) ?>"
               data-book="<?= htmlspecialchars($book) ?>"
               data-title="<?= strtolower(htmlspecialchars($name)) ?>"
               data-original-title="<?= htmlspecialchars($name) ?>">
                <img class="cover loading" src="" alt="" loading="lazy">
                <div class="book-info">
                    <div class="book-title"><?= htmlspecialchars($name) ?></div>
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

<script>
const STORAGE_KEY_PER_PAGE = "epub-per-page"
const STORAGE_KEY_SORT     = "epub-sort"
const STORAGE_KEY_VIEW     = "epub-view"

let booksPerPage = parseInt(localStorage.getItem(STORAGE_KEY_PER_PAGE)) || 10
let currentPage  = 1
let filteredBooks = []
let currentSort   = localStorage.getItem(STORAGE_KEY_SORT) || "az"
let currentFilter = "all"

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
    renderPage(1, document.getElementById("searchBook").value.toLowerCase().trim())
})

/* ── Sort select ── */
const sortSelect = document.getElementById("sortSelect")
sortSelect.value = currentSort
sortSelect.addEventListener("change", () => {
    currentSort = sortSelect.value
    localStorage.setItem(STORAGE_KEY_SORT, currentSort)
    renderPage(1, document.getElementById("searchBook").value.toLowerCase().trim())
})

/* ── Filter select ── */
document.getElementById("filterSelect").addEventListener("change", e => {
    currentFilter = e.target.value
    renderPage(1, document.getElementById("searchBook").value.toLowerCase().trim())
})

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
   COVER CACHE (sessionStorage) — [PATCH 4] LRU eviction
   Sebelumnya: evict 5 item pertama secara acak
   Sekarang: track waktu akses terakhir, hapus yang paling lama tidak dilihat
──────────────────────────────────────────── */
const COVER_PFX     = "cover-"
const COVER_LRU_PFX = "cover-lru-"
const COVER_MAX     = 20   // maksimum cover di-cache sekaligus

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
            // sessionStorage penuh — evict setengah cache terlama (LRU)
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
        } catch { /* gagal total, abaikan */ }
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
   COVER LAZY LOAD (IntersectionObserver)
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

function observeCover(el) {
    coverObserver.observe(el)
}

/* ────────────────────────────────────────────
   FUZZY SEARCH HELPER
──────────────────────────────────────────── */
function normalizeTitle(str) {
    return str.replace(/[-_]/g, ' ').replace(/\s+/g, ' ').trim()
}

function fuzzyMatch(text, query) {
    if (text.includes(query)) return true
    let ti = 0
    for (let qi = 0; qi < query.length; qi++) {
        const c = query[qi]
        while (ti < text.length && text[ti] !== c) ti++
        if (ti >= text.length) return false
        ti++
    }
    return true
}

/* ────────────────────────────────────────────
   HIGHLIGHT HELPER
──────────────────────────────────────────── */
function highlightText(title, query) {
    if (!query) return title
    const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    const regex = new RegExp(`(${escaped})`, 'gi')
    return title.replace(regex, '<mark>$1</mark>')
}

/* ────────────────────────────────────────────
   SORT HELPER
──────────────────────────────────────────── */
function sortBooks(books) {
    return [...books].sort((a, b) => {
        if (currentSort === "az") {
            return normalizeTitle(a.dataset.title).localeCompare(normalizeTitle(b.dataset.title), 'id')
        }
        if (currentSort === "za") {
            return normalizeTitle(b.dataset.title).localeCompare(normalizeTitle(a.dataset.title), 'id')
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
function renderPage(page, query) {
    const allBooks = Array.from(document.querySelectorAll(".book"))
    const normalizedQuery = normalizeTitle(query.toLowerCase())

    // 1. Filter by status
    let pool = allBooks.filter(el => {
        const pct = getProgress(el.dataset.book)
        if (currentFilter === "unread")  return pct === null
        if (currentFilter === "reading") return pct !== null && pct < 95
        if (currentFilter === "done")    return pct !== null && pct >= 95
        return true
    })

    // 2. Filter by search
    if (normalizedQuery) {
        pool = pool.filter(el =>
            fuzzyMatch(normalizeTitle(el.dataset.title), normalizedQuery)
        )
    }

    // 3. Sort
    filteredBooks = sortBooks(pool)

    const totalPages = Math.max(1, Math.ceil(filteredBooks.length / booksPerPage))
    currentPage = Math.min(Math.max(1, page), totalPages)

    const start = (currentPage - 1) * booksPerPage
    const end   = start + booksPerPage

    // Hide semua, reset title
    allBooks.forEach(el => {
        el.style.display = "none"
        const titleEl = el.querySelector(".book-title")
        if (normalizedQuery) {
            const displayTitle = normalizeTitle(el.dataset.originalTitle)
            titleEl.innerHTML = highlightText(displayTitle, normalizedQuery)
        } else {
            titleEl.textContent = el.dataset.originalTitle
        }
    })

    // Tampilkan slice yang sesuai, observe cover
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
    const q = document.getElementById("searchBook").value.toLowerCase().trim()
    renderPage(currentPage + dir, q)
}

function gotoPage() {
    const total = Math.max(1, Math.ceil(filteredBooks.length / booksPerPage))
    let val = parseInt(document.getElementById("gotoInput").value) || 1
    val = Math.max(1, Math.min(total, val))
    const q = document.getElementById("searchBook").value.toLowerCase().trim()
    renderPage(val, q)
}

document.getElementById("gotoInput").addEventListener("keydown", e => {
    if (e.key === "Enter") gotoPage()
})

/* ────────────────────────────────────────────
   SEARCH
──────────────────────────────────────────── */
let searchTimer = null
document.getElementById("searchBook").addEventListener("input", e => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        renderPage(1, e.target.value.toLowerCase().trim())
    }, 150)
})

document.getElementById("clearBtn").addEventListener("click", () => {
    document.getElementById("searchBook").value = ""
    renderPage(1, "")
})

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