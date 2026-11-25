# AMBOKO ERP

Toko streetwear + panel ERP (PHP + MySQL/MariaDB + Tailwind). Semua halaman mengikuti desain Anda.

## Cara menjalankan (XAMPP)
1. Salin folder `amboko_erp` ke `C:\xampp\htdocs\`.
2. Start **Apache** dan **MySQL** di XAMPP.
3. Buka phpMyAdmin -> tab **Import** -> pilih `database.sql` -> Go. Lalu import **`database_admin.sql`** (sekali, aman diulang) untuk halaman admin baru + data dummy pelanggan.
   (Sudah punya data asli? pakai `database_upgrade.sql`, lihat bagian bawah.)
   Database sudah terpasang dan hanya ingin memperbaiki pilihan produk? Jalankan `database_fix_warna.sql` saja (aman diulang).
4. Buka `http://localhost/amboko_erp/`

Akun contoh:

| Peran     | Email                      | Password     |
|-----------|----------------------------|--------------|
| Admin     | admin@amboko.com           | admin123     |
| Pelanggan | aditya.pratama@email.com   | customer123  |

Ganti password admin sebelum dipakai sungguhan. Koneksi database ada di `config.php` (salin dari `config.sample.php`).
Butuh internet saat dibuka (Tailwind, Font Awesome, Google Fonts lewat CDN, seperti proyek asli).

## Halaman toko (sesuai desain)
| Desain | File |
|---|---|
| Desktop Home | `index.php` |
| Catalogue | `katalog.php` (filter kategori/harga/material/ketersediaan, sort, search, wishlist) |
| Product Detail (6 produk) | `product.php?id=` (satu template, varian tampilan diatur data DB) |
| Keranjang Belanja | `keranjang.php` |
| Checkout & Pembayaran | `checkout.php` |
| Konfirmasi Pesanan | `konfirmasi.php` |
| (tambahan) | `pesanan.php`, `lacak.php`, `info.php`, `login.php`, `register.php` |

## Panel admin
Menu samping dikelompokkan; hak akses tiap administrator diatur di menu **Pengguna**.

| Ketentuan backend | Menu / file |
|---|---|
| Manajemen Produk (daftar, tambah/edit/hapus, kategori, gambar) | **Produk** `admin_produk.php`, `tambah_produk.php`, `edit_produk.php` |
| Manajemen Kategori (daftar, tambah/edit/hapus, gambar) | **Kategori** `admin_kategori.php` |
| Manajemen Stok (daftar, ubah stok, peringatan stok habis) | **Stok** `admin_stok.php` |
| Manajemen Pesanan (daftar, status, detail) | **Pesanan** `admin_pesanan.php` + `admin_pesanan_detail.php` |
| Manajemen Pengiriman (daftar, tambah/edit/hapus, biaya) | **Pengiriman** `admin_pengiriman.php` |
| Manajemen Pembayaran (daftar, tambah/edit/hapus, metode) | **Pembayaran** `admin_pembayaran.php` |
| Pengembalian & Penukaran (daftar, status, detail) | **Retur & Tukar** `admin_retur.php`, `admin_retur_detail.php` |
| Lacak Pesanan (pengguna) | halaman website `lacak.php` (tidak diubah) |
| Manajemen Pelanggan (daftar, tambah/edit/hapus, riwayat pesanan) | **Pelanggan** `admin_pelanggan*.php` |
| Pengguna/Administrator + hak akses | **Pengguna** `admin_pengguna.php` |
| Pengaturan Website (umum, logo, favicon, kontak) | **Pengaturan** `admin_pengaturan.php` |
| Laporan Keuangan (laporan, pendapatan, pengeluaran) | **Keuangan** `admin_keuangan.php` |
| CRM (leads, riwayat interaksi, analisis pelanggan) | **CRM** `admin_crm.php` |
| Analitik & Statistik (pengunjung, penjualan, terlaris) | **Analitik** `admin_analitik.php` |
| (fitur lama, tetap ada) | **Dashboard** `dashboard.php`, **Voucher** `admin_voucher.php` |

Catatan: Pengaturan, Pengiriman (biaya), dan Metode Pembayaran adalah data master admin; halaman website (checkout, footer, kontak) tidak membacanya.
Statistik pengunjung di `database_admin.sql` adalah data contoh karena website belum mencatat kunjungan.

## Data dummy pelanggan
`database_admin.sql` menambah 18 pelanggan sesuai nama pada ulasan produk (11 beli lewat website, 7 beli di toko offline),
lengkap dengan pesanan, pembayaran, dan ulasan yang tertaut. Pelanggan website bisa login: `<nama>.<belakang>@email.com` / `customer123`
(contoh `bagas.saputra@email.com`). Pelanggan toko tidak punya email/password.

## Database (tabel baru/berubah)
`categories`, `products` (diperluas), `product_images`, `product_option_groups`, `product_option_values`,
`product_relations`, `product_metrics`, `product_reviews`, `wishlists`, `vouchers`, `orders` (diperluas),
`order_items` (snapshot nama/varian), `order_status_history`, `payments`, `stock_movements`.

## Hal penting
* **Pembayaran**: `PAYMENT_SIMULATION = false` di `koneksi.php`. Setelah checkout, pelanggan melihat kode **QRIS** atau nomor **Virtual Account**
  (BCA/Mandiri/BNI/BRI/Permata) di halaman konfirmasi, lalu mengirim bukti bayar via WhatsApp; admin menekan **Tandai Sudah Dibayar**.
  QRIS dan kode VA diganti di admin: menu **Pembayaran**. QRIS bawaan hanya contoh. Set `true` untuk mode demo (langsung LUNAS).
  Konfirmasi otomatis (tanpa admin) butuh Midtrans/Xendit di `checkout.php`.
* **Rating & ulasan** hanya dari pembeli yang punya pesanan lunas berisi produk itu (produk belum ada pembeli = tanpa rating).
  Pembeli menulis ulasan di halaman produk setelah pesanannya berstatus Diterima. Database lama: import `database_fix_rating_pembayaran.sql`.
* **Contact Us**: alamat tersambung ke Google Maps + peta tertanam; alamat/link Maps diatur di admin **Pengaturan**.
* Harga sudah termasuk PPN 11%. Gratis ongkir reguler >= Rp200.000; Express +Rp15.000 (ubah di `koneksi.php`).
* Password lama (plaintext) otomatis diubah menjadi hash saat pemiliknya login.
* Checkout wajib login (agar pesanan terhubung ke akun).

## Upgrade dari database lama
Backup dulu, lalu import `database_upgrade.sql` (butuh MariaDB/XAMPP). Akun, pesanan, dan produk lama dipertahankan.
Foto/varian/ulasan sesuai desain hanya ada di `database.sql` (instalasi baru).
