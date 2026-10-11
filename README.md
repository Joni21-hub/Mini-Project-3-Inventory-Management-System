# Mini Project 3: Inventory Management System
> **Mata Kuliah:** Pemrograman Web (Pertemuan 4: Relational Database + Advanced PHP)  
> **Aplikasi:** Sistem Manajemen Persediaan Barang & Mutasi Gudang Terintegrasi (InventarisHub)

---

## 📌 1. Deskripsi Proyek
Aplikasi ini dikembangkan untuk mengelola persediaan barang secara komprehensif dengan integritas relasional, kepatuhan aturan bisnis (*business rules*), pencatatan audit mutasi (*audit trail*), keamanan tingkat tinggi (*prepared statements, validation, output escaping, password hashing*), dan otorisasi berbasis peran (*Role-Based Access Control* / RBAC).

---

## 🗄️ 2. Desain Database & Relasi (ERD)

Aplikasi menerapkan 3 jenis relasi database relasional sesuai standar materi Pertemuan 4:

1. **One-to-One (1:1):**
   - `users` &harr; `user_profiles`
   - Kunci relasi: `user_profiles.user_id` bertipe `UNIQUE` dan merujuk ke `users.id` dengan opsi `ON DELETE CASCADE`.
2. **One-to-Many (1:N):**
   - `categories` &harr; `products` (Satu kategori memiliki banyak produk). Foreign key `products.category_id` dengan `ON DELETE RESTRICT`.
   - `users` &harr; `stock_movements` (Satu user mencatat banyak transaksi mutasi).
   - `products` &harr; `stock_movements` (Satu produk memiliki banyak riwayat mutasi masuk/keluar).
3. **Many-to-Many (M:N):**
   - `products` &harr; `suppliers`
   - Melalui **Junction Table** `product_suppliers` dengan *Composite Primary Key* `(product_id, supplier_id)` serta menyimpan atribut relasi `supplier_price` (harga beli dari masing-masing supplier).

```
                      +-------------------+
                      |   user_profiles   |
                      +-------------------+
                                ^ (1:1)
                                |
+------------------+    +-------------------+    +---------------------+
|    categories    |    |       users       |    |      suppliers      |
+------------------+    +-------------------+    +---------------------+
        | (1:N)                 | (1:N)                    |
        v                       v                          | (M:N via
+------------------+    +-------------------+              |  product_suppliers)
|     products     |--->|  stock_movements  |<-------------+
+------------------+    +-------------------+
        ^
        | (M:N)
+-----------------------+
|   product_suppliers   |
+-----------------------+
```

---

## 🛡️ 3. Aturan Bisnis & Logika Transaksi (Business Logic)

Sesuai materi Slide 25, 26, 27, dan 33:
- **SKU Unik & Validasi Server:** Setiap SKU diverifikasi unik di database sebelum disimpan. Harga jual dan stok minimum tidak boleh bernilai negatif.
- **Barang Masuk (Stock IN):** Menggunakan *Atomic Database Transaction* (`beginTransaction`, `commit`, `rollBack`). Menambah stok fisik dan mencatat entri histori ke `stock_movements` secara bersamaan.
- **Barang Keluar (Stock OUT):**
  - Menggunakan `SELECT stock FROM products WHERE id = :id FOR UPDATE` (Pessimistic Locking).
  - Validasi ketat `quantity <= stock`. Mencegah terjadinya stok negatif.
  - Mengurangi stok dan mencatat riwayat transaksi.
- **Integritas Referensial (RESTRICT on Delete):**
  - Produk atau Kategori yang sudah memiliki histori transaksi mutasi **tidak dapat dihapus sembarangan** demi mempertahankan integritas audit trail laporan keuangan/gudang.
- **Otorisasi Ketat (RBAC):**
  - **Admin:** Memiliki akses penuh ke Master Produk, Kategori, Supplier, Transaksi Mutasi, Laporan, dan Manajemen User.
  - **Staff:** Hanya dapat melihat katalog produk dan mencatat transaksi Barang Masuk / Barang Keluar. Akses ke menu User dan Master Kategori/Supplier otomatis ditolak dengan status HTTP **403 Forbidden**.

---

## 📊 4. Fitur & Query Agregasi

- **Dashboard Real-Time (Slide 29):**
  - Widget Total Produk: `COUNT(products.id)`
  - Widget Nilai Inventory: `SUM(stock * price)`
  - Widget Produk Stok Rendah: `stock <= minimum_stock`
  - Widget Barang Masuk: `SUM(quantity) WHERE type = 'IN'`
  - Widget Barang Keluar: `SUM(quantity) WHERE type = 'OUT'`
  - Notifikasi peringatan stok kritis dan tombol cepat restock.
- **Listing Produk Tingkat Lanjut (Slide 13, 16, 17):**
  - Pencarian dengan klausa `LIKE` (nama produk dan SKU).
  - Filter kategori dan status stok (aman, menipis, habis).
  - Sorting dengan *Allowlist validation* (mencegah SQL Injection pada `ORDER BY`).
  - Pagination dinamis yang mempertahankan seluruh parameter pencarian/filter di URL.
- **Laporan Inventory (Slide 14 & 28):**
  - Laporan saldo mutasi produk dengan conditional aggregation: `SUM(CASE WHEN type='IN'...)` dan `SUM(CASE WHEN type='OUT'...)`.
  - Laporan kategori dengan `GROUP BY`, `COUNT`, `SUM`, dan `AVG(price)`.
  - Fitur cetak langsung (*Print*) dan Ekspor ke CSV/Excel.

---

## 🔐 5. Keamanan Aplikasi (Security Best Practices)

1. **SQL Injection Defense:** Seluruh query dinamis menggunakan PDO Prepared Statements dengan parameter binding (`:param`). Opsi `PDO::ATTR_EMULATE_PREPARES => false` diaktifkan.
2. **XSS Protection:** Seluruh data keluaran HTML melalui fungsi escaping helper `e()` yang memanfaatkan `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`.
3. **Password Security:** Password pengguna di-hash menggunakan algoritma Bcrypt via `password_hash()` dan diverifikasi dengan `password_verify()`.
4. **Session Security:** `session_regenerate_id(true)` dipanggil saat proses login untuk mencegah serangan *Session Fixation*.

---

## 🚀 6. Panduan Menjalankan Aplikasi

### Persyaratan:
- Web Server XAMPP (Apache & MySQL) aktif.
- PHP versi 8.0 ke atas.

### Langkah Menjalankan:
1. Pastikan folder proyek berada di direktori XAMPP:
   `C:\xampp\htdocs\Mini-Project-3-Inventory-Management-System`
2. Buka browser dan jalankan skrip migrasi database otomatis:
   [http://localhost/Mini-Project-3-Inventory-Management-System/database/setup.php](http://localhost/Mini-Project-3-Inventory-Management-System/database/setup.php)
   *(Skrip ini otomatis membuat database `inventory_management` beserta seluruh tabel dan seed data demo).*
3. Buka URL utama aplikasi:
   [http://localhost/Mini-Project-3-Inventory-Management-System/](http://localhost/Mini-Project-3-Inventory-Management-System/)

### Akun Uji Coba (Demo Credentials):
| Role | Username | Password | Hak Akses |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin123` | Akses penuh seluruh modul & kelola user |
| **Staff Gudang** | `staff` | `staff123` | Pencatatan transaksi masuk/keluar & katalog |

---

## ✅ 7. Checklist Pengumpulan (Slide 36)

- [x] Relasi dan Foreign Key (1:1, 1:N, M:N) berjalan sempurna.
- [x] SKU Unik dan validasi server-side aktif.
- [x] Login, logout, session regeneration, dan otorisasi role (Admin vs Staff) berfungsi.
- [x] Tidak ada konkatenasi query SQL dari input user (Prepared Statement aktif).
- [x] Output HTML selalu di-escape dengan `htmlspecialchars`.
- [x] Fitur Search, Filter kategori, Sorting allowlist, dan Pagination berjalan konsisten.
- [x] Stok tidak dapat menjadi negatif dan setiap pergerakan tercatat di histori mutasi.
- [x] Transaksi database menggunakan `beginTransaction`, `commit`, dan `rollBack`.
- [x] Dashboard menampilkan metrik agregat dan laporan inventory mutasi.
