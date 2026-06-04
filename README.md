# CariinTern

## Identitas Proyek

**Judul Topik Karya:** CariinTern: Platform Web Pencarian dan Pendaftaran Magang Terintegrasi untuk Mahasiswa dan Perusahaan

Project ini dibuat untuk memenuhi tugas **EAS Pemrograman Web Kelas F081**.

**Dosen Pengampu:** M. Muharrom Al Haromainy, S.Kom., M.Kom.

**Kelompok:** 4

**Anggota Kelompok:**

- Galih Aji Pangestu
- Fidelia Hahas Asabela
- Mohammad Satria Putra Wicaksono

CariinTern adalah aplikasi web pendaftaran magang berbasis PHP Native untuk menghubungkan mahasiswa, perusahaan, dan admin kampus dalam satu sistem terpusat. Aplikasi ini mendukung pencarian lowongan, pengiriman lamaran, verifikasi perusahaan, manajemen data master, laporan, dan dashboard analitik.

## Fitur Utama

- Multi-role authentication untuk `admin`, `company`, dan `student`.
- Dashboard role-based dengan statistik dan Chart.js.
- Manajemen user, kategori magang, program studi, dan verifikasi perusahaan.
- Company module untuk profil perusahaan, CRUD lowongan, dan review pelamar.
- Student module untuk profil mahasiswa, upload CV/transkrip, pencarian lowongan, submit lamaran, dan tracking status.
- Public landing page dengan statistik, kategori, dan lowongan terbaru.
- Upload dokumen, logo perusahaan, dan foto profil dengan validasi tipe file dan ukuran.
- CSRF protection, reusable Validator class, dan helper sanitasi output.
- Notification bell berbasis activity log.
- Push notification berbasis Web Push API dan VAPID.
- Progressive Web App (PWA) dengan manifest, service worker, cache strategy, install prompt, dan halaman offline.
- Auto-scraper lowongan eksternal dari website, RSS, Telegram, dan Google Custom Search.
- Admin panel untuk mengelola sumber scraper, review hasil scraping, import lowongan, dan monitoring log.
- Integrasi lowongan eksternal yang sudah disetujui ke halaman lowongan mahasiswa.
- Admin reports dengan export CSV.
- Custom error pages untuk 404, 403, dan 500.
- Hardening upload directory dan login rate limiting sederhana.

## Tech Stack

- PHP Native
- MySQL
- PDO
- Bootstrap 5
- Bootstrap Icons
- Chart.js
- Vanilla JavaScript
- Service Worker dan Web App Manifest
- Composer
- Web Push library
- Apache dengan `.htaccess` dan `mod_rewrite`

## Persyaratan Sistem

- PHP >= 7.4
- MySQL >= 5.7
- Apache dengan `mod_rewrite`
- Ekstensi PHP: `pdo_mysql`, `fileinfo`
- Browser modern

## Instalasi

1. Clone atau download project.

```bash
git clone https://github.com/galiihajiip/CariinTern.git
cd CariinTern
```

2. Buat database dan import schema.

```bash
mysql -u root -p < database/schema.sql
```

3. Edit konfigurasi aplikasi di `config/config.php`.

Sesuaikan nilai berikut dengan environment lokal atau server:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'internship_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', 'http://localhost/internship-system');
```

4. Pastikan folder `uploads/` writable.

```bash
chmod 755 uploads/
chmod 755 uploads/cv uploads/transcripts uploads/company_logos
```

5. Akses aplikasi melalui browser.

```text
http://localhost/internship-system
```

## Akun Default

Gunakan akun admin default berikut setelah import database:

```text
Email    : admin@internship.com
Password : admin123
Role     : admin
```

Segera ubah password admin default setelah aplikasi berjalan di server.

## Struktur Folder

```text
CariinTern/
|-- admin/
|   |-- categories/
|   |-- companies/
|   |-- programs/
|   |-- users/
|   |-- dashboard.php
|   `-- reports.php
|-- api/
|   `-- mark_notification_read.php
|-- assets/
|   |-- css/
|   `-- js/
|-- company/
|   |-- applicants/
|   |-- jobs/
|   |-- dashboard.php
|   `-- profile.php
|-- config/
|   |-- config.php
|   `-- db.php
|-- database/
|   `-- schema.sql
|-- errors/
|   |-- 403.php
|   |-- 404.php
|   `-- 500.php
|-- includes/
|   |-- auth.php
|   |-- file_upload.php
|   |-- flash.php
|   `-- functions.php
|-- layouts/
|   |-- footer.php
|   |-- header.php
|   |-- sidebar_admin.php
|   |-- sidebar_company.php
|   `-- sidebar_student.php
|-- student/
|   |-- applications/
|   |-- jobs/
|   |-- dashboard.php
|   `-- profile.php
|-- uploads/
|   |-- company_logos/
|   |-- cv/
|   `-- transcripts/
|-- index.php
|-- login.php
|-- register.php
|-- logout.php
`-- README.md
```

## Screenshot

![Dashboard](screenshots/dashboard.png)

## Catatan Deployment

- Pastikan `APP_ENV` di `config/config.php` bernilai `production` pada server live.
- Pastikan `display_errors` tidak aktif di production.
- Pastikan `.htaccess` aktif dan Apache mengizinkan `AllowOverride`.
- Pastikan folder `uploads/` tidak dapat mengeksekusi script server-side.
- Sesuaikan `BASE_URL` jika aplikasi dipasang di subfolder berbeda.

## Lisensi

Project ini dibuat untuk kebutuhan pembelajaran dan pengembangan sistem pendaftaran magang. Silakan gunakan, modifikasi, dan kembangkan sesuai kebutuhan.
