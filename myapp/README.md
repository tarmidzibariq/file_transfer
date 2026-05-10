# File Transfer API (Laravel)

Backend REST untuk **manajemen berkas per pengguna**: registrasi/login, upload, daftar, download, hapus, dan visibilitas publik/privat. API berada di prefix **`/api`**, autentikasi dengan **Laravel Sanctum** (token Bearer).

**Stack:** PHP ^8.3, Laravel ^13, Sanctum ^4.

---

## Prasyarat

- PHP 8.3 atau lebih baru (ekstensi umum Laravel: `openssl`, `pdo`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, dll.)
- [Composer](https://getcomposer.org/)
- Node.js & npm (opsional, untuk aset front-end / `composer run dev`)

---

## Instalasi

Clone atau masuk ke folder proyek, lalu pasang dependensi PHP:

```bash
cd myapp
composer install
```

Salin environment dan generate key aplikasi:

```bash
cp .env.example .env
php artisan key:generate
```

Sesuaikan **database** di `.env`. Default contoh memakai SQLite (`DB_CONNECTION=sqlite`). Jika memakai SQLite, pastikan file database ada:

```bash
touch database/database.sqlite
```

Jalankan migrasi (tabel `users`, `files`, `personal_access_tokens`, dll.):

```bash
php artisan migrate
```

Tautkan storage publik agar file yang di-upload bisa diakses/diunduh dari disk `public`:

```bash
php artisan storage:link
```

---

## Tahapan ringkas (alur kerja)

| Urutan | Perintah / tindakan | Tujuan |
|--------|---------------------|--------|
| 1 | `composer install` | Dependensi PHP |
| 2 | `cp .env.example .env` + `php artisan key:generate` | Konfigurasi & enkripsi |
| 3 | Atur `DB_*` di `.env` + buat DB (mis. `touch database/database.sqlite`) | Persistensi data |
| 4 | `php artisan migrate` | Skema tabel |
| 5 | `php artisan storage:link` | Upload ke `storage/app/public` terhubung ke `public/storage` |
| 6 | `php artisan serve` | Server API lokal (biasanya `http://127.0.0.1:8000`) |

Untuk pengembangan penuh (server + queue + log + Vite), proyek ini menyediakan skrip:

```bash
composer run dev
```

---

## Analisa (arsitektur & alur)

### Modul utama

- **`routes/api.php`** — definisi endpoint; grup `auth:sanctum` membungkus operasi file selain download publik.
- **`App\Http\Controllers\API\AuthController`** — register, login, logout; token personal access dibuat per sesi.
- **`App\Http\Controllers\API\FileController`** — CRUD logika file; upload ke disk `public` di folder `files/`.
- **`App\Models\File`** — model dengan **UUID** primary key; relasi ke `User`.
- **`App\Http\Resources\FileResource`** — pembungkus respons JSON seragam: `success`, `message`, `data`.

### Alur autentikasi

1. Client memanggil `POST /api/register` atau `POST /api/login` dengan JSON.
2. Server mengembalikan `token` (plain text sekali); disimpan client.
3. Request selanjutnya ke route terproteksi menyertakan header `Authorization: Bearer {token}`.
4. `POST /api/logout` menghapus token saat ini di server.

### Alur file

1. User terautentikasi mengunggah `multipart` ke `POST /api/files/upload` (maks. 10 MB per validasi controller).
2. Metadata disimpan di tabel `files`; berkas fisik di `storage/app/public/files/...`.
3. Download oleh pemilik: `GET /api/files/download/{id}` (perlu token).
4. Jika `is_public` true, siapa pun bisa `GET /api/public/files/{id}/download` tanpa token.
5. `PUT /api/files/{id}/toggle-visibility` membalik flag publik; `DELETE /api/files/{id}` menghapus record dan file di storage.

### Catatan desain & operasional

- **Keamanan:** password pada register memakai aturan `Password` Laravel (panjang, huruf besar/kecil, angka, simbol). Token Sanctum untuk API stateless.
- **Publik:** endpoint download publik hanya boleh dipakai untuk `is_public = true`; jangan expose ID jika ingin privasi lebih kuat (pertimbangkan URL bertanda/tanda tangan di iterasi berikutnya).
- **Validasi:** input gagal mengembalikan **422** dengan format error bawaan Laravel.
- **Health:** aplikasi mendaftarkan route health Laravel di **`/up`** (bukan di bawah `/api`).

---

## Dokumentasi API

Struktur penulisan di bagian ini mengikuti **pola dokumentasi REST yang dipakai di kelas Dicoding** (Back-End): setiap endpoint memiliki path, deskripsi, spesifikasi request (header & body), lalu contoh respons per kode status. Contoh URL memakai server lokal; ganti host/port jika berbeda.

Referensi gaya serupa di ekosistem Dicoding: [Notes API Documentation](https://notes-api.dicoding.dev/v1).

### Mengenai API ini

API mengelola **pengguna** dan **berkas** milik pengguna. Data dipertukarkan dengan format **JSON** (kecuali endpoint unduh file). Komunikasi memakai protokol **HTTP/HTTPS** dan mengikuti konsep **stateless**: identitas pengguna dibuktikan dengan **token** di setiap permintaan yang dilindungi.

### Base URL

| Lingkungan | Base URL |
|------------|----------|
| Lokal (`php artisan serve`, port default) | `http://127.0.0.1:8000/api` |

Seluruh path di bawah adalah **relatif** terhadap Base URL di atas (misalnya `/register` → `http://127.0.0.1:8000/api/register`).

### Autentikasi (Bearer Token)

Endpoint yang membutuhkan login mengirim header:

| Header | Nilai |
|--------|--------|
| `Authorization` | `Bearer <access_token>` |
| `Accept` | `application/json` (disarankan) |

Token didapat dari respons **Register** atau **Login** (field `data.token`). Di materi Dicoding untuk API berbasis JWT, token sering bernama `accessToken` di dalam `data`; di proyek ini nama field-nya **`token`** (Laravel Sanctum).

### Format respons JSON

Pola umum (endpoint selain unduh file):

| Field | Tipe | Keterangan |
|-------|------|------------|
| `success` | boolean | `true` jika permintaan berhasil diproses sesuai skenario endpoint. |
| `message` | string | Pesan singkat untuk developer atau pengguna. |
| `data` | object, array, atau `null` | Muatan utama; bentuknya tergantung endpoint. |

Di beberapa proyek contoh Dicoding (misalnya Notes API), indikator keberhasilan memakai field **`status`** bertipe string (`"success"`). Di API ini setara secara konsep dengan **`success: true`** — beda penamaan, sama fungsi dokumentasinya.

Respons **gagal validasi** (misalnya email sudah terdaftar) mengikuti format error bawaan Laravel (**HTTP 422**) dengan detail per field.

### Ringkasan endpoint

| Method | Path | Autentikasi | Fungsi |
|--------|------|-------------|--------|
| `POST` | `/register` | Tidak | Mendaftarkan pengguna baru |
| `POST` | `/login` | Tidak | Masuk dan mendapatkan token |
| `POST` | `/logout` | Bearer | Menghapus token aktif |
| `GET` | `/files` | Bearer | Mendapatkan daftar file milik pengguna |
| `POST` | `/files/upload` | Bearer | Mengunggah file |
| `GET` | `/files/download/{id}` | Bearer | Mengunduh file (pemilik) |
| `GET` | `/public/files/{id}/download` | Tidak | Mengunduh file bersifat publik |
| `DELETE` | `/files/{id}` | Bearer | Menghapus file |
| `PUT` | `/files/{id}/toggle-visibility` | Bearer | Mengubah publik ↔ privat |

Path parameter `{id}` pada resource file adalah **UUID** string.

---

### Registrasi pengguna

**Path:** `/register`  
**URL lengkap (lokal):** `http://127.0.0.1:8000/api/register`  
**Method:** `POST`  
**Deskripsi:** Membuat akun pengguna baru. Jika berhasil, respons memuat data pengguna dan token untuk akses endpoint terproteksi.

**Request header**

| Header | Wajib | Nilai |
|--------|--------|--------|
| `Content-Type` | Ya | `application/json` |
| `Accept` | Disarankan | `application/json` |

**Request body** (JSON)

| Parameter | Tipe | Wajib | Deskripsi |
|-----------|------|--------|-----------|
| `name` | string | Ya | Nama pengguna |
| `email` | string | Ya | Alamat email; harus unik |
| `password` | string | Ya | Minimal 6 karakter; harus memuat huruf besar, huruf kecil, angka, dan simbol |

**Response `201 Created`**

```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "Nama Pengguna",
      "email": "email@contoh.com",
      "email_verified_at": null,
      "created_at": "...",
      "updated_at": "..."
    },
    "token": "1|contohPlainTextTokenSanctum"
  }
}
```

**Response `422 Unprocessable Entity`** — contoh: email sudah digunakan atau password tidak memenuhi aturan (struktur detail mengikuti validasi Laravel).

---

### Login pengguna

**Path:** `/login`  
**URL lengkap (lokal):** `http://127.0.0.1:8000/api/login`  
**Method:** `POST`  
**Deskripsi:** Memverifikasi kredensial dan mengembalikan token akses.

**Request header**

| Header | Wajib | Nilai |
|--------|--------|--------|
| `Content-Type` | Ya | `application/json` |
| `Accept` | Disarankan | `application/json` |

**Request body** (JSON)

| Parameter | Tipe | Wajib | Deskripsi |
|-----------|------|--------|-----------|
| `email` | string | Ya | Email terdaftar |
| `password` | string | Ya | Password akun |

**Response `200 OK`**

```json
{
  "success": true,
  "message": "User logged in successfully",
  "data": {
    "user": { "id": 1, "name": "...", "email": "...", "created_at": "...", "updated_at": "..." },
    "token": "2|contohPlainTextTokenSanctum"
  }
}
```

**Response `401 Unauthorized`**

```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

---

### Logout pengguna

**Path:** `/logout`  
**URL lengkap (lokal):** `http://127.0.0.1:8000/api/logout`  
**Method:** `POST`  
**Deskripsi:** Menghapus token yang sedang dipakai sehingga tidak dapat digunakan lagi.

**Request header**

| Header | Wajib | Nilai |
|--------|--------|--------|
| `Authorization` | Ya | `Bearer <access_token>` |
| `Accept` | Disarankan | `application/json` |

**Request body:** tidak memerlukan body.

**Response `200 OK`** — hanya field berikut (tanpa `data`):

```json
{
  "success": true,
  "message": "User logged out successfully"
}
```

---

### Mendapatkan daftar file

**Path:** `/files`  
**URL lengkap (lokal):** `http://127.0.0.1:8000/api/files`  
**Method:** `GET`  
**Deskripsi:** Mengembalikan seluruh file yang dimiliki oleh pengguna yang sedang login, diurutkan dari yang terbaru.

**Request header**

| Header | Wajib | Nilai |
|--------|--------|--------|
| `Authorization` | Ya | `Bearer <access_token>` |
| `Accept` | Disarankan | `application/json` |

**Response `200 OK`**

```json
{
  "success": true,
  "message": "Files retrieved successfully",
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "user_id": 1,
      "original_name": "dokumen.pdf",
      "file_name": "abc123.pdf",
      "file_size": 102400,
      "mime_type": "application/pdf",
      "path": "files/abc123.pdf",
      "is_public": false,
      "created_at": "...",
      "updated_at": "..."
    }
  ]
}
```

---

### Mengunggah file

**Path:** `/files/upload`  
**URL lengkap (lokal):** `http://127.0.0.1:8000/api/files/upload`  
**Method:** `POST`  
**Deskripsi:** Mengunggah satu berkas; ukuran maksimum sesuai validasi server (**10 MB**).

**Request header**

| Header | Wajib | Nilai |
|--------|--------|--------|
| `Authorization` | Ya | `Bearer <access_token>` |
| `Content-Type` | Ya | `multipart/form-data` (biasanya diatur otomatis oleh klien) |
| `Accept` | Disarankan | `application/json` |

**Request body** (`multipart/form-data`)

| Parameter | Tipe | Wajib | Deskripsi |
|-----------|------|--------|-----------|
| `file` | file | Ya | Berkas yang diunggah |
| `is_public` | boolean | Tidak | Default `false` jika tidak dikirim |

**Response `200 OK`** — `data` berisi satu objek file yang baru dibuat (struktur kolom sama seperti elemen pada daftar file di atas).

---

### Mengunduh file (pemilik)

**Path:** `/files/download/{id}`  
**URL lengkap (lokal):** `http://127.0.0.1:8000/api/files/download/{id}`  
**Method:** `GET`  
**Deskripsi:** Mengunduh berkas milik pengguna yang sedang login. `{id}` adalah UUID file.

**Request header**

| Header | Wajib | Nilai |
|--------|--------|--------|
| `Authorization` | Ya | `Bearer <access_token>` |

**Response `200 OK`:** body berupa **binary file** (bukan JSON), dengan header unduhan sesuai nama asli file.

**Response `404 Not Found`:** file tidak ada atau bukan milik pengguna.

---

### Mengunduh file publik

**Path:** `/public/files/{id}/download`  
**URL lengkap (lokal):** `http://127.0.0.1:8000/api/public/files/{id}/download`  
**Method:** `GET`  
**Deskripsi:** Mengunduh file yang **`is_public`** bernilai `true`. Tidak memerlukan token.

**Request header:** tidak wajib `Authorization`.

**Response `200 OK`:** binary file.  
**Response `404 Not Found`:** file tidak ada atau tidak bersifat publik.

---

### Menghapus file

**Path:** `/files/{id}`  
**URL lengkap (lokal):** `http://127.0.0.1:8000/api/files/{id}`  
**Method:** `DELETE`  
**Deskripsi:** Menghapus metadata di database dan berkas di penyimpanan.

**Request header**

| Header | Wajib | Nilai |
|--------|--------|--------|
| `Authorization` | Ya | `Bearer <access_token>` |
| `Accept` | Disarankan | `application/json` |

**Response `200 OK`**

```json
{
  "success": true,
  "message": "File deleted successfully",
  "data": null
}
```

---

### Mengubah visibilitas file (publik / privat)

**Path:** `/files/{id}/toggle-visibility`  
**URL lengkap (lokal):** `http://127.0.0.1:8000/api/files/{id}/toggle-visibility`  
**Method:** `PUT`  
**Deskripsi:** Membalik nilai `is_public` pada file milik pengguna.

**Request header**

| Header | Wajib | Nilai |
|--------|--------|--------|
| `Authorization` | Ya | `Bearer <access_token>` |
| `Accept` | Disarankan | `application/json` |

**Request body:** tidak diperlukan.

**Response `200 OK`** — `data` berisi objek file yang sudah diperbarui.

---

### Kode status HTTP (ringkas)

| Kode | Penggunaan umum pada API ini |
|------|------------------------------|
| `200` | Permintaan berhasil (login, daftar file, upload, hapus, toggle, logout) |
| `201` | Sumber daya baru dibuat (register) |
| `401` | Login gagal atau token tidak valid / tidak dikirim |
| `404` | Resource tidak ditemukan |
| `422` | Validasi input gagal |
| `500` | Kesalahan server (tidak didokumentasikan per kasus) |

---

## Lisensi

Proyek skeleton mengikuti lisensi **MIT** (Laravel). Isi aplikasi Anda dapat ditambahkan di sini sesuai kebijakan tim.
