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
    <h2>📚 EPUB</h2>
    <div class="search-wrap">
        <input type="text" id="searchBook" placeholder="Cari judul...">
        <button id="clearBtn">✕</button>
    </div>
    <div class="view-btns">
        <button onclick="setView('grid')" title="Grid">⊞</button>
        <button onclick="setView('list')" title="List">≡</button>
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
                <img class="cover loading" src="" alt="">
                <div class="book-info">
                    <div class="book-title"><?= htmlspecialchars($name) ?></div>
                    <div class="book-filename"><?= htmlspecialchars($name) ?>.epub</div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Pagination -->
<div id="pagination">
    <button id="prevBtn" onclick="changePage(-1)">◀ Prev</button>
    <span id="pageInfo"></span>
    <button id="nextBtn" onclick="changePage(1)">Next ▶</button>

    <div id="perPageWrap">
        <label for="perPageInput">Tampilkan:</label>
        <input type="number" id="perPageInput" min="10" max="100" step="10" value="10">
        <span>per halaman</span>
    </div>
</div>

<script>
const STORAGE_KEY_PER_PAGE = "epub-per-page"

let booksPerPage = parseInt(localStorage.getItem(STORAGE_KEY_PER_PAGE)) || 10
let currentPage  = 1
let filteredBooks = []

/* ── Sinkronkan input dengan nilai tersimpan ── */
const perPageInput = document.getElementById("perPageInput")
perPageInput.value = booksPerPage

perPageInput.addEventListener("change", () => {
    let val = parseInt(perPageInput.value) || 10
    // Bulatkan ke kelipatan 10 terdekat, min 10, max 100
    val = Math.round(val / 10) * 10
    val = Math.max(10, Math.min(100, val))
    perPageInput.value = val
    booksPerPage = val
    localStorage.setItem(STORAGE_KEY_PER_PAGE, val)
    const q = document.getElementById("searchBook").value.toLowerCase().trim()
    renderPage(1, q)
})

/* ── COVER CACHE (sessionStorage) ── */
const COVER_PFX = "cover-"

function getCached(url) {
    try { return sessionStorage.getItem(COVER_PFX + url) } catch { return null }
}

function setCached(url, dataUrl) {
    try {
        sessionStorage.setItem(COVER_PFX + url, dataUrl)
    } catch {
        try {
            Object.keys(sessionStorage)
                .filter(k => k.startsWith(COVER_PFX))
                .slice(0, 5)
                .forEach(k => sessionStorage.removeItem(k))
            sessionStorage.setItem(COVER_PFX + url, dataUrl)
        } catch {}
    }
}

/* ── COVER QUEUE (max 6 concurrent) ── */
let coverQueue = [], coverActive = 0

function enqueueCover(el) { coverQueue.push(el); pumpCovers() }

function pumpCovers() {
    while (coverActive < 6 && coverQueue.length) {
        coverActive++
        loadCover(coverQueue.shift()).finally(() => { coverActive--; pumpCovers() })
    }
}

async function loadCover(el) {
    const url = el.dataset.book
    const img = el.querySelector(".cover")

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

/* ── COVER LOAD TRACKER ── */
const loadedCovers = new Set()
function triggerCoverLoad(el) {
    if (!loadedCovers.has(el)) {
        loadedCovers.add(el)
        enqueueCover(el)
    }
}

/* ── BADGE "lanjut" ── */
document.querySelectorAll(".book").forEach(el => {
    if (localStorage.getItem("epub-" + el.dataset.book)) {
        const badge = document.createElement("span")
        badge.className = "last-read-badge"
        badge.textContent = "lanjut"
        el.appendChild(badge)
    }
})

/* ── HIGHLIGHT HELPER ── */
function highlightText(originalTitle, query) {
    if (!query) return originalTitle
    const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    const regex = new RegExp(`(${escaped})`, 'gi')
    return originalTitle.replace(regex, '<mark>$1</mark>')
}

/* ── PAGINATION ── */
function renderPage(page, query) {
    const allBooks = Array.from(document.querySelectorAll(".book"))

    filteredBooks = query
        ? allBooks.filter(el => el.dataset.title.includes(query))
        : allBooks

    const totalPages = Math.max(1, Math.ceil(filteredBooks.length / booksPerPage))
    currentPage = Math.min(Math.max(1, page), totalPages)

    const start = (currentPage - 1) * booksPerPage
    const end   = start + booksPerPage

    allBooks.forEach(el => {
        el.style.display = "none"
        el.querySelector(".book-title").innerHTML = query
            ? highlightText(el.dataset.originalTitle, query)
            : el.dataset.originalTitle
    })

    filteredBooks.slice(start, end).forEach(el => {
        el.style.display = ""
        triggerCoverLoad(el)
    })

    document.getElementById("pageInfo").textContent =
        filteredBooks.length === 0
            ? "Tidak ada hasil"
            : `Halaman ${currentPage} / ${totalPages}  (${filteredBooks.length} buku)`

    document.getElementById("prevBtn").disabled = currentPage <= 1
    document.getElementById("nextBtn").disabled = currentPage >= totalPages

    document.getElementById("pagination").style.display =
        filteredBooks.length === 0 ? "none" : "flex"

    window.scrollTo({ top: 0, behavior: "smooth" })
}

function changePage(dir) {
    const q = document.getElementById("searchBook").value.toLowerCase().trim()
    renderPage(currentPage + dir, q)
}

/* ── SEARCH ── */
let searchTimer = null
document.getElementById("searchBook").addEventListener("input", e => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        const q = e.target.value.toLowerCase().trim()
        renderPage(1, q)
    }, 150)
})

document.getElementById("clearBtn").addEventListener("click", () => {
    document.getElementById("searchBook").value = ""
    renderPage(1, "")
})

/* ── GRID / LIST VIEW ── */
function setView(mode) { document.getElementById("library").className = mode }

/* ── INIT ── */
renderPage(1, "")
</script>
</body>
</html>