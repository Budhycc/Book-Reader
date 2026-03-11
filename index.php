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
    <!-- Kita tidak perlu js/jszip.min.js dan js/epub.min.js lagi untuk cover! -->
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
               data-title="<?= strtolower(htmlspecialchars($name)) ?>">
                <img class="cover loading" src="" alt="">
                <div class="book-info">
                    <div class="book-title"><?= htmlspecialchars($name) ?></div>
                    <div class="book-filename"><?= htmlspecialchars($name) ?>.epub</div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<script>
/* ── COVER CACHE (sessionStorage) ── */
const COVER_PFX = "cover-"

function getCached(url) {
    try { return sessionStorage.getItem(COVER_PFX + url) } catch { return null }
}

function setCached(url, dataUrl) {
    try {
        sessionStorage.setItem(COVER_PFX + url, dataUrl)
    } catch {
        // Jika penyimpanan penuh, hapus 5 entri tertua
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

    // Gunakan cache jika ada
    const cached = getCached(url)
    if (cached) {
        img.src = cached
        img.classList.replace("loading", "loaded")
        return
    }

    // Fetch cover dari endpoint server
    const coverUrl = `get-cover.php?book=${encodeURIComponent(url)}`
    try {
        const response = await fetch(coverUrl)
        if (!response.ok) throw new Error(`HTTP ${response.status}`)
        const blob = await response.blob()
        // Konversi blob ke data URL agar bisa dicache di sessionStorage
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

/* ── LAZY LOADING (Intersection Observer) ── */
const obs = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            obs.unobserve(e.target)
            enqueueCover(e.target)
        }
    })
}, { rootMargin: "300px" })

document.querySelectorAll(".book").forEach(el => obs.observe(el))

/* ── BADGE "lanjut" untuk buku yang sudah pernah dibaca ── */
document.querySelectorAll(".book").forEach(el => {
    if (localStorage.getItem("epub-" + el.dataset.book)) {
        const badge = document.createElement("span")
        badge.className = "last-read-badge"
        badge.textContent = "lanjut"
        el.appendChild(badge)
    }
})

/* ── SEARCH (filter berdasarkan judul) ── */
let searchTimer = null
document.getElementById("searchBook").addEventListener("input", e => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        const q = e.target.value.toLowerCase()
        document.querySelectorAll(".book").forEach(el => {
            el.style.display = el.dataset.title.includes(q) ? "" : "none"
        })
    }, 150)
})
document.getElementById("clearBtn").addEventListener("click", () => {
    document.getElementById("searchBook").value = ""
    document.querySelectorAll(".book").forEach(el => el.style.display = "")
})

/* ── GRID / LIST VIEW ── */
function setView(mode) { document.getElementById("library").className = mode }
</script>
</body>
</html>