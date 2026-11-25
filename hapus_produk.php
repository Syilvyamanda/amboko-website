<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('dashboard.php');
csrf_check();

$id = (int)($_POST['id'] ?? 0);
$used = (int)$conn->query("SELECT COUNT(*) FROM order_items WHERE product_id = $id")->fetch_row()[0];

if ($used > 0) {
    // produk pernah dipesan -> arsipkan (riwayat pesanan tetap utuh)
    $conn->query("UPDATE products SET is_active = 0 WHERE id = $id");
    flash('success', 'Produk pernah dipesan, jadi diarsipkan (disembunyikan dari toko) agar riwayat pesanan tetap utuh.');
} else {
    $st = $conn->prepare("DELETE FROM products WHERE id = ?");
    $st->bind_param('i', $id);
    $st->execute();
    flash('success', 'Produk berhasil dihapus.');
}
$back = safe_next($_POST['back'] ?? '', 'dashboard.php#inventori');
redirect($back);
