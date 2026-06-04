# LAPORAN EAS PEMROGRAMAN WEB

---

**Judul Topik Karya:**  
CariinTern: Platform Web Pencarian dan Pendaftaran Magang Terintegrasi untuk Mahasiswa dan Perusahaan

**Mata Kuliah:** Pemrograman Web  
**Kelas:** F081  
**Dosen Pengampu:** M. Muharrom Al Haromainy, S.Kom., M.Kom.  
**Kelompok:** 4

**Anggota Kelompok:**

| No | Nama | NIM |
|----|------|-----|
| 1 | Galih Aji Pangestu | [isi NIM] |
| 2 | Fidelia Hahas Asabela | [isi NIM] |
| 3 | Mohammad Satria Putra Wicaksono | [isi NIM] |

**Tahun Akademik:** [isi tahun akademik]  
**Program Studi:** [isi prodi]

---

## DAFTAR ISI

1. Pendahuluan  
2. Orisinalitas Proyek  
3. Analisis Sistem dan Diagram  
4. Detail Proyek, Fungsi, dan Menu  
5. Dokumentasi Screenshot UI  
6. Demo Proyek (Video)  
7. Deployment Proyek  
8. Kesimpulan  
9. Daftar Lampiran  

---

## BAB 1 — PENDAHULUAN

### 1.1 Latar Belakang

Mahasiswa sering kesulitan mencari informasi magang karena data tersebar di banyak situs, media sosial, dan grup Telegram. Perusahaan juga membutuhkan kanal terpusat untuk mempublikasikan lowongan dan menerima lamaran. Admin kampus membutuhkan sistem untuk memverifikasi perusahaan, memantau lowongan, dan melihat laporan aktivitas.

CariinTern dikembangkan sebagai solusi web terintegrasi yang menghubungkan tiga peran utama: **Admin**, **Perusahaan**, dan **Mahasiswa** dalam satu platform pendaftaran magang.

### 1.2 Rumusan Masalah

1. Bagaimana menyediakan sistem pendaftaran magang berbasis web yang mudah diakses mahasiswa dan perusahaan?  
2. Bagaimana mengelola verifikasi perusahaan, lowongan, dan lamaran secara terpusat?  
3. Bagaimana memperluas sumber informasi lowongan dengan mekanisme pengumpulan data eksternal yang terkontrol?  
4. Bagaimana meningkatkan pengalaman pengguna melalui antarmuka modern, responsif, dan fitur PWA?

### 1.3 Tujuan

1. Membangun aplikasi web pendaftaran magang multi-role (admin, perusahaan, mahasiswa).  
2. Menyediakan fitur pencarian lowongan, pengiriman lamaran, dan pelacakan status lamaran.  
3. Mengimplementasikan auto-scraper untuk mengumpulkan lowongan eksternal dengan panel review admin.  
4. Mengimplementasikan PWA dan push notification untuk meningkatkan aksesibilitas.  
5. Mendokumentasikan sistem melalui diagram UML dan screenshot seluruh halaman.

### 1.4 Ruang Lingkup

- Bahasa pemrograman: PHP Native  
- Database: MySQL  
- Frontend: Bootstrap 5, JavaScript, Chart.js  
- Fitur tambahan: PWA, Web Push, Auto Scraper  
- Deployment: hosting gratis (PHP + MySQL)

### 1.5 Metodologi Pengembangan

Pengembangan dilakukan secara iteratif: analisis kebutuhan → perancangan database dan antarmuka → implementasi modul per role → pengujian fungsional → dokumentasi EAS.

---

## BAB 2 — ORISINALITAS PROYEK

Bagian ini menjawab aspek **orisinalitas** pada penilaian EAS.

### 2.1 Perbandingan dengan Sistem Serupa

| Aspek | Portal magang umum | CariinTern |
|-------|-------------------|------------|
| Multi-role terintegrasi | Terbatas | Admin, Perusahaan, Mahasiswa dalam satu sistem |
| Lowongan eksternal | Jarang ada | Auto-scraper + review admin + badge "Eksternal" |
| PWA | Jarang | Manifest, service worker, offline page, install prompt |
| Push notification | Jarang di PHP native | Web Push API + trigger otomatis (verifikasi, status lamaran) |
| UI landing page | Statis sederhana | Hero modern, animasi, testimoni, statistik live |
| Keamanan form | Bervariasi | CSRF token, validasi terpusat, rate limit login |

### 2.2 Keunikan dan Kontribusi Orisinal

1. **Integrasi tiga ekosistem dalam satu platform** — Admin mengelola master data dan verifikasi; perusahaan mempublikasikan lowongan; mahasiswa mencari dan melamar tanpa pindah sistem.  
2. **Auto-scraper multi-sumber** — Mendukung website (CSS selector), RSS, Telegram, dan Google Custom Search dengan deduplikasi, auto-kategori, log eksekusi, dan alur approve/reject/import oleh admin.  
3. **PWA production-ready** — Service worker dengan strategi cache, halaman offline, dan metadata instalasi aplikasi di perangkat mobile.  
4. **Push notification berbasis event bisnis** — Notifikasi terkirim saat perusahaan diverifikasi admin atau status lamaran diubah perusahaan.  
5. **Desain UI modern dan responsif** — Landing page dengan typewriter effect, AOS animation, layout mobile-first, dan redesign halaman detail lowongan mahasiswa.  
6. **Branding dan identitas proyek konsisten** — Nama CariinTern, tema visual seragam, dan dokumentasi lengkap untuk kebutuhan akademik.

### 2.3 Teknologi yang Digunakan (Orisinal dalam Konteks Tugas)

- PHP Native + PDO (tanpa framework berat, menunjukkan penguasaan fundamental)  
- Composer + library `minishlink/web-push`  
- Service Worker + `manifest.json`  
- cURL + DOMDocument/DOMXPath untuk scraping  
- Chart.js untuk dashboard analitik admin  

### 2.4 Kesimpulan Orisinalitas

CariinTern bukan sekadar CRUD lowongan magang. Proyek ini menggabungkan **manajemen magang kampus**, **otomatisasi pengumpulan lowongan eksternal**, dan **teknologi web modern (PWA + push)** dalam satu solusi yang relevan dengan kebutuhan mahasiswa dan standar penilaian Pemrograman Web.

---

## BAB 3 — ANALISIS SISTEM DAN DIAGRAM

### 3.1 Aktor Sistem

| Aktor | Deskripsi |
|-------|-----------|
| Admin | Mengelola user, kategori, program studi, verifikasi perusahaan, scraper, dan laporan |
| Perusahaan | Mengelola profil, lowongan, dan status pelamar |
| Mahasiswa | Mengelola profil, dokumen, mencari lowongan, dan melamar |
| Sistem Scraper | Modul otomatis pengambilan lowongan eksternal (dijalankan admin/cron) |

### 3.2 Use Case Diagram

**Gambar 1. Use Case Diagram Sistem CariinTern**

```
[Lampirkan diagram use case di sini. Referensi struktur di bawah.]

                    ┌─────────────────────────────────────────┐
                    │           SISTEM CARIINTERN             │
                    └─────────────────────────────────────────┘
         ┌──────────────┐    ┌──────────────┐    ┌──────────────┐
         │    Admin     │    │  Perusahaan  │    │  Mahasiswa   │
         └──────┬───────┘    └──────┬───────┘    └──────┬───────┘
                │                   │                   │
    ┌───────────┼───────────┐       │       ┌───────────┼───────────┐
    │ Kelola User           │       │       │ Kelola Profil         │
    │ Verifikasi Perusahaan │       │       │ Upload CV/Transkrip   │
    │ Kelola Kategori       │       │       │ Cari Lowongan         │
    │ Kelola Program Studi  │       │       │ Lamar Magang          │
    │ Kelola Scraper        │       │       │ Lacak Lamaran         │
    │ Review Lowongan Scrape│       │       │ Upload Foto Profil    │
    │ Lihat Laporan         │       │       └───────────────────────┘
    └───────────────────────┘       │
                            ┌───────┴───────┐
                            │ CRUD Lowongan │
                            │ Review Pelamar│
                            │ Update Status │
                            └───────────────┘

    Semua aktor: Login, Logout, Register (perusahaan/mahasiswa)
    Publik: Lihat Landing Page
```

*Catatan: Salin diagram ke Draw.io, StarUML, atau Word SmartArt. Ganti placeholder dengan gambar final.*

---

### 3.3 Activity Diagram — Alur Lamaran Mahasiswa

**Gambar 2. Activity Diagram — Proses Lamaran Magang**

```
[Lampirkan activity diagram di sini]

[Start] → Login Mahasiswa → Lengkapi Profil & Upload Dokumen
       → Cari Lowongan (Internal / Eksternal)
       → Buka Detail Lowongan
       → {Dokumen lengkap?}
            Tidak → [Peringatan lengkapi profil] → Kembali ke Profil
            Ya → Submit Lamaran
       → Status: Submitted
       → Perusahaan Review → Status: Review / Accepted / Rejected
       → [Notifikasi Push ke Mahasiswa]
       → [End]
```

---

### 3.4 Activity Diagram — Alur Auto Scraper (Admin)

**Gambar 3. Activity Diagram — Auto Scraper**

```
[Lampirkan activity diagram di sini]

[Start] → Admin Login → Buka Menu Auto Scraper
       → {Tambah / Jalankan Scraper?}
            Tambah → Input Sumber (Website/RSS/Telegram/Google CSE) → Simpan
            Jalankan → Trigger CLI/API Background
       → Scraper Engine Ambil Data → Simpan ke scraped_jobs (pending)
       → Admin Review (Approve / Reject / Duplicate)
       → {Approve?}
            Ya → Import ke job_listings → Tampil ke Mahasiswa (badge Eksternal)
            Tidak → Tetap di arsip rejected/duplicate
       → Catat Log Eksekusi
       → [End]
```

---

### 3.5 Sequence Diagram — Login dan Akses Dashboard

**Gambar 4. Sequence Diagram — Login**

```
[Lampirkan sequence diagram di sini]

Mahasiswa    Halaman Login    Auth/Session    Database
    |              |              |              |
    |-- POST cred -|              |              |
    |              |-- validate ->|              |
    |              |              |-- query user->|
    |              |              |<- user data --|
    |              |<- set session|              |
    |<- redirect --|              |              |
    |-- GET dashboard ---------->|              |
    |              |              |-- load stats->|
    |<- HTML dashboard ----------|              |
```

---

### 3.6 Sequence Diagram — Perusahaan Ubah Status Lamaran + Push Notification

**Gambar 5. Sequence Diagram — Update Status Lamaran**

```
[Lampirkan sequence diagram di sini]

Perusahaan   UI Pelamar   update_status.php   Database   push_notification
    |            |              |                |              |
    |-- ubah status ---------->|                |              |
    |            |-- POST JSON->|                |              |
    |            |              |-- UPDATE app -->|              |
    |            |              |-- log activity->|              |
    |            |              |-- notify_user ---------------->|
    |            |              |                |   (Web Push) |
    |            |<- JSON OK ---|                |              |
```

---

### 3.7 Entity Relationship Diagram (ERD)

**Gambar 6. Entity Relationship Diagram (ERD)**

```
[Lampirkan ERD di sini. Entitas utama:]

users ──┬── student_profiles
        ├── company_profiles
        └── activity_logs / notifications

internship_categories ── job_listings ── applications
programs ── student_profiles

scraper_sources ── scraped_jobs ── scraper_logs
scraped_jobs ── (optional) job_listings (setelah import)
```

*Sumber: `database/schema.sql` — export diagram dari MySQL Workbench atau dbdiagram.io.*

---

## BAB 4 — DETAIL PROYEK, FUNGSI, DAN MENU

### 4.1 Arsitektur Sistem

CariinTern menggunakan arsitektur **3-tier sederhana**:

1. **Presentation Layer** — HTML, Bootstrap 5, JavaScript, Chart.js, PWA assets  
2. **Application Layer** — PHP Native (auth, validation, business logic, scraper engine)  
3. **Data Layer** — MySQL via PDO  

**Gambar 7. Diagram Arsitektur Sistem**

```
[Lampirkan diagram arsitektur 3-tier di sini]
```

---

### 4.2 Struktur Role dan Hak Akses

| Role | Hak Akses Utama |
|------|-----------------|
| Admin | Full access master data, verifikasi, scraper, laporan |
| Perusahaan | Profil, CRUD lowongan milik sendiri, kelola pelamar |
| Mahasiswa | Profil, dokumen, cari lowongan, lamaran |
| Tamu (publik) | Landing page, login, register |

---

### 4.3 Menu dan Fungsi — Halaman Publik

| No | Halaman / File | Fungsi |
|----|----------------|--------|
| 1 | `index.php` | Landing page: hero, statistik, fitur, lowongan terbaru, testimoni |
| 2 | `login.php` | Autentikasi multi-role |
| 3 | `register.php` | Registrasi mahasiswa atau perusahaan |
| 4 | `logout.php` | Keluar sesi |
| 5 | `offline.php` | Halaman fallback saat tidak ada koneksi (PWA) |
| 6 | `errors/404.php` | Halaman tidak ditemukan |
| 7 | `errors/403.php` | Akses ditolak |
| 8 | `errors/500.php` | Kesalahan server |

---

### 4.4 Menu dan Fungsi — Mahasiswa

| Menu Sidebar | Halaman | Fungsi Detail |
|--------------|---------|---------------|
| Dashboard | `student/dashboard.php` | Ringkasan profil, lamaran terbaru, statistik |
| Profil Saya | `student/profile.php` | Data pribadi, upload CV/transkrip, foto profil, progress kelengkapan |
| Cari Lowongan | `student/jobs/index.php` | Filter kategori, lowongan internal + eksternal, toggle sumber eksternal |
| Detail & Lamar | `student/applications/apply.php` | Detail lowongan, countdown deadline, quota, submit lamaran |
| Lamaran Saya | `student/applications/index.php` | Daftar lamaran + timeline status |
| (aksi) | `student/applications/cancel.php` | Pembatalan lamaran (jika status memungkinkan) |

---

### 4.5 Menu dan Fungsi — Perusahaan

| Menu Sidebar | Halaman | Fungsi Detail |
|--------------|---------|---------------|
| Dashboard | `company/dashboard.php` | Statistik lowongan dan pelamar |
| Profil Perusahaan | `company/profile.php` | Data perusahaan, logo, status verifikasi |
| Lowongan Saya | `company/jobs/index.php` | Daftar lowongan yang dibuat |
| Buat Lowongan Baru | `company/jobs/create.php` | Form tambah lowongan |
| (edit) | `company/jobs/edit.php` | Ubah lowongan |
| (hapus) | `company/jobs/delete.php` | Hapus lowongan |
| Daftar Pelamar | `company/applicants/index.php` | Review pelamar per lowongan |
| (API) | `company/applicants/update_status.php` | Ubah status + trigger push notification |

---

### 4.6 Menu dan Fungsi — Admin

| Menu Sidebar | Halaman | Fungsi Detail |
|--------------|---------|---------------|
| Dashboard | `admin/dashboard.php` | Statistik sistem + grafik Chart.js |
| Manajemen User | `admin/users/index.php`, `create.php`, `edit.php` | CRUD akun pengguna |
| Verifikasi Perusahaan | `admin/companies/index.php` | Approve/reject perusahaan + push notifikasi |
| Kategori Magang | `admin/categories/index.php`, `create.php`, `edit.php`, `delete.php` | CRUD kategori |
| Program Studi | `admin/programs/index.php`, `create.php`, `edit.php`, `delete.php` | CRUD program studi |
| Semua Lowongan | `admin/jobs/index.php` | Monitoring seluruh lowongan sistem |
| Auto Scraper | `admin/scraper/index.php` | Dashboard scraper, trigger run |
| | `admin/scraper/create_source.php` | Tambah sumber scraper |
| | `admin/scraper/results.php` | Review pending/approved/rejected |
| | `admin/scraper/logs.php` | Monitoring log eksekusi |
| | `admin/trigger_scraper.php` | Trigger manual scraper |
| Laporan | `admin/reports.php` | Analitik + export CSV |

---

### 4.7 Fitur Teknis Tambahan

| Fitur | Deskripsi | File Terkait |
|-------|-----------|--------------|
| PWA | Install app, cache asset, offline detection | `manifest.json`, `sw.js`, `assets/js/pwa.js` |
| Push Notification | Subscribe/unsubscribe, kirim notifikasi | `api/push_subscribe.php`, `includes/push_notification.php` |
| Auto Scraper | Multi-source scraping engine | `scraper/ScraperEngine.php`, `scraper/run_scraper.php` |
| Keamanan | CSRF, validasi, rate limit login | `includes/Validator.php`, `includes/auth.php` |
| Upload Aman | Validasi MIME, `.htaccess` uploads | `includes/file_upload.php`, `uploads/*/.htaccess` |

---

## BAB 5 — DOKUMENTASI SCREENSHOT UI

**Petunjuk:** Ambil screenshot setiap halaman saat aplikasi berjalan (localhost atau deploy). Sisipkan ke Word dengan caption **Gambar X.** Sesuai urutan di bawah. Resolusi disarankan minimal 1280×720 (desktop) dan 390×844 (mobile untuk 2–3 halaman utama).

---

### 5.1 Halaman Publik

**Gambar 8. Landing Page (Beranda)**  
`index.php` — Tampilan hero, statistik, fitur, lowongan terbaru, testimoni.

**Gambar 9. Halaman Login**  
`login.php` — Form login multi-role.

**Gambar 10. Halaman Registrasi**  
`register.php` — Form daftar mahasiswa/perusahaan.

**Gambar 11. Halaman Offline (PWA)**  
`offline.php` — Tampilan saat tidak ada koneksi internet.

**Gambar 12. Halaman Error 404**  
`errors/404.php`

**Gambar 13. Halaman Error 403**  
`errors/403.php`

**Gambar 14. Halaman Error 500**  
`errors/500.php`

---

### 5.2 Halaman Mahasiswa

**Gambar 15. Dashboard Mahasiswa**  
`student/dashboard.php`

**Gambar 16. Profil Mahasiswa — Tab Data Pribadi**  
`student/profile.php`

**Gambar 17. Profil Mahasiswa — Tab Upload Dokumen**  
`student/profile.php` (tab dokumen)

**Gambar 18. Profil Mahasiswa — Upload Foto**  
`student/profile.php` (modal/foto profil)

**Gambar 19. Daftar Lowongan Mahasiswa**  
`student/jobs/index.php` — termasuk badge lowongan eksternal.

**Gambar 20. Detail Lowongan & Form Lamar**  
`student/applications/apply.php`

**Gambar 21. Daftar Lamaran Saya**  
`student/applications/index.php` — timeline status.

---

### 5.3 Halaman Perusahaan

**Gambar 22. Dashboard Perusahaan**  
`company/dashboard.php`

**Gambar 23. Profil Perusahaan**  
`company/profile.php`

**Gambar 24. Daftar Lowongan Perusahaan**  
`company/jobs/index.php`

**Gambar 25. Form Buat Lowongan**  
`company/jobs/create.php`

**Gambar 26. Form Edit Lowongan**  
`company/jobs/edit.php`

**Gambar 27. Daftar Pelamar**  
`company/applicants/index.php`

**Gambar 28. Ubah Status Pelamar (Notifikasi)**  
`company/applicants/index.php` — saat mengubah status + notifikasi sukses.

---

### 5.4 Halaman Admin

**Gambar 29. Dashboard Admin**  
`admin/dashboard.php` — grafik Chart.js.

**Gambar 30. Manajemen User**  
`admin/users/index.php`

**Gambar 31. Tambah User**  
`admin/users/create.php`

**Gambar 32. Edit User**  
`admin/users/edit.php`

**Gambar 33. Verifikasi Perusahaan**  
`admin/companies/index.php`

**Gambar 34. Kategori Magang**  
`admin/categories/index.php`

**Gambar 35. Program Studi**  
`admin/programs/index.php`

**Gambar 36. Semua Lowongan (Admin)**  
`admin/jobs/index.php`

**Gambar 37. Dashboard Auto Scraper**  
`admin/scraper/index.php`

**Gambar 38. Tambah Sumber Scraper**  
`admin/scraper/create_source.php`

**Gambar 39. Review Hasil Scraping**  
`admin/scraper/results.php`

**Gambar 40. Log Scraper**  
`admin/scraper/logs.php`

**Gambar 41. Laporan Admin & Export CSV**  
`admin/reports.php`

---

### 5.5 Fitur Tambahan (Opsional, Nilai Plus)

**Gambar 42. Prompt Install PWA**  
Tampilan banner "Install App" di browser mobile/desktop.

**Gambar 43. Push Notification**  
Notifikasi browser saat status lamaran berubah.

**Gambar 44. Tampilan Mobile / Responsif**  
Landing page atau dashboard di ukuran layar HP.

---

## BAB 6 — DEMO PROYEK (VIDEO)

### 6.1 Tujuan Video Demo

Video demo menunjukkan alur end-to-end sistem kepada dosen pengampu: login tiap role, fitur utama, auto-scraper, dan PWA (jika memungkinkan).

### 6.2 Link Video YouTube

**Link Demo:** [https://youtu.be/XXXXXXXXXXX](https://youtu.be/XXXXXXXXXXX)

*(Ganti XXXXXXXXXXX dengan ID video setelah upload ke YouTube. Disarankan unlisted, durasi 8–15 menit.)*

### 6.3 Narasi Demo (Skrip Singkat)

| Menit | Adegan | Yang Ditampilkan |
|-------|--------|------------------|
| 0:00–1:00 | Intro | Judul proyek, anggota kelompok, tujuan |
| 1:00–2:30 | Publik | Landing page, register, login |
| 2:30–5:00 | Mahasiswa | Profil, upload dokumen, cari lowongan, lamar, lacak status |
| 5:00–7:00 | Perusahaan | Buat lowongan, lihat pelamar, ubah status |
| 7:00–9:30 | Admin | Verifikasi perusahaan, scraper, review & import, laporan |
| 9:30–10:30 | Bonus | PWA install / push notification (jika sempat) |
| 10:30–11:00 | Penutup | Link deploy + kesimpulan |

---

## BAB 7 — DEPLOYMENT PROYEK

### 7.1 Opsi Hosting Gratis (PHP + MySQL)

| Platform | Kelebihan | Keterbatasan | Cocok untuk |
|----------|-----------|--------------|-------------|
| **InfinityFree** | PHP + MySQL gratis, subdomain gratis | Iklan, batas resource | **Rekomendasi utama** |
| 000webhost | Gratis, panel familiar | Sleep mode, batas bandwidth | Alternatif |
| Railway / Render | Modern deployment | Perlu konfigurasi Docker/env | Lanjutan |

### 7.2 Langkah Deploy ke InfinityFree (Rekomendasi)

1. Daftar akun di [https://infinityfree.com](https://infinityfree.com)  
2. Buat akun hosting baru → pilih subdomain gratis (contoh: `cariintern.rf.gd`)  
3. Buat database MySQL di panel → catat host, nama DB, user, password  
4. Upload file project via **File Manager** atau **FTP** (semua folder kecuali `.git`)  
5. Import `database/schema.sql` lewat **phpMyAdmin**  
6. Edit `config/config.php`:

```php
define('BASE_URL', 'https://cariintern.rf.gd');
define('DB_HOST', 'sqlXXX.infinityfree.com'); // sesuaikan panel
define('DB_NAME', 'if0_XXXXXXX_internship');
define('DB_USER', 'if0_XXXXXXX');
define('DB_PASS', 'password_dari_panel');
```

7. Set permission folder `uploads/` agar writable (chmod 755/775)  
8. Jalankan aplikasi: `https://cariintern.rf.gd`  
9. Login admin default: `admin@internship.com` / `admin123` → **ganti password segera**

### 7.3 Link Deploy Proyek

**URL Deploy:** [https://cariintern.rf.gd](https://cariintern.rf.gd)

*(Ganti dengan URL subdomain/hosting yang sudah aktif setelah deploy.)*

**Status Deploy:** [ ] Belum deploy  /  [ ] Sudah deploy  /  [ ] Sudah diuji end-to-end

### 7.4 Catatan Deploy untuk Fitur Khusus

| Fitur | Catatan di Hosting Gratis |
|-------|---------------------------|
| PWA / HTTPS | InfinityFree menyediakan SSL gratis — PWA membutuhkan HTTPS |
| Push Notification | Perlu HTTPS + VAPID key valid; uji di browser yang mengizinkan push |
| Auto Scraper cron | Gunakan **Cron Jobs** di panel hosting atau trigger manual dari admin |
| Composer vendor | Upload folder `vendor/` yang sudah di-generate lokal (`composer install`) |

**Gambar 45. Bukti Halaman Deploy Berhasil Dibuka**  
[Screenshot halaman login/landing dari URL deploy]

**Gambar 46. Bukti Database Terhubung**  
[Screenshot dashboard admin setelah login di server deploy]

---

## BAB 8 — KESIMPULAN

CariinTern berhasil diimplementasikan sebagai platform web pendaftaran magang terintegrasi dengan tiga peran pengguna. Sistem menyediakan manajemen lowongan dan lamaran, verifikasi perusahaan, laporan admin, serta fitur orisinal berupa **auto-scraper lowongan eksternal**, **PWA**, dan **push notification**.

Proyek memenuhi komponen penilaian EAS:

1. **Orisinalitas** — Kombinasi scraper, PWA, dan push notification dalam sistem magang kampus  
2. **Laporan** — Dokumen ini dengan diagram UML dan penjelasan lengkap  
3. **Detail proyek/fungsi/menu** — Terdokumentasi per role di Bab 4  
4. **Screenshot UI** — Daftar 39+ halaman dengan placeholder Gambar 8–46  
5. **Demo video** — Link YouTube di Bab 6  
6. **Deploy** — Panduan hosting gratis + link deploy di Bab 7  

Pengembangan selanjutnya dapat mencakup: email notification, chat admin-perusahaan, filter lokasi magang berbasis peta, dan peningkatan akurasi scraper dengan machine learning klasifikasi kategori.

---

## DAFTAR LAMPIRAN

| Lampiran | Isi |
|----------|-----|
| A | Source code (repository GitHub) |
| B | File `database/schema.sql` |
| C | Screenshot UI (Gambar 8–46) |
| D | Diagram UML (Gambar 1–7) |
| E | Link video demo YouTube |
| F | Link deploy hosting |
| G | Manual instalasi (`README.md`) |

**Repository GitHub:** [https://github.com/galiihajiip/CariinTern](https://github.com/galiihajiip/CariinTern)

---

## CHECKLIST PENGUMPULAN EAS

Centang sebelum dikumpulkan:

- [ ] Laporan Word/PDF (salin dari dokumen ini + sisipkan gambar)
- [ ] Diagram: Use Case, Activity (min. 2), Sequence (min. 2), ERD
- [ ] Screenshot semua halaman (Gambar 8–41 wajib, 42–44 opsional)
- [ ] Link video demo YouTube (unlisted/public)
- [ ] Link deploy hosting gratis (HTTPS)
- [ ] Source code / link GitHub
- [ ] Identitas kelompok, dosen, kelas di halaman judul

---

*Dokumen ini disusun untuk Kelompok 4 — EAS Pemrograman Web Kelas F081. Salin ke Microsoft Word, ubah font ke Times New Roman 12, spasi 1.5, dan sisipkan gambar sesuai placeholder.*
