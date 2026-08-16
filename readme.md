# 📚 EPUB Library

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)

Aplikasi web *self-hosted* yang indah untuk membaca koleksi buku EPUB Anda secara lokal. Dibangun dengan murni PHP pada backend dan `epub.js` pada frontend — menampilkan estetika modern, tanpa framework berat, tanpa database, dan tanpa setup yang rumit.

---

## Daftar Isi

- [Fitur Utama](#fitur-utama)
- [Struktur Direktori](#struktur-direktori)
- [Persyaratan Server](#persyaratan-server)
- [Instalasi](#instalasi)
- [Panduan Penggunaan](#panduan-penggunaan)
- [Penyimpanan Data](#penyimpanan-data)
- [Caching Sampul](#caching-sampul)
- [Internal API Endpoints](#internal-api-endpoints)
- [Keamanan](#keamanan)
- [Menambahkan Font Baru](#menambahkan-font-baru)
- [Dependensi](#dependensi)
- [Penyelesaian Masalah (Troubleshooting)](#penyelesaian-masalah-troubleshooting)
- [Kredit](#kredit)
- [Lisensi](#lisensi)

---

## Fitur Utama

### 📖 Manajemen Perpustakaan
- **Tampilan Dinamis**: Berpindah dengan mudah antara tata letak **grid** (kisi) dan **list** (daftar) yang dirancang dengan indah.
- **Ekstraksi Sampul Pintar**: Sampul buku diekstrak secara otomatis dari file EPUB.
- **Lazy Loading**: Sampul dimuat secara *lazy load* dengan efek *shimmer* yang elegan untuk performa optimal.
- **Pencarian Instan**: Pemfilteran *real-time* berdasarkan judul buku.
- **Lencana Progres Baca**: Terdapat lencana "Lanjutkan membaca" untuk buku yang sudah mulai Anda baca.

### 👓 Pengalaman Membaca
- **Navigasi Mulus**: Membalik halaman melalui tombol di layar, **gestur usap (swipe)**, atau pintasan keyboard.
- **Pelacakan Progres**: Bilah progres visual yang menunjukkan sisa waktu membaca.
- **Mode Imersif**: Toolbar yang otomatis bersembunyi saat membaca dan muncul kembali saat diketuk.
- **Banyak Tema**: Beralih antara mode Terang ☀, Sepia 📜, dan Gelap 🌙.
- **Kontrol Tipografi**: Sesuaikan ukuran font (70%–200%) dan pilih dari font premium yang di-*host* secara lokal.
- **Peredup Layar**: *Slider* bawaan untuk meredupkan layar tanpa mengubah kecerahan sistem (*brightness*).
- **Mode Tata Letak**: Beralih antara mode gulir terus-menerus (*scroll*) dan mode halaman (*paginated*).
- **Pencarian Global**: Mencari teks tertentu di seluruh isi buku.
- **Terjemahan Sebaris**: Pilih teks untuk diterjemahkan secara instan melalui Google Translate.
- **Bookmark (Penanda)**: Simpan dan kelola *bookmark* dengan daftar riwayat.
- **Statistik Membaca**: Lacak total halaman yang dibaca, waktu yang dihabiskan, rekor berturut-turut (streak), dan lihat grafik baca 7 hari.
- **Status Berkelanjutan**: Posisi membaca terakhir Anda otomatis tersimpan.
- **Unduh Langsung**: Unduh file EPUB langsung dari antarmuka pembaca.

---

## Struktur Direktori

```text
/
├── books/              # Folder untuk menyimpan file .epub Anda
├── cache/
│   └── covers/         # Cache disk otomatis untuk sampul buku yang diubah ukurannya
├── ui/                 # Aset UI statis dan file frontend
│   ├── fonts/          # Font lokal (tanpa CDN eksternal)
│   ├── img/            # Gambar statis dan favicon
│   ├── js/             # File JavaScript (epub.js, dll.)
│   ├── index.html      # Halaman perpustakaan (daftar buku)
│   ├── index.css       # Gaya untuk halaman perpustakaan
│   ├── reader.html     # Halaman pembaca EPUB
│   ├── reader.css      # Gaya untuk halaman pembaca
│   ├── translate-reader.css # Gaya untuk panel terjemahan
│   ├── upload.html     # Halaman unggah buku
│   └── upload.css      # Gaya untuk halaman unggah
├── get-books.php       # API endpoint untuk mendaftar buku
├── get-cover.php       # Mengekstrak, mengubah ukuran, dan men-cache sampul EPUB
├── get-epub-part.php   # Mengalirkan konten internal EPUB ke browser
├── get-meta.php        # Mengekstrak metadata (judul, penulis) dari EPUB
├── index.php           # Mengarahkan ke ui/index.html
├── translate.php       # API endpoint untuk integrasi Google Translate
├── upload.php          # API endpoint untuk menangani unggahan EPUB
├── start.sh            # Skrip pintasan untuk menjalankan server
├── php.ini             # Konfigurasi ukuran unggahan PHP (500MB)
├── .user.ini           # Konfigurasi ukuran unggahan PHP untuk server spesifik
├── LICENSE             # Lisensi proyek
└── readme.md           # Dokumentasi ini
```

---

## Persyaratan Server

| Persyaratan | Detail |
|-----------|------------|
| PHP | ≥ 7.4 |
| Ekstensi PHP | `zip`, `dom`, `fileinfo` |
| Ekstensi PHP (opsional)| `gd` — untuk mengubah ukuran dan mengompresi sampul otomatis |
| Web Server | Apache / Nginx / PHP built-in server |
| Browser | Versi modern dari Chrome, Firefox, Safari, Edge |

> **Catatan mengenai GD:** Jika ekstensi `gd` aktif, sampul akan diubah ukurannya menjadi maksimal 300px dan dikompresi (kualitas JPEG 30) sebelum disimpan ke cache disk. Tanpa GD, sampul asli dari EPUB akan ditampilkan langsung.

---

## Instalasi

### 1. Klon atau Unduh Proyek

```bash
git clone https://github.com/Budhycc/Book-Reader.git
cd Book-Reader
```

### 2. Tambahkan Buku Anda

Salin file `.epub` Anda ke dalam direktori `books/`:

```bash
cp ~/Downloads/buku-saya.epub books/
```

Sebagai alternatif, Anda dapat mengunggah buku langsung menggunakan antarmuka web melalui halaman Unggah.

### 3. Jalankan Server

**Menggunakan PHP built-in server (untuk lokal/pengembangan):**

Anda dapat menggunakan skrip pintasan yang telah disediakan agar ukuran maksimal unggahan (upload limit) otomatis menjadi 500MB:

```bash
./start.sh
```

Atau menjalankannya secara manual:

```bash
php -c php.ini -S 0.0.0.0:8000
```

Buka `http://localhost:8000` (atau IP lokal Anda) di browser web.

**Menggunakan Apache / Nginx:**
Arahkan *document root* web server Anda ke folder proyek ini dan pastikan PHP telah diaktifkan. File `.user.ini` yang tersedia akan mengatur limit unggahan.

---

## Panduan Penggunaan

### Antarmuka Perpustakaan
- Buka `index.php` (yang mengarah ke `ui/index.html`) untuk menjelajahi koleksi Anda.
- Ketik di kotak pencarian untuk memfilter buku berdasarkan judul dengan cepat.
- Gunakan ikon ⊞ dan ≡ untuk beralih antara tampilan *grid* dan *list*.
- Klik sampul atau judul buku untuk mulai membaca.

### Pembaca — Navigasi

| Tindakan | Cara Mengaktifkan |
|------|------|
| Halaman Berikutnya | Tombol ▶, usap kiri, `→`, atau `Spasi` |
| Halaman Sebelumnya | Tombol ◀, usap kanan, atau `←` |
| Kembali ke Perpustakaan| Tombol 🏠 atau tekan `Esc` |
| Tampilkan/Sembunyikan Toolbar | Ketuk bagian tengah layar |

### Pembaca — Fitur

| Fitur | Akses |
|-------|-----------|
| Daftar Isi (TOC) | Tombol ☰ di sebelah kiri toolbar |
| Bookmark halaman ini | Tombol 🔖 atau tekan `B` |
| Cari teks | Tombol 🔍 atau tekan `F` |
| Pengaturan Tampilan | Tombol ⚙ di sebelah kanan toolbar |
| Statistik Membaca | ⚙ Pengaturan → 📊 Statistik |
| Unduh Buku | ⚙ Pengaturan → ⬇ Unduh |

### Pembaca — Pengaturan Tampilan

Buka panel pengaturan (⚙) untuk menyesuaikan:
- **Ukuran Font**: A− untuk memperkecil, A+ untuk memperbesar, ↺ untuk reset.
- **Keluarga Font**: Pilih di antara font lokal premium.
- **Tema**: ☀ Terang / 📜 Sepia / 🌙 Gelap.
- **Mode Tampilan**: Beralih antara mode *Page* (halaman) dan *Scroll* (gulir).
- **Peredup (Dimmer)**: Mengurangi kecerahan layar secara *software*.
- **Spasi dan Margin**: Atur spasi baris dan jarak tepi teks.

---

## Penyimpanan Data

Semua data pengguna dan progres membaca disimpan secara ketat di dalam **localStorage** browser — tidak ada data pribadi yang dikirim atau disimpan di server.

| Jenis Data | Kunci localStorage |
|------|-----------------|
| Posisi Baca Terakhir | `epub-books/nama-buku.epub` |
| Ukuran Font | `reader-fontSize` |
| Keluarga Font | `reader-fontFamily` |
| Tema | `reader-theme` |
| Mode Tampilan | `reader-flow` |
| Bookmark | `bm-books/nama-buku.epub` |
| Statistik Membaca | `stats-books/nama-buku.epub` |

Sampul buku di-cache di **sessionStorage** pada browser dan ditulis ke **cache disk** (`cache/covers/`) pada server untuk mengoptimalkan kecepatan memuat.

---

## Caching Sampul

`get-cover.php` menggunakan sistem caching dua lapis:

**1. Cache Disk Sisi Server**
- Sampul yang diubah ukurannya disimpan di `cache/covers/` sebagai file `.jpg`.
- Permintaan berikutnya dilayani langsung dari disk tanpa mengekstrak ulang ZIP.
- Kunci cache dihasilkan berdasarkan nama file buku dan waktu modifikasi, otomatis dibatalkan (invalidate) jika file buku diperbarui.

**2. Cache Browser Sisi Klien**
- Menggunakan header `Cache-Control: public, max-age=31536000, immutable` bersama dengan header `ETag`.
- Browser tidak akan mengambil sampul lagi selama cache masih valid.

**Pembersihan Otomatis**
- Terdapat peluang 1% setiap kali ada permintaan sampul untuk memicu rutinitas pembersihan otomatis.
- File cache yatim piatu (sampul buku yang bukunya sudah dihapus dari folder `books/`) akan otomatis dihapus.
- Tidak diperlukan intervensi manual atau cron job.

Untuk membersihkan cache server secara manual:
```bash
rm -rf cache/covers/
```

---

## Internal API Endpoints

### `get-cover.php`
Mengekstrak, mengubah ukuran, dan men-cache gambar sampul dari file EPUB.
```
GET get-cover.php?book=books/nama.epub
```

### `get-epub-part.php`
Mengalirkan konten internal EPUB (HTML, CSS, gambar, font) ke browser.
```
GET get-epub-part.php?book=books/nama.epub&file=path/di/dalam/epub.xhtml
```

### `upload.php`
Menerima file EPUB melalui POST dan menyimpannya ke direktori `books/`.
```
POST upload.php
Content-Type: multipart/form-data
```

### `get-meta.php`
Mengekstrak metadata (judul, penulis) dari file EPUB.
```
GET get-meta.php                    # Semua buku
GET get-meta.php?book=books/nama.epub  # Buku tertentu
```

### `translate.php`
Menerjemahkan teks menggunakan API Google Translate.
```
POST translate.php
Content-Type: application/json
Body: {"text": "Hello world", "source": "en", "target": "id"}
```

Semua endpoint menyertakan validasi keamanan untuk memastikan hanya file `.epub` di dalam direktori `books/` yang dapat diakses, mencegah serangan *path traversal*.

---

## Keamanan

- Jalur buku divalidasi dengan ketat menggunakan regex: hanya `books/*.epub` yang diizinkan.
- `get-epub-part.php` secara aktif memblokir `..` dan jalur absolut.
- Unggahan dibatasi khusus untuk file `.epub`, dengan nama file yang otomatis dibersihkan (*sanitized*).
- Konten EPUB dikotakpasir (*sandboxed*) dalam sebuah *iframe* untuk mencegah eksekusi kode acak.
- Tidak diperlukan autentikasi, menjadikannya sempurna untuk penggunaan lokal atau intranet yang aman.

---

## Menambahkan Font Baru

Untuk menambahkan font kustom, buka `ui/reader.html` dan tambahkan opsi baru di dalam elemen `<select id="fontSelect">`:

```html
<option value="Literata">Literata</option>
```

Pastikan font tersebut diimpor dengan benar menggunakan `@font-face` di dalam `ui/fonts/fonts.css`. Semua font di-host secara lokal untuk memastikan fungsionalitas luring (offline) yang lengkap.

---

## Dependensi

| Pustaka (Library) | Versi | Lisensi |
|---------|-------|---------|
| [epub.js](https://github.com/futurepress/epub.js) | 0.3.x | FreeBSD |
| [JSZip](https://stuk.github.io/jszip/) | 3.10.x | MIT |
| Font Lokal | — | OFL / Beragam |

Semua dependensi, termasuk file font dan pustaka JS, dilayani secara lokal. Aplikasi sama sekali tidak membuat permintaan ke CDN eksternal untuk fungsi membaca intinya.

---

## Penyelesaian Masalah (Troubleshooting)

**Sampul tidak dimuat**
Pastikan ekstensi PHP `zip` sudah diinstal dan diaktifkan. Anda dapat memeriksanya dengan menjalankan `php -m | grep zip`.

**Sampul dimuat lambat pada kali pertama**
Ini adalah perilaku normal. Sampul diekstrak dari arsip ZIP dan diubah ukurannya pada permintaan awal. Semua pemuatan berikutnya akan disajikan dengan cepat dari cache disk.

**Cache sampul tidak diperbarui saat buku diganti**
Anda dapat membersihkannya secara manual dengan menjalankan `rm -rf cache/covers/`, atau tunggu rutinitas pembersihan otomatis terpicu (peluang 1% per permintaan).

**Ekstensi GD tidak ditemukan**
Jika GD tidak terinstal, sampul akan ditampilkan dalam ukuran aslinya tanpa diubah ukurannya. Untuk menginstalnya di Debian/Ubuntu: `sudo apt install php-gd`. Di Windows (XAMPP/Laragon): hapus tanda komentar pada `extension=gd` di `php.ini` Anda.

**Buku gagal dibuka**
Pastikan file EPUB tersebut valid menggunakan alat seperti EPUBCheck. File yang korup atau dilindungi DRM tidak didukung.

**Gaya font berubah tanpa diduga**
`epub.js` merender buku di dalam *iframe*. Beberapa EPUB memiliki CSS internal (*hardcoded*) yang menimpa pengaturan pembaca. Ini adalah perilaku normal yang ditentukan oleh pemformatan bawaan buku tersebut.

**Progres membaca tidak muncul**
Pembuatan lokasi buku (`book.locations.generate`) membutuhkan beberapa saat. Progres akan muncul setelah proses ini selesai, biasanya 1–5 detik setelah buku dibuka.

---

## Kredit

- [epub.js](https://github.com/futurepress/epub.js) - Pustaka pembaca EPUB yang tangguh untuk browser.
- [JSZip](https://stuk.github.io/jszip/) - Pustaka untuk membuat, membaca, dan mengedit file .zip.
- Google Translate API - Terintegrasi untuk terjemahan teks sebaris yang mulus.

---

## Lisensi

Proyek ini dilisensikan di bawah [AGPL-3.0 License](LICENSE).
