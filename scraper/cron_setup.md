# Setup Cron Job

Edit crontab:

```bash
crontab -e
```

Jalankan scraper setiap 6 jam:

```bash
0 */6 * * * /usr/bin/php /var/www/html/internship-system/scraper/run_scraper.php --all >> /var/log/cariintern_scraper.log 2>&1
```

Khusus Telegram, jika ingin lebih sering:

```bash
0 */2 * * * /usr/bin/php /var/www/html/internship-system/scraper/run_scraper.php --source=4 >> /var/log/cariintern_scraper.log 2>&1
```

Verifikasi cron berjalan:

```bash
tail -f /var/log/cariintern_scraper.log
```

Environment lokal seperti Laragon/XAMPP biasanya tidak punya cron. Gunakan halaman `admin/trigger_scraper.php` atau tombol trigger di panel scraper admin.
