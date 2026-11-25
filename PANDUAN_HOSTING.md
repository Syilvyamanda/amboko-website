# Panduan Hosting AMBOKO (PHP + MySQL) untuk Demo

Vercel / Netlify / GitHub Pages tidak bisa menjalankan PHP + MySQL. Pakai hosting PHP gratis (mis. InfinityFree)
atau tunnel dari XAMPP.

## A. Hosting PHP gratis (link tetap)
1. Daftar hosting PHP + MySQL (mis. InfinityFree), buat akun hosting dan satu **database MySQL** dari panel.
   Catat: host MySQL, nama database, user, password.
2. Buka **phpMyAdmin** hosting -> pilih database Anda -> tab **Import**, import berurutan dari folder `hosting/`:
   1. `database.sql`
   2. `database_admin.sql`
   3. `database_fix_rating_pembayaran.sql`
   (File di folder `hosting/` sudah tanpa baris `USE`, jadi masuk ke database yang Anda pilih.)
3. Edit `config.php`: isi `host`, `user`, `pass`, `db` sesuai data hosting.
4. Upload **isi** folder `amboko` (index.php, asset/, css/, js/, includes/, uploads/, dll.) ke folder `htdocs` lewat File Manager / FTP.
   Folder `hosting/` dan file `.sql` tidak perlu di-upload.
5. Pastikan folder `uploads/` bisa ditulis (permission 755/775) agar upload QRIS & foto produk jalan.
6. Buka alamat website, coba **Register** akun baru lalu **Login**.
7. Login admin `admin@amboko.com` / `admin123`, lalu **ganti password admin** (menu Pengguna).

Catatan: `database_admin.sql` butuh MariaDB (sintaks `ADD COLUMN IF NOT EXISTS`). Jika import error di situ,
hosting memakai MySQL biasa; pilih hosting lain yang MariaDB.

## B. Tunnel dari XAMPP (tanpa migrasi, laptop harus menyala)
1. Jalankan Apache + MySQL di XAMPP, pastikan `http://localhost/amboko/` terbuka.
2. Jalankan Cloudflare Tunnel (`cloudflared tunnel --url http://localhost`) atau ngrok (`ngrok http 80`).
3. Bagikan link https yang muncul, tambahkan `/amboko/` di belakangnya sesuai nama folder Anda.
