# Scraper Configuration Guide

## Google Custom Search API

1. Buka Google Cloud Console dan buat project baru.
2. Aktifkan **Custom Search API**.
3. Buat API key dari menu Credentials.
4. Buka Programmable Search Engine, buat search engine baru, lalu salin **Search engine ID (CX)**.
5. Edit source `Google Search - Magang 2024` di panel admin dan isi:
   - `api_key`
   - `cx`
   - `query`
   - `date_restrict`, misalnya `d7`

Free tier Google Custom Search biasanya cukup untuk testing ringan, tetapi tetap perhatikan quota harian.

## Telegram Bot Token

1. Buka Telegram dan cari `@BotFather`.
2. Jalankan `/newbot`, ikuti instruksi nama bot dan username.
3. Salin token bot.
4. Untuk channel publik, cara paling stabil di project ini adalah membaca halaman publik `https://t.me/s/nama_channel`.
5. Isi config source Telegram:

```json
{
  "method": "web_scrape",
  "keyword_filter": ["magang", "internship", "deadline"]
}
```

Jika ingin memakai Bot API, isi:

```json
{
  "method": "bot_api",
  "bot_token": "TOKEN_BOT",
  "channel_username": "@namaChannel",
  "keyword_filter": ["magang", "internship", "deadline"]
}
```

## Menemukan CSS Selector Website

1. Buka halaman lowongan di browser.
2. Klik kanan pada kartu lowongan, pilih **Inspect**.
3. Cari container terluar untuk satu lowongan, gunakan sebagai `job_container`.
4. Cari selector relatif untuk:
   - `title`
   - `company`
   - `location`
   - `url`
   - `deadline`
5. Test manual:

```bash
php scraper/run_scraper.php --source=1
```

## Catatan Legal

Gunakan sumber publik, RSS, Telegram channel publik, dan API resmi. Hindari scraping platform yang melarang scraping dalam Terms of Service.
