<?php
$books = glob("books/*.epub");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>EPUB Library</title>
    <script src="js/jszip.min.js"></script>
    <script src="js/epub.min.js"></script>
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
/* ── COVER CACHE ── */
const COVER_PFX = "cover-"

function getCached(url) {
    try { return sessionStorage.getItem(COVER_PFX + url) } catch { return null }
}

function setCached(url, dataUrl) {
    try {
        sessionStorage.setItem(COVER_PFX + url, dataUrl)
    } catch {
        // Storage full — evict oldest 5 cover entries then retry
        try {
            Object.keys(sessionStorage)
                .filter(k => k.startsWith(COVER_PFX))
                .slice(0, 5)
                .forEach(k => sessionStorage.removeItem(k))
            sessionStorage.setItem(COVER_PFX + url, dataUrl)
        } catch {}
    }
}

/* Convert a blob URL to a base64 data URL.
   Blob URLs are only alive in the current tab's memory — they cannot
   be stored in sessionStorage and reused. A data URL is self-contained. */
function blobUrlToDataUrl(blobUrl) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest()
        xhr.open("GET", blobUrl)
        xhr.responseType = "blob"
        xhr.onload = () => {
            const reader = new FileReader()
            reader.onloadend = () => resolve(reader.result)
            reader.onerror  = reject
            reader.readAsDataURL(xhr.response)
        }
        xhr.onerror = reject
        xhr.send()
    })
}

/* ── COVER QUEUE (max 3 concurrent) ── */
let coverQueue = [], coverActive = 0

function enqueueCover(el) { coverQueue.push(el); pumpCovers() }

function pumpCovers() {
    while (coverActive < 3 && coverQueue.length) {
        coverActive++
        loadCover(coverQueue.shift()).finally(() => { coverActive--; pumpCovers() })
    }
}

async function loadCover(el) {
    const url = el.dataset.book
    const img = el.querySelector(".cover")

    // Serve from cache immediately (always a data URL, never a dead blob)
    const cached = getCached(url)
    if (cached) {
        img.src = cached
        img.classList.replace("loading", "loaded")
        return
    }

    try {
        const b = ePub(url)
        await b.ready
        const blobUrl = await b.coverUrl()
        try { b.destroy() } catch {}

        if (blobUrl) {
            // Convert blob → data URL before showing and caching
            const dataUrl = await blobUrlToDataUrl(blobUrl)
            img.src = dataUrl
            img.classList.replace("loading", "loaded")
            setCached(url, dataUrl)
        } else {
            img.src = "img/image.png"
            img.classList.replace("loading", "loaded")
        }
    } catch {
        img.src = "img/image.png"
        img.classList.replace("loading", "loaded")
    }
}

/* ── LAZY OBSERVER ── */
const obs = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (e.isIntersecting) { obs.unobserve(e.target); enqueueCover(e.target) }
    })
}, { rootMargin: "300px" })

document.querySelectorAll(".book").forEach(el => obs.observe(el))

/* ── LAST READ BADGE ── */
document.querySelectorAll(".book").forEach(el => {
    if (localStorage.getItem("epub-" + el.dataset.book)) {
        const badge = document.createElement("span")
        badge.className = "last-read-badge"
        badge.textContent = "lanjut"
        el.appendChild(badge)
    }
})

/* ── SEARCH ── */
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

/* ── VIEW ── */
function setView(mode) { document.getElementById("library").className = mode }
</script>
</body>
</html>