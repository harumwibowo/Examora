# Panduan Deploy Examora

Examora adalah aplikasi PHP native tanpa Composer dan tanpa build step. Cukup
salin folder aplikasi ke web server, siapkan satu database MySQL, lalu ikuti
wizard instalasi di browser.

Panduan ini membahas empat skenario: shared hosting (cPanel), VPS Apache, VPS
Nginx, dan server pengembangan `php -S`.

---

## 1. Kebutuhan server

| Komponen | Keterangan |
|---|---|
| PHP | **8.2 atau lebih baru** (disarankan 8.3) |
| Ekstensi wajib | `pdo_mysql`, `mbstring`, `openssl`, `session`, `json` |
| Ekstensi opsional | `curl` (unduh soal PDF Google Drive), `gd` (ubah ukuran logo) |
| Database | MySQL 5.7+ atau MariaDB 10.4+ |
| Web server | Apache (`.htaccess` aktif), Nginx + PHP-FPM, atau `php -S` untuk pengembangan |

Fitur yang tidak memakai ekstensi opsional tetap berjalan; hanya soal PDF
Google Drive dan unggah logo yang membutuhkan `curl`/`gd`.

---

## 2. Unduh kode

```bash
git clone https://github.com/harumwibowo/Examora.git examora
```

Atau unduh ZIP dari halaman GitHub lalu ekstrak. Folder hasil ekstrak adalah
root aplikasi (berisi `index.php`, `app/`, `views/`, dst).

Yang **tidak** ada di repo dan dibuat otomatis saat instalasi:

- `config/config.php` — kredensial database dan `app_key`
- `install.lock` — penanda instalasi selesai
- isi `storage/` — log, cache, dan file sesi
- logo sekolah di `assets/uploads/`

Jangan menimpa atau menghapus empat hal di atas saat memperbarui aplikasi.

---

## 3. Izin folder

Aplikasi perlu menulis ke folder berikut:

| Folder | Kegunaan |
|---|---|
| `config/` | Menyimpan `config.php` saat instalasi |
| `storage/` | Log, cache (rate limit, favicon, PDF), dan file sesi |
| `storage/cache/`, `storage/logs/`, `storage/sessions/` | Dibuat otomatis bila belum ada |
| `assets/uploads/` | Menyimpan logo sekolah yang diunggah |

Linux (pemilik user web server, mis. `www-data`):

```bash
sudo chown -R www-data:www-data /var/www/examora/config /var/www/examora/storage /var/www/examora/assets/uploads
sudo find /var/www/examora/config /var/www/examora/storage /var/www/examora/assets/uploads -type d -exec chmod 775 {} \;
```

cPanel: File Manager → klik kanan folder → **Permissions** → `775` (atau `755`
bila hosting tidak mengizinkan group write). Halaman instalasi akan menampilkan
daftar folder yang belum dapat ditulis.

---

## 4. Shared hosting (cPanel)

1. **Unggah** ZIP repo ke `public_html` (atau subfolder, mis. `public_html/examora`)
   lewat File Manager, lalu **Extract**.
2. **Buat database**: menu **MySQL Databases** → buat database dan user, lalu
   tambahkan user ke database dengan **All Privileges**. Catat host, nama
   database, user, dan password. Di cPanel, nama database/user biasanya
   berawalan `namacpanel_`.
3. **Atur permission** `config/`, `storage/`, `assets/uploads/` menjadi `775`.
4. **Buka** `https://domain-sekolah.sch.id/` (atau `/examora/`). Wizard
   instalasi akan muncul karena `install.lock` belum ada.
5. **Isi wizard**:
   - Host database biasanya `localhost` (bukan `127.0.0.1`).
   - Nama database/user **lengkap dengan awalan cPanel**.
   - Nama sekolah dan akun superadmin.
6. **Aktifkan HTTPS** (AutoSSL/Let's Encrypt di cPanel). Cookie login otomatis
   memakai flag `Secure` saat HTTPS aktif.
7. Login melalui menu **Guru / Admin** memakai akun superadmin tadi.

> Jika akun database tidak boleh membuat database, langkah 2 wajib dilakukan
> lebih dulu. Wizard akan memberi pesan yang jelas bila database belum ada.

---

## 5. VPS dengan Apache

Contoh Ubuntu 22.04/24.04:

```bash
sudo apt update
sudo apt install -y apache2 php8.3 libapache2-mod-php8.3 php8.3-mysql \
  php8.3-mbstring php8.3-curl php8.3-gd unzip git
```

```bash
sudo git clone https://github.com/harumwibowo/Examora.git /var/www/examora
sudo chown -R www-data:www-data /var/www/examora
sudo find /var/www/examora -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/examora/config /var/www/examora/storage /var/www/examora/assets/uploads
```

Buat database:

```sql
CREATE DATABASE examora CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'examora'@'localhost' IDENTIFIED BY 'password-kuat';
GRANT ALL PRIVILEGES ON examora.* TO 'examora'@'localhost';
FLUSH PRIVILEGES;
```

Virtual host (`/etc/apache2/sites-available/examora.conf`):

```apache
<VirtualHost *:80>
    ServerName examora.sekolah.sch.id
    DocumentRoot /var/www/examora

    <Directory /var/www/examora>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/examora-error.log
    CustomLog ${APACHE_LOG_DIR}/examora-access.log combined
</VirtualHost>
```

```bash
sudo a2enmod rewrite headers expires
sudo a2ensite examora
sudo systemctl reload apache2
```

**Penting:** `AllowOverride All` wajib aktif agar `.htaccess` memblokir folder
`app/`, `config/`, `database/`, `storage/`, dan `views/`.

HTTPS:

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d examora.sekolah.sch.id
```

---

## 6. VPS dengan Nginx + PHP-FPM

Nginx **tidak membaca `.htaccess`**. Tanpa aturan tambahan, folder internal
(termasuk `storage/logs`, `storage/sessions`, dan `database/schema.sql`) dapat
diakses dari browser. Gunakan contoh konfigurasi di
[`docs/nginx-example.conf`](nginx-example.conf).

```bash
sudo apt install -y nginx php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-curl php8.3-gd git
sudo git clone https://github.com/harumwibowo/Examora.git /var/www/examora
sudo chown -R www-data:www-data /var/www/examora
sudo chmod -R 775 /var/www/examora/config /var/www/examora/storage /var/www/examora/assets/uploads
```

Salin contoh konfigurasi, ganti `server_name` dan versi PHP-FPM bila perlu:

```bash
sudo cp /var/www/examora/docs/nginx-example.conf /etc/nginx/sites-available/examora
sudo ln -s /etc/nginx/sites-available/examora /etc/nginx/sites-enabled/examora
sudo nginx -t && sudo systemctl reload nginx
```

HTTPS: `sudo certbot --nginx -d examora.sekolah.sch.id`.

Verifikasi bahwa proteksi bekerja — ketiga URL ini harus menghasilkan **403/404**,
bukan isi file:

```
https://domain/storage/logs/error.log
https://domain/database/schema.sql
https://domain/config/config.php
```

---

## 7. Server pengembangan (`php -S`)

Hanya untuk mencoba di komputer sendiri:

```bash
php -S 127.0.0.1:8080 -t examora
```

Server bawaan PHP **tidak membaca `.htaccess`**, sehingga folder internal tidak
terlindungi. Jangan pakai cara ini untuk server yang diakses siswa.

---

## 8. Setelah instalasi

- [ ] `config/config.php` dan `install.lock` sudah dibuat; wizard tertutup.
- [ ] `https://domain/config/config.php` tidak bisa diunduh.
- [ ] HTTPS aktif dan sertifikat valid.
- [ ] Login superadmin berhasil; password kuat dan tidak dibagikan.
- [ ] Menu **Pengaturan → Database** diuji: unduh backup dan simpan di tempat aman.
- [ ] Batas login siswa, pengawasan ujian, dan identitas sekolah diatur.
- [ ] Zona waktu server konsisten (aplikasi memaksa `Asia/Jakarta`).
- [ ] Backup rutin: file `.sql` dari menu Database + salinan folder `assets/uploads/`.

---

## 9. Memperbarui aplikasi

Simpan dulu backup database, lalu:

```bash
cd /var/www/examora
sudo git pull
```

Jangan hapus `config/config.php`, `install.lock`, isi `storage/`, dan
`assets/uploads/`. Migrasi skema ringan berjalan otomatis saat halaman pertama
dibuka.

---

## 10. Troubleshooting

| Gejala | Penyebab umum | Solusi |
|---|---|---|
| Halaman putih / error 500 | Ekstensi PHP kurang, folder tidak writable | Lihat `storage/logs/php-error.log` dan log web server |
| “Folder config/ tidak dapat ditulis” | Permission salah | `chmod 775 config`, pastikan pemilik = user web server |
| “User atau password database ditolak” | Kredensial salah / host salah | cPanel biasanya memakai `localhost`, nama DB berawalan akun cPanel |
| “Database … belum ada dan akun tidak berhak membuatnya” | User DB tanpa izin `CREATE` | Buat database lewat panel hosting, lalu isi nama yang sama |
| Wizard muncul lagi | `install.lock` terhapus | Buat ulang file `install.lock` (isi bebas) |
| Semua siswa ter-logout sendiri | Folder sesi tidak writable / dibersihkan hosting | Pastikan `storage/sessions` writable; aplikasi memakai folder sendiri |
| Soal PDF Google Drive gagal | `curl` tidak aktif atau file tidak dibagikan publik | Aktifkan `curl`, set berbagi “Siapa saja yang memiliki link” |
| Unggah logo gagal | `gd` tidak aktif atau folder tidak writable | Aktifkan `gd`, beri izin tulis `assets/uploads/` |
| Aset CSS/JS 404 di Nginx | `try_files`/root salah | Periksa `root` dan blok `location /` pada contoh konfigurasi |
| `.htaccess` diabaikan (Apache) | `AllowOverride None` | Ubah virtual host menjadi `AllowOverride All` |

Bila tetap gagal, sertakan isi `storage/logs/php-error.log` (hapus data sensitif)
saat melaporkan masalah.
