# Aplikasi Tanya Jawab (Q&A) - Kelompok 05

Aplikasi bersama untuk Tugas 1 (Pra-UTS) mata kuliah Komputasi Awan dan Terdistribusi. Pengunjung dapat mengajukan pertanyaan, pemilik laman menjawab, dan pertanyaan dapat di-upvote. Setiap data mencatat `route_id`, yaitu identitas server yang menerima data tersebut.

## Aplikasi yang dipilih

**Tanya Jawab (Q&A)**

| Fitur | Keterangan |
|---|---|
| Fungsi tulis | Form bertanya dan form menjawab |
| Fungsi baca | Daftar pertanyaan terbaru atau terpopuler beserta jawabannya |
| Fitur tambahan | Paginasi dan moderasi (menyembunyikan pertanyaan) |
| Upvote | Counter atomik: `UPDATE ... SET upvote = upvote + 1` |

## Anggota dan route

| Route | Nama | NIM |
|---|---|---|
| m0 (ketua) | Faizul 'Afiat | 103012400356 |
| m1 | Raynar Khalid Mahardika Sumarno | 103012430017 |
| m2 | Aryo Dwipa Maulana | 103012400081 |
| m3 | Zakiyah Nabilah Az Zahra | 103012430044 |
| m4 | Annisa Roslina Anwar | 103012430064 |

## Teknologi

- PHP 8.x (Apache, via XAMPP atau WAMP)
- PostgreSQL (akses lewat PDO dengan driver `pdo_pgsql`)
- HTML, CSS, dan JavaScript di sisi klien

## Struktur folder

Repo ini adalah isi folder `app/` pada personal page masing-masing anggota:

```
htdocs/personal/
├── index.php            <- personal page (individu, tidak ada di repo ini)
├── assets/              <- css, js, gambar (individu)
├── identity.php         <- identitas mahasiswa (lokal, tidak di repo)
├── config/
│   ├── .htaccess        <- isi: Require all denied
│   └── db.php           <- kredensial database (lokal, tidak di repo)
└── app/                 <- hasil git clone repo ini
    ├── index.php        <- halaman aplikasi bersama
    ├── lib/db.php       <- koneksi PDO
    ├── partials/served_by.php
    └── sql/schema.sql
```

Aturan struktur:
- Folder `app/` hanya berisi kode dari repo ini dan tidak diubah secara lokal. Perubahan dilakukan lewat commit ke repo.
- `identity.php` dan `config/db.php` berada di luar `app/`, sehingga tidak ikut ter-commit.
- Semua tautan memakai path relatif (misalnya `app/index.php`).

## Cara instalasi

### 1. Prasyarat

- XAMPP atau WAMP dengan PHP 8.x
- PostgreSQL (sudah termasuk pgAdmin)
- Git

Aktifkan ekstensi PostgreSQL di `php.ini` (hapus tanda `;` di depannya), lalu restart Apache:

```
extension=pdo_pgsql
extension=pgsql
```

MySQL pada XAMPP tidak perlu dinyalakan.

### 2. Buat struktur folder personal page

```
cd C:\xampp\htdocs
mkdir personal
cd personal
mkdir assets, config
```

### 3. Clone repo ke folder `app`

```
git clone https://github.com/<username>/<nama-repo>.git app
```

### 4. Buat database dan user

Masuk sebagai `postgres`:

```
psql -U postgres
```

Lalu jalankan (ganti `password_lokal` dengan password pilihan Anda):

```sql
CREATE DATABASE kelompok05_db ENCODING 'UTF8';
CREATE USER app_local WITH PASSWORD 'password_lokal';
\q
```

Nama database (`kelompok05_db`) dan nama user (`app_local`) harus sama di semua anggota. Password boleh berbeda.

### 5. Impor skema

Jalankan dari terminal biasa (bukan di dalam psql), di dalam folder `personal/app`:

```
psql -U postgres -d kelompok05_db -f sql/schema.sql
```

File ini membuat tabel `anggota`, `pertanyaan`, dan `jawaban`, mengisi data anggota, dan memberi hak akses minimum kepada `app_local`. Aman dijalankan berulang.

Cek hasilnya:

```
psql -U postgres -d kelompok05_db
\dt
SELECT * FROM anggota;
```

### 6. Buat file lokal (tidak ikut repo)

`personal/identity.php`:

```php
<?php
define('MEMBER_ROUTE', 'm0'); // m0 = ketua, m1..m4 = anggota
define('MEMBER_NAMA', 'Nama Mahasiswa');
define('MEMBER_NIM', '1030xxxxxxxx');
```

`personal/config/db.php`:

```php
<?php
return [
  'host' => '127.0.0.1',
  'port' => '5432',
  'name' => 'kelompok05_db',
  'user' => 'app_local',
  'pass' => 'password_lokal',
];
```

`personal/config/.htaccess`:

```
Require all denied
```

Pastikan `http://localhost/personal/config/db.php` menampilkan **403 Forbidden**.

### 7. Jalankan

- Nyalakan **Apache** di XAMPP Control Panel.
- Pastikan layanan PostgreSQL berstatus **Running** (`services.msc`).
- Buka `http://localhost/personal/` dan `http://localhost/personal/app/`.

## Skema database

| Tabel | Kolom utama |
|---|---|
| `anggota` | `route_id` (PK), `nim`, `nama` |
| `pertanyaan` | `id`, `route_id` (FK), `nama`, `isi`, `upvote`, `disembunyikan`, `created_at` |
| `jawaban` | `id`, `pertanyaan_id` (FK), `route_id` (FK), `isi`, `created_at` |

Skema lengkap ada di `sql/schema.sql`.

## Keamanan

- Semua query memakai PDO dengan prepared statement.
- Semua data yang ditampilkan melewati `htmlspecialchars()`.
- Validasi input dilakukan di sisi klien (JavaScript) dan di sisi server (PHP).
- Kode tidak berisi kredensial. Koneksi selalu membaca `config/db.php`.
- User database aplikasi (`app_local`) hanya memiliki hak `SELECT`, `INSERT`, `UPDATE`, dan `DELETE`.

## Alur kerja Git

1. Jangan push langsung ke `main`.
2. Setiap fitur dikerjakan di branch sendiri:
   ```
   git checkout -b fitur/nama-fitur
   git add .
   git commit -m "Deskripsi singkat"
   git push -u origin fitur/nama-fitur
   ```
3. Buat Pull Request di GitHub.
4. Ketua menggabungkan (merge) setelah di-review minimal satu anggota lain.
5. Setelah merge, semua anggota memperbarui folder `app/`:
   ```
   cd personal/app
   git checkout main
   git pull
   ```

## Pembagian tugas

| Anggota | Branch | Fitur |
|---|---|---|
| m0 | `fitur/db-inti` | `sql/schema.sql`, `lib/db.php`, README |
| _isi_ | `fitur/daftar-pertanyaan` | Daftar pertanyaan, paginasi, `partials/served_by.php` |
| _isi_ | `fitur/form-tanya` | Form bertanya, validasi JavaScript dan PHP |
| _isi_ | `fitur/jawaban` | Form jawab, tampilan jawaban, moderasi |
| _isi_ | `fitur/upvote` | Tombol upvote AJAX, endpoint upvote atomik |

## Uji keamanan sederhana

Masukkan teks berikut ke form pertanyaan. Keduanya harus tersimpan dan tampil sebagai teks biasa, tanpa error dan tanpa menjalankan skrip:

```
<script>alert(1)</script>
' OR '1'='1
```

## Catatan

Pada Tugas 1, setiap laptop memiliki database lokal sendiri, sehingga data di satu laptop belum terlihat di laptop lain. Setelah UTS, database lokal diganti satu database PostgreSQL terpusat di server ketua dengan mengubah isi `config/db.php`.