# Project API WhatsApp — Registrasi dengan Fonnte

Aplikasi registrasi sederhana menggunakan PHP native (PDO), MySQL/MariaDB, HTML5, CSS3, JavaScript Fetch API, dan layanan WhatsApp Fonnte. Data akun tetap disimpan meskipun layanan Fonnte sedang gagal; setiap percobaan pengiriman dicatat di `log_whatsapp`.

## Struktur proyek

```text
project-api-fonnte/
├── config/
│   ├── database.php
│   └── fonnte.php
├── functions/
│   └── fonnte.php
├── api/
│   └── register.php
├── database.sql
├── README.md
└── index.html
```

## Deskripsi dan alur sistem

1. Pengguna mengisi formulir di `index.html`; browser mengirim JSON dengan Fetch API melalui HTTP POST.
2. `api/register.php` memvalidasi data, menormalisasi nomor WhatsApp, dan memeriksa email serta nomor yang sudah dipakai.
3. Password di-hash dengan BCRYPT. Data akun disimpan dengan prepared statement dan constraint unik di database.
4. Setelah insert akun berhasil, server membentuk pesan personal dan memanggil `kirimWhatsApp()` ke Fonnte menggunakan cURL.
5. Hasil pengiriman (berhasil atau gagal) disimpan di `log_whatsapp`. Kegagalan Fonnte tidak membatalkan registrasi.
6. API mengirim respons JSON yang menyatakan status registrasi dan pengiriman secara terpisah. Antarmuka menampilkan respons menggunakan `textContent`.

## Cara menjalankan

### 1. Persiapan database

1. Jalankan Apache dan MySQL dari XAMPP.
2. Buka phpMyAdmin, lalu import `database.sql`; skrip membuat database `api_wa_fonnte` dan kedua tabel.
3. Pastikan akun MySQL yang digunakan dapat mengakses database. Nilai default untuk XAMPP adalah host `127.0.0.1`, user `root`, dan password kosong. Untuk server lain, atur environment variable `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASS`.

### 2. Konfigurasi Fonnte

1. Buat/masuk ke akun Fonnte, hubungkan perangkat WhatsApp sesuai instruksi penyedia, lalu salin API token dari dashboard.
2. Atur token sebagai environment variable server bernama `FONNTE_API_TOKEN` (di Windows, tambahkan melalui pengaturan Environment Variables untuk akun yang menjalankan Apache, lalu mulai ulang XAMPP/Apache). Jangan menaruh token di `index.html`, JavaScript, repository publik, atau URL.
3. Endpoint yang digunakan adalah `https://api.fonnte.com/send`. Jika token tidak tersedia atau cURL gagal, akun tetap tersimpan dan catatan pengiriman berstatus `failed`.
4. Pastikan ekstensi PHP cURL, PDO MySQL, dan mbstring aktif pada konfigurasi PHP XAMPP.

### 3. Menjalankan aplikasi

Letakkan folder proyek di `C:\xampp\htdocs\project-api-fonnte`, kemudian akses:

```text
http://localhost/project-api-fonnte/
```

Jalankan pengujian API melalui halaman tersebut atau kirim POST JSON ke `api/register.php`. Endpoint hanya menerima metode POST dan mengembalikan JSON. Hindari membuka `index.html` langsung melalui skema `file://`, karena Fetch API harus mengakses endpoint melalui Apache.

Dashboard admin untuk melihat data user dan log pengiriman tersedia di:

```text
http://localhost/project-api-fonnte/dashboard/
```

### 4. Fungsi normalisasi dan pengiriman

`formatNomorWA($nomor)` menghapus karakter selain digit, mengubah awalan `08...` menjadi `628...`, dan juga menerima nomor yang diawali `8...` tanpa kode negara. Nomor disimpan dalam bentuk kanonis yang sama agar pemeriksaan duplikasi konsisten. Validasi endpoint membatasi nomor ke pola nomor seluler Indonesia.

`kirimWhatsApp($nomor, $pesan)` melakukan HTTP POST multipart dengan target dan isi pesan, membawa token dalam header `Authorization`, serta menetapkan batas waktu koneksi 10 detik dan total 30 detik. Fungsi melaporkan error koneksi, kode HTTP, dan respons API agar hasilnya dapat dicatat.

### 5. Langkah kerja implementasi

1. Buat database dan tabel dengan indeks unik pada email dan nomor WhatsApp.
2. Siapkan koneksi PDO dengan charset `utf8mb4`, exception mode, dan prepared statement native.
3. Simpan konfigurasi token hanya di environment server dan implementasikan helper normalisasi serta cURL.
4. Validasi input di server; cek keunikan sebagai umpan balik awal, tetapi tetap andalkan indeks unik untuk mencegah kondisi balapan.
5. Hash password, simpan akun, lalu coba kirim pesan dan catat hasilnya. Jangan menggulung balik akun karena kegagalan layanan eksternal.
6. Bangun formulir responsif. Render hasil API sebagai teks, bukan HTML, agar input tidak dieksekusi sebagai markup.
7. Uji seluruh enam skenario pada tabel berikut, termasuk mematikan layanan/token Fonnte secara terkontrol.

## Pencegahan bug dan keamanan

- **SQL Injection:** seluruh input database disalurkan melalui PDO prepared statements; jangan merangkai nilai input ke SQL.
- **XSS:** respons dan data pengguna di browser ditampilkan dengan `textContent`, tidak menggunakan `innerHTML`. Validasi server tetap wajib walau formulir memiliki validasi HTML.
- **Kebocoran token API:** token dibaca dari environment server, hanya dikirim dalam header server-ke-server, dan tidak dimasukkan ke respons browser maupun log aplikasi.
- **cURL timeout:** timeout koneksi dan request membatasi waktu tunggu. Penanganan error cURL dicatat; request yang gagal tidak menghapus data akun.
- **Decoupling kegagalan:** penyimpanan akun dilakukan sebelum pengiriman; status WA dicatat terpisah. Dengan demikian layanan Fonnte down tidak membuat registrasi gagal.
- **Password:** hanya hash BCRYPT yang disimpan; password asli tidak dikembalikan atau dicatat. Gunakan HTTPS di luar lingkungan lokal.
- **Duplikasi:** validasi SELECT memberikan pesan yang ramah, sedangkan constraint `UNIQUE` adalah perlindungan terhadap dua request serentak.
- **Operasional:** batasi laju endpoint (rate limiting), gunakan kredensial database khusus dengan hak minimum, lindungi file konfigurasi, dan jangan menampilkan stack trace kepada pengguna di produksi.

## Skenario pengujian dan hasil yang diharapkan

| No. | Skenario | Langkah uji | Hasil yang diharapkan |
|---:|---|---|---|
| 1 | Registrasi normal (data valid) | Kirim nama, email baru, nomor `081234567890`, alamat, dan password valid; Fonnte aktif. | HTTP 201; satu baris pengguna tersimpan dengan nomor `6281234567890` dan hash BCRYPT; pesan terkirim; satu log berstatus `sent`. |
| 2 | Email sudah terdaftar | Kirim email yang telah dimiliki akun lain dengan nomor baru. | HTTP 409; pesan email sudah terdaftar; tidak ada akun/log baru dan Fonnte tidak dipanggil. |
| 3 | Nomor HP sudah terdaftar | Kirim nomor yang telah dimiliki akun lain dengan email baru. | HTTP 409; pesan nomor sudah terdaftar; tidak ada akun/log baru dan Fonnte tidak dipanggil. |
| 4 | Input nomor format `08xxx` | Daftar dengan nomor Indonesia yang valid berawalan `08`. | Nomor dinormalisasi menjadi `62...` dan bentuk kanonis yang sama disimpan serta dipakai untuk pengiriman/log. |
| 5 | Nomor tidak valid | Kirim nomor terlalu pendek, bukan nomor seluler Indonesia, atau kosong. | HTTP 422 dengan error pada `no_hp`; akun tidak dibuat dan Fonnte tidak dipanggil. |
| 6 | Fonnte API down/tidak tersedia | Gunakan token kosong/tidak valid atau blokir koneksi Fonnte, lalu kirim data valid. | HTTP 201 dan akun tetap tersimpan; respons menyatakan WA gagal; log tersimpan berstatus `failed` dengan detail respons/error. |

Pengujian integrasi terhadap Fonnte memerlukan token aktif dan perangkat WhatsApp yang terhubung. Skenario Fonnte down sebaiknya dilakukan pada lingkungan uji.

## Jawaban 10 pertanyaan analisis

1. **Apa itu API?** API (Application Programming Interface) adalah antarmuka dan aturan yang memungkinkan satu perangkat lunak meminta layanan atau bertukar data dengan perangkat lunak lain tanpa harus mengetahui detail implementasi internalnya.
2. **Apa fungsi API Fonnte pada proyek ini?** API Fonnte menjadi perantara server aplikasi untuk meneruskan pesan teks ke nomor WhatsApp tujuan melalui layanan WhatsApp yang disediakan Fonnte.
3. **Mengapa token API harus dirahasiakan?** Token berfungsi sebagai kredensial otorisasi. Pihak yang mendapatkannya dapat menyalahgunakan kuota atau identitas akun; karena itu token disimpan dan digunakan hanya di server, bukan di kode browser.
4. **Mengapa registrasi dan pengiriman WhatsApp dipisahkan?** Database adalah proses utama yang harus tetap tersedia ketika layanan eksternal terganggu. Pemisahan ini mencegah outage Fonnte menghapus atau menggagalkan akun yang telah dibuat.
5. **Apa tujuan normalisasi nomor WhatsApp?** Normalisasi menyamakan variasi penulisan nomor menjadi format internasional kanonis, misalnya `0812...` menjadi `62812...`. Ini membuat pencarian duplikat dan pengiriman ke API konsisten.
6. **Mengapa digunakan HTTP POST?** POST mengirimkan data sebagai isi request untuk diproses atau membuat resource, sesuai operasi registrasi dan pengiriman pesan. Data sensitif juga tidak ditempatkan pada URL seperti query string (namun HTTPS tetap diperlukan).
7. **Apa kegunaan PDO prepared statements?** Query dan nilai input dipisahkan sehingga input diperlakukan sebagai data, bukan bagian sintaks SQL. Ini mengurangi risiko SQL Injection sekaligus mendukung parameterisasi yang aman.
8. **Mengapa password harus di-hash?** Hash satu arah menghindari penyimpanan password asli. Jika database terbaca pihak lain, hash BCRYPT dengan salt tidak langsung mengungkap password; verifikasi dilakukan dengan `password_verify()`.
9. **Apa fungsi tabel `log_whatsapp`?** Tabel ini menyimpan tujuan, isi pesan, status, respons/error, dan waktu percobaan untuk audit dan diagnosis. Log menunjukkan hasil notifikasi secara terpisah dari status akun.
10. **Mengapa cURL diberi timeout dan bagaimana Fetch API bekerja?** Timeout membatasi resource dan waktu tunggu saat layanan eksternal lambat/tidak tersedia. Fetch API mengirim request secara asynchronous dan menerima respons tanpa memuat ulang halaman; kode tetap perlu memeriksa status HTTP dan menangani kegagalan jaringan.

## Respons API

- Sukses menyimpan akun: HTTP `201`, `success: true`; status WhatsApp berada pada `whatsapp.status` (`sent` atau `failed`).
- Input tidak valid: HTTP `422` dengan daftar `errors`.
- Email/nomor duplikat: HTTP `409`.
- Metode selain POST: HTTP `405`.
- Gangguan database: HTTP `500`; detail teknis hanya dicatat server-side.
