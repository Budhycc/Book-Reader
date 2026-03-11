# 📚 EPUB Library

Aplikasi web untuk membaca koleksi buku EPUB secara lokal. Dibangun dengan PHP murni di sisi server dan epub.js di sisi klien — tanpa framework, tanpa database, tanpa instalasi rumit.

---

## Fitur Utama

### Library
- Tampilan **grid** dan **list** yang bisa diubah
- Cover buku dimuat otomatis dari file EPUB
- **Lazy loading** cover dengan efek shimmer saat memuat
- **Pencarian judul** secara real-time
- Badge **"lanjut"** pada buku yang sudah pernah dibuka

### Reader
- Navigasi halaman dengan tombol, **swipe**, atau keyboard
- **Progress bar visual** dengan estimasi waktu baca tersisa
- **Auto-hide toolbar** — tersembunyi saat membaca, muncul saat tap tengah layar
- **Tiga tema**: Terang ☀, Sepia 📜, Gelap 🌙
- Pengaturan ukuran font (70%–200%) dan pilihan jenis huruf
- **Slider redup** layar tanpa mengubah kecerahan sistem
- **Toggle mode scroll / halaman**
- **Pencarian teks** di seluruh isi buku
- **Bookmark** halaman dengan daftar riwayat
- **Statistik membaca**: total halaman, waktu, streak harian, grafik 7 hari
- Posisi baca terakhir tersimpan otomatis
- Unduh file EPUB langsung dari reader

---

## Struktur File

```
/
├── index.php           # Halaman library (daftar buku)
├── index.css           # Gaya halaman library
├── reader.php          # Halaman reader EPUB
├── reader.css          # Gaya halaman reader
├── get-cover.php       # Endpoint ekstrak cover dari EPUB
├── get-epub-part.php   # Endpoint streaming konten EPUB ke browser
├── books/              # Folder tempat menyimpan file .epub
│   └── *.epub
└── js/
    ├── epub.min.js     # epub.js library
    └── jszip.min.js    # JSZip (dependensi epub.js)
```

---

## Persyaratan Server

| Kebutuhan | Keterangan |
|-----------|------------|
| PHP | ≥ 7.4 |
| Ekstensi PHP | `zip`, `dom`, `fileinfo` |
| Web server | Apache / Nginx / PHP built-in server |
| Browser | Chrome, Firefox, Safari, Edge (modern) |

---

## Instalasi

### 1. Clone atau unduh project

```bash
git clone https://github.com/username/epub-library.git
cd epub-library
```

### 2. Tambahkan buku

Salin file `.epub` ke dalam folder `books/`:

```bash
cp ~/Downloads/buku-saya.epub books/
```

### 3. Jalankan server

**Menggunakan PHP built-in server (development):**

```bash
php -S localhost:8080
```

Buka `http://localhost:8080` di browser.

**Menggunakan Apache / Nginx:** Arahkan document root ke folder project, pastikan PHP aktif.

---

## Cara Penggunaan

### Library
- Buka `index.php` untuk melihat semua buku
- Ketik di kotak pencarian untuk menyaring judul
- Klik ⊞ untuk tampilan grid, ≡ untuk tampilan list
- Klik cover atau judul buku untuk mulai membaca

### Reader — Navigasi

| Aksi | Cara |
|------|------|
| Halaman berikutnya | Tombol ▶, swipe kiri, atau `→` / `Space` |
| Halaman sebelumnya | Tombol ◀, swipe kanan, atau `←` |
| Kembali ke library | Tombol 🏠 atau tekan `Esc` |
| Tampilkan/sembunyikan toolbar | Tap bagian tengah layar |

### Reader — Fitur

| Fitur | Cara Akses |
|-------|-----------|
| Daftar chapter (TOC) | Tombol ☰ di kiri toolbar |
| Bookmark halaman ini | Tombol 🔖 atau tekan `B` |
| Cari teks | Tombol 🔍 atau tekan `F` |
| Pengaturan tampilan | Tombol ⚙ di kanan toolbar |
| Statistik baca | Pengaturan ⚙ → Statistik |
| Unduh buku | Pengaturan ⚙ → ⬇ Unduh |

### Reader — Pengaturan

Buka panel pengaturan (⚙) untuk mengakses:

- **Ukuran font**: A− untuk kecilkan, A+ untuk besarkan, ↺ untuk reset
- **Jenis huruf**: Serif, Sans, Georgia, Times New Roman
- **Tema**: ☀ Terang / 📜 Sepia / 🌙 Gelap
- **Mode tampilan**: Toggle antara mode Halaman (paginasi) dan Scroll
- **Slider redup**: Geser untuk meredupkan layar tanpa mengubah setting HP

---

## Penyimpanan Data

Semua data pengguna disimpan di **localStorage** browser — tidak ada data yang dikirim ke server.

| Data | Key localStorage |
|------|-----------------|
| Posisi baca terakhir | `epub-books/nama.epub` |
| Ukuran font | `reader-fontSize` |
| Jenis huruf | `reader-fontFamily` |
| Tema | `reader-theme` |
| Mode tampilan | `reader-flow` |
| Bookmark | `bm-books/nama.epub` |
| Statistik baca | `stats-books/nama.epub` |

Cover buku di-cache sementara di **sessionStorage** untuk mempercepat tampilan.

---

## API Endpoint Internal

### `get-cover.php`

Mengekstrak gambar cover dari file EPUB.

```
GET get-cover.php?book=books/nama.epub
```

Respons: gambar (`image/jpeg`, `image/png`, dll.) dengan header cache 1 tahun.

### `get-epub-part.php`

Menyajikan konten internal EPUB (HTML, CSS, gambar, font) ke browser.

```
GET get-epub-part.php?book=books/nama.epub&file=path/di/dalam/epub.xhtml
```

Respons: konten file dengan MIME type yang sesuai dan header ETag untuk caching.

Kedua endpoint dilengkapi validasi keamanan — hanya mengizinkan akses ke file `.epub` di dalam folder `books/` dan mencegah path traversal.

---

## Keamanan

- Path buku divalidasi dengan regex ketat: hanya `books/*.epub`
- Endpoint `get-epub-part.php` memblokir `..` dan path absolut
- Tidak ada eksekusi kode dari konten EPUB (iframe sandbox aktif)
- Tidak ada autentikasi — cocok untuk penggunaan lokal/intranet

---

## Menambah Font Baru

Buka `reader.php` dan tambahkan opsi baru di elemen `<select id="fontSelect">`:

```html
<option value="Literata">Literata</option>
```

Pastikan font tersedia via Google Fonts atau CSS `@font-face` di `reader.css`.

---

## Dependensi

| Library | Versi | Lisensi |
|---------|-------|---------|
| [epub.js](https://github.com/futurepress/epub.js) | 0.3.x | FreeBSD |
| [JSZip](https://stuk.github.io/jszip/) | 3.10.x | MIT |
| [Playfair Display](https://fonts.google.com/specimen/Playfair+Display) | — | OFL |
| [DM Sans](https://fonts.google.com/specimen/DM+Sans) | — | OFL |

Font dimuat dari Google Fonts CDN. Untuk penggunaan offline, unduh dan host secara lokal.

---

## Troubleshooting

**Cover tidak muncul**
Pastikan ekstensi PHP `zip` aktif. Cek dengan `php -m | grep zip`.

**Buku gagal dibuka**
Verifikasi file EPUB valid dengan tool seperti EPUBCheck. File EPUB yang rusak atau terenkripsi (DRM) tidak didukung.

**Layar font berganti sendiri**
epub.js menggunakan iframe; beberapa EPUB memiliki CSS internal yang meng-override pengaturan. Ini perilaku normal.

**Progress tidak muncul**
Proses generate lokasi buku (`book.locations.generate`) membutuhkan waktu. Progress muncul setelah selesai, biasanya 1–5 detik setelah buku terbuka.

---

## Lisensi

MIT License — bebas digunakan, dimodifikasi, dan didistribusikan.