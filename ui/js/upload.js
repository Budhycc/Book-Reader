const dropZone   = document.getElementById("dropZone")
    const fileInput  = document.getElementById("fileInput")
    const fileList   = document.getElementById("fileList")
    const actions    = document.getElementById("actions")
    const uploadBtn  = document.getElementById("uploadBtn")
    const clearBtn   = document.getElementById("clearBtn")
    const summary    = document.getElementById("summary")

    let pendingFiles = []   // { file, itemEl }

    /* ── Drag over styling ── */
    dropZone.addEventListener("dragover", e => { e.preventDefault(); dropZone.classList.add("over") })
    dropZone.addEventListener("dragleave", e => { if (!dropZone.contains(e.relatedTarget)) dropZone.classList.remove("over") })
    dropZone.addEventListener("drop", e => {
        e.preventDefault()
        dropZone.classList.remove("over")
        addFiles([...e.dataTransfer.files])
    })

    /* ── File picker ── */
    fileInput.addEventListener("change", () => {
        addFiles([...fileInput.files])
        fileInput.value = ""
    })

    /* ── Add files to queue ── */
    function addFiles(files) {
        const epubs = files.filter(f => f.name.toLowerCase().endsWith(".epub"))
        if (!epubs.length) {
            showSummary("error", "❌ Tidak ada file .epub yang dipilih.")
            return
        }
        summary.style.display = "none"

        epubs.forEach(file => {
            // Cegah duplikat nama
            if (pendingFiles.some(p => p.file.name === file.name)) return

            const itemEl = createFileItem(file)
            fileList.appendChild(itemEl)
            pendingFiles.push({ file, itemEl })
        })

        actions.style.display = pendingFiles.length ? "flex" : "none"
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + " B"
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB"
        return (bytes / (1024 * 1024)).toFixed(1) + " MB"
    }

    function createFileItem(file) {
        const el = document.createElement("div")
        el.className = "file-item"
        el.innerHTML = `
            <div class="file-icon">📖</div>
            <div class="file-meta">
                <div class="file-name">${escHtml(file.name)}</div>
                <div class="file-size">${formatSize(file.size)}</div>
                <div class="file-progress"><div class="file-progress-fill"></div></div>
            </div>
            <div class="file-status">Menunggu</div>
        `
        return el
    }

    function escHtml(str) {
        return str.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;")
    }

    /* ── Upload ── */
    uploadBtn.addEventListener("click", uploadAll)

    async function uploadAll() {
        if (!pendingFiles.length) return
        uploadBtn.disabled = true
        summary.style.display = "none"

        let successCount = 0
        let errorCount   = 0

        for (const { file, itemEl } of pendingFiles) {
            const statusEl = itemEl.querySelector(".file-status")
            const fillEl   = itemEl.querySelector(".file-progress-fill")

            itemEl.classList.add("uploading")
            statusEl.className = "file-status uploading"
            statusEl.textContent = "Mengupload…"
            fillEl.style.width = "40%"

            try {
                const formData = new FormData()
                formData.append("epub", file)

                const res = await fetch("../upload.php", { method: "POST", body: formData })
                const data = await res.json()

                if (data.ok) {
                    itemEl.classList.remove("uploading")
                    itemEl.classList.add("success")
                    statusEl.className = "file-status success"
                    statusEl.textContent = "✓ Berhasil"
                    fillEl.style.width = "100%"
                    successCount++
                } else {
                    throw new Error(data.error || "Gagal upload")
                }
            } catch (err) {
                itemEl.classList.remove("uploading")
                itemEl.classList.add("error")
                statusEl.className = "file-status error"
                statusEl.textContent = "✕ " + err.message
                fillEl.style.width = "100%"
                errorCount++
            }
        }

        pendingFiles = []
        uploadBtn.disabled = false
        actions.style.display = "none"

        // Summary
        if (errorCount === 0) {
            showSummary("success", `✅ ${successCount} buku berhasil diupload. <a href="index.html" style="color:var(--accent2);text-decoration:underline">Kembali ke library →</a>`)
        } else if (successCount === 0) {
            showSummary("error", `❌ Semua upload gagal (${errorCount} file).`)
        } else {
            showSummary("partial", `⚠ ${successCount} berhasil, ${errorCount} gagal. <a href="index.html" style="color:var(--accent2);text-decoration:underline">Kembali ke library →</a>`)
        }
    }

    /* ── Clear ── */
    clearBtn.addEventListener("click", () => {
        fileList.innerHTML = ""
        pendingFiles = []
        actions.style.display = "none"
        summary.style.display = "none"
    })

    function showSummary(type, html) {
        summary.className = type
        summary.innerHTML = html
        summary.style.display = "block"
    }
