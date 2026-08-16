# 📚 EPUB Library

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)

A beautiful, self-hosted web application for reading your EPUB book collection locally. Built with pure PHP on the backend and `epub.js` on the frontend — featuring a modern aesthetic, no heavy frameworks, no database, and zero complex setup.

---

## Table of Contents

- [Key Features](#key-features)
- [Directory Structure](#directory-structure)
- [Server Requirements](#server-requirements)
- [Installation](#installation)
- [Usage Guide](#usage-guide)
- [Data Storage](#data-storage)
- [Cover Caching](#cover-caching)
- [Internal API Endpoints](#internal-api-endpoints)
- [Security](#security)
- [Adding New Fonts](#adding-new-fonts)
- [Dependencies](#dependencies)
- [Troubleshooting](#troubleshooting)
- [Credits](#credits)
- [License](#license)

---

## Key Features

### 📖 Library Management
- **Dynamic Views**: Easily toggle between beautifully designed **grid** and **list** layouts.
- **Smart Cover Extraction**: Book covers are automatically extracted from the EPUB files.
- **Lazy Loading**: Covers lazy load with elegant shimmer effects for optimal performance.
- **Instant Search**: Real-time filtering by book title.
- **Reading Progress Badge**: "Continue reading" badge for books you've already started.

### 👓 Reader Experience
- **Fluid Navigation**: Turn pages via on-screen buttons, **swipe gestures**, or keyboard shortcuts.
- **Progress Tracking**: Visual progress bar indicating remaining reading time.
- **Immersive Mode**: Auto-hiding toolbar that vanishes while reading and reappears on tap.
- **Multiple Themes**: Switch between Light ☀, Sepia 📜, and Dark 🌙 modes.
- **Typography Control**: Adjust font sizes (70%–200%) and select from locally hosted premium fonts.
- **Screen Dimming**: Built-in slider to dim the screen without altering system brightness.
- **Layout Modes**: Toggle between continuous scroll and paginated views.
- **Global Search**: Search for specific text across the entire book.
- **Inline Translation**: Select text to translate instantly via Google Translate.
- **Bookmarks**: Save and manage bookmarks with a history list.
- **Reading Stats**: Track total pages read, time spent, daily streaks, and view a 7-day reading graph.
- **Seamless State**: Your last reading position is automatically saved.
- **Direct Download**: Download the EPUB file directly from the reader interface.

---

## Directory Structure

```text
/
├── books/              # Folder to store your .epub files
├── cache/
│   └── covers/         # Auto-generated disk cache for resized book covers
├── ui/                 # Static UI assets and frontend files
│   ├── fonts/          # Self-hosted local fonts (no external CDNs)
│   ├── img/            # Static images and favicons
│   ├── js/             # JavaScript files (epub.js, etc.)
│   ├── index.html      # Library page (book list)
│   ├── index.css       # Styling for the library page
│   ├── reader.html     # EPUB reader page
│   ├── reader.css      # Styling for the reader page
│   ├── translate-reader.css # Styling for the translation panel
│   ├── upload.html     # Book upload page
│   └── upload.css      # Styling for the upload page
├── get-books.php       # API endpoint for listing books
├── get-cover.php       # Extracts, resizes, and caches EPUB covers
├── get-epub-part.php   # Streams internal EPUB content to the browser
├── get-meta.php        # Extracts metadata (title, author) from EPUB files
├── index.php           # Redirects to ui/index.html
├── translate.php       # API endpoint for Google Translate integration
├── upload.php          # API endpoint for handling EPUB uploads
├── LICENSE             # Project license
└── readme.md           # This documentation
```

---

## Server Requirements

| Requirement | Details |
|-----------|------------|
| PHP | ≥ 7.4 |
| PHP Extensions | `zip`, `dom`, `fileinfo` |
| PHP Extensions (optional)| `gd` — for automatic cover resizing & compression |
| Web Server | Apache / Nginx / PHP built-in server |
| Browser | Modern versions of Chrome, Firefox, Safari, Edge |

> **Note on GD:** If the `gd` extension is active, covers will be resized to a maximum of 300px and compressed (JPEG quality 30) before being cached to disk. Without GD, the original cover from the EPUB is displayed directly.

---

## Installation

### 1. Clone or Download the Project

```bash
git clone https://github.com/Budhycc/Book-Reader.git
cd Book-Reader
```

### 2. Add Your Books

Copy your `.epub` files into the `books/` directory:

```bash
cp ~/Downloads/my-book.epub books/
```

Alternatively, you can upload books directly using the web interface via `ui/upload.html`.

### 3. Run the Server

**Using the built-in PHP server (for development):**

```bash
php -S localhost:8080
```

Open `http://localhost:8080` in your web browser.

**Using Apache / Nginx:**
Point your document root to the project folder and ensure PHP is enabled.

---

## Usage Guide

### Library Interface
- Open `index.php` (which redirects to `ui/index.html`) to browse your collection.
- Type in the search box to quickly filter books by title.
- Use the ⊞ and ≡ icons to switch between grid and list views.
- Click a book cover or title to start reading.

### Reader — Navigation

| Action | How to Trigger |
|------|------|
| Next Page | ▶ button, swipe left, `→`, or `Space` |
| Previous Page | ◀ button, swipe right, or `←` |
| Back to Library | 🏠 button or press `Esc` |
| Toggle Toolbar | Tap the center of the screen |

### Reader — Features

| Feature | Access |
|-------|-----------|
| Table of Contents (TOC) | ☰ button on the left of the toolbar |
| Bookmark this page | 🔖 button or press `B` |
| Search text | 🔍 button or press `F` |
| Display Settings | ⚙ button on the right of the toolbar |
| Reading Statistics | ⚙ Settings → Statistics |
| Download Book | ⚙ Settings → ⬇ Download |

### Reader — Display Settings

Open the settings panel (⚙) to adjust:
- **Font Size**: A− to decrease, A+ to increase, ↺ to reset.
- **Font Family**: Choose between premium local fonts.
- **Theme**: ☀ Light / 📜 Sepia / 🌙 Dark.
- **View Mode**: Toggle between paginated (Page) and continuous Scroll modes.
- **Dimming Slider**: Reduce screen brightness purely via software overlay.

---

## Data Storage

All user data and reading progress are stored strictly in the browser's **localStorage** — no personal data is sent to or saved on the server.

| Data Type | localStorage Key |
|------|-----------------|
| Last Reading Position | `epub-books/book-name.epub` |
| Font Size | `reader-fontSize` |
| Font Family | `reader-fontFamily` |
| Theme | `reader-theme` |
| View Mode | `reader-flow` |
| Bookmarks | `bm-books/book-name.epub` |
| Reading Statistics | `stats-books/book-name.epub` |

Book covers are cached in **sessionStorage** on the browser and written to **disk cache** (`cache/covers/`) on the server to optimize loading speeds.

---

## Cover Caching

`get-cover.php` utilizes a dual-layer caching system:

**1. Server-side Disk Cache**
- Resized covers are saved in `cache/covers/` as `.jpg` files.
- Subsequent requests are served directly from disk without re-extracting the ZIP.
- Cache keys are generated based on the book's filename and modification time, automatically invalidating if a book file is updated.

**2. Client-side Browser Cache**
- Uses `Cache-Control: public, max-age=31536000, immutable` alongside `ETag` headers.
- The browser will not fetch the cover again while the cache is valid.

**Automatic Cleanup**
- There is a 1% probability per cover request to trigger an automatic cleanup routine.
- Orphaned cache files (covers belonging to books that were deleted from `books/`) are automatically removed.
- No manual intervention or cron jobs are necessary.

To manually clear the server cache:
```bash
rm -rf cache/covers/
```

---

## Internal API Endpoints

### `get-cover.php`
Extracts, resizes, and caches the cover image from an EPUB file.
```
GET get-cover.php?book=books/name.epub
```

### `get-epub-part.php`
Streams internal EPUB content (HTML, CSS, images, fonts) to the browser.
```
GET get-epub-part.php?book=books/name.epub&file=path/inside/epub.xhtml
```

### `upload.php`
Receives an EPUB file via POST and saves it to the `books/` directory.
```
POST upload.php
Content-Type: multipart/form-data
```

### `get-meta.php`
Extracts metadata (title, author) from EPUB files.
```
GET get-meta.php                    # All books
GET get-meta.php?book=books/name.epub  # Specific book
```

### `translate.php`
Translates text using the Google Translate API.
```
POST translate.php
Content-Type: application/json
Body: {"text": "Hello world", "source": "en", "target": "id"}
```

All endpoints include security validations to ensure only `.epub` files within the `books/` directory can be accessed, preventing path traversal attacks.

---

## Security

- Book paths are strictly validated using regex: only `books/*.epub` is permitted.
- `get-epub-part.php` actively blocks `..` and absolute paths.
- Uploads are restricted strictly to `.epub` files, with filenames automatically sanitized.
- EPUB content is sandboxed in an iframe to prevent arbitrary code execution.
- No authentication is required, making it perfect for secure local or intranet use.

---

## Adding New Fonts

To add custom fonts, open `ui/reader.html` and append a new option inside the `<select id="fontSelect">` element:

```html
<option value="Literata">Literata</option>
```

Ensure the font is correctly imported via `@font-face` within `ui/fonts/fonts.css`. All fonts are hosted locally to ensure complete offline functionality without relying on external CDNs.

---

## Dependencies

| Library | Version | License |
|---------|-------|---------|
| [epub.js](https://github.com/futurepress/epub.js) | 0.3.x | FreeBSD |
| [JSZip](https://stuk.github.io/jszip/) | 3.10.x | MIT |
| Local Fonts | — | OFL / Various |

All dependencies, including font files and JS libraries, are strictly served locally. The application makes zero requests to external CDNs for core reading functionalities.

---

## Troubleshooting

**Covers aren't loading**
Ensure the PHP `zip` extension is installed and enabled. You can check this by running `php -m | grep zip`.

**Covers load slowly the first time**
This is expected behavior. The cover is extracted from the ZIP archive and resized on the initial request. All subsequent loads will be served rapidly from the disk cache.

**Cover cache doesn't update when a book is replaced**
You can manually clear it by running `rm -rf cache/covers/`, or wait for the automatic cleanup routine to trigger (1% chance per request).

**The GD extension is missing**
If GD isn't installed, covers will display in their original sizes without resizing. To install it on Debian/Ubuntu: `sudo apt install php-gd`. On Windows (XAMPP/Laragon): uncomment `extension=gd` in your `php.ini`.

**Book fails to open**
Ensure the EPUB file is valid using tools like EPUBCheck. Corrupt or DRM-protected files are not supported.

**Font styles change unexpectedly**
`epub.js` renders books inside an iframe. Some EPUBs have hardcoded internal CSS that overrides reader settings. This is normal behavior dictated by the book's formatting.

**Reading progress isn't showing**
Generating book locations (`book.locations.generate`) takes a few moments. Progress will appear once this is completed, typically 1–5 seconds after opening a book.

---

## Credits

- [epub.js](https://github.com/futurepress/epub.js) - Powerful EPUB reading library for the browser.
- [JSZip](https://stuk.github.io/jszip/) - Library for creating, reading and editing .zip files.
- Google Translate API - Integrated for seamless inline text translation.

---

## License

This project is licensed under the [AGPL-3.0 License](LICENSE).
