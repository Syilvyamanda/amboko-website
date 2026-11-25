<?php
// =====================================================================
//  AMBOKO ERP - Koneksi database & konfigurasi
// =====================================================================
date_default_timezone_set('Asia/Jakarta');

// Pengaturan database ada di config.php (lokal XAMPP = bawaan; hosting = isi data dari hosting).
$cfg = is_file(__DIR__ . '/config.php') ? (require __DIR__ . '/config.php') : [];
$host = $cfg['host'] ?? "localhost";
$user = $cfg['user'] ?? "root";
$pass = $cfg['pass'] ?? "";
$db   = $cfg['db']   ?? "amboko_erp";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+07:00'");

// ---- Konfigurasi toko -------------------------------------------------
const FREE_SHIPPING_MIN  = 200000;  // gratis ongkir reguler di atas nominal ini
const REGULAR_SHIPPING   = 25000;   // tarif reguler jika belum gratis
const EXPRESS_SURCHARGE  = 15000;   // tambahan untuk Express Next Day
const SERVICE_FEE        = 0;       // biaya layanan & penanganan
const PPN_RATE           = 11;      // harga sudah termasuk PPN 11%
const LOW_STOCK_LIMIT    = 10;      // batas "stok menipis" di dashboard
// true  = pembayaran disimulasikan langsung LUNAS (untuk demo/skripsi).
// false = pesanan dibuat status "menunggu pembayaran"; admin menandai lunas manual
//         (sambungkan ke Midtrans/Xendit di checkout.php untuk produksi).
const PAYMENT_SIMULATION = false;

const INSTAGRAM_URL = 'https://www.instagram.com/';
const TIKTOK_URL    = 'https://www.tiktok.com/';

// ---- Bootstrap akun admin (hash, bukan plaintext) --------------------
$cek_admin = $conn->query("SELECT id, password FROM users WHERE email = 'admin@amboko.com' LIMIT 1");
if ($cek_admin && $cek_admin->num_rows == 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES ('Super Admin', 'admin@amboko.com', ?, 'admin')");
    $stmt->bind_param('s', $hash);
    $stmt->execute();
}

require_once __DIR__ . '/includes/functions.php';
