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

**Base URL lokal (contoh):** `http://127.0.0.1:8000/api`

Header untuk route terproteksi: `Authorization: Bearer {token}`

### Autentikasi

#### `POST /register`

Body: `application/json`

| Field | Wajib | Keterangan |
|-------|--------|------------|
| `name` | Ya | String |
| `email` | Ya | Unik |
| `password` | Ya | Min. 6 karakter, huruf besar/kecil, angka, simbol |

Respons sukses **201:**

```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": { "id": "...", "name": "...", "email": "..." },
    "token": "1|..."
  }
}
```

#### `POST /login`

Body: `application/json` — `email`, `password` (wajib). Respons **200** sama bentuknya dengan register. Gagal **401:** `{ "success": false, "message": "Invalid credentials" }`.

#### `POST /logout`

Header: Bearer token. Respons **200:** `{ "success": true, "message": "User logged out successfully" }`.

### File

Respons JSON umum (bukan download biner):

```json
{
  "success": true,
  "message": "...",
  "data": null
}
```

Objek file memakai **UUID** sebagai `id`. Field umum: `user_id`, `original_name`, `file_name`, `file_size`, `mime_type`, `path`, `is_public`, timestamps.

| Method | Path | Auth | Keterangan |
|--------|------|------|------------|
| `GET` | `/files` | Ya | Daftar file user, terbaru dulu |
| `POST` | `/files/upload` | Ya | `multipart/form-data`: `file` (wajib, max 10 MB), `is_public` (opsional, boolean) |
| `GET` | `/files/download/{id}` | Ya | Unduh berkas (pemilik) |
| `GET` | `/public/files/{id}/download` | Tidak | Unduh jika `is_public` |
| `DELETE` | `/files/{id}` | Ya | Hapus metadata + file di storage |
| `PUT` | `/files/{id}/toggle-visibility` | Ya | Toggle `is_public` |

---

## Lisensi

Proyek skeleton mengikuti lisensi **MIT** (Laravel). Isi aplikasi Anda dapat ditambahkan di sini sesuai kebijakan tim.
