<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';

$id = (int)($_GET['id'] ?? 0);
$u = $conn->query("SELECT * FROM users WHERE id = $id AND role = 'customer'")->fetch_assoc();
if (!$u) { flash('error', 'Pelanggan tidak ditemukan.'); redirect('admin_pelanggan.php'); }

$orders = $conn->query("SELECT o.*, (SELECT SUM(qty) FROM order_items WHERE order_id = o.id) pcs,
        (SELECT GROUP_CONCAT(product_name SEPARATOR ', ') FROM order_items WHERE order_id = o.id) names
    FROM orders o WHERE o.user_id = $id ORDER BY o.id DESC")->fetch_all(MYSQLI_ASSOC);
$spent = 0; foreach ($orders as $o) if ($o['payment_status'] === 'paid' && $o['status'] !== 'cancelled') $spent += (float)$o['total_amount'];
$reviews = $conn->query("SELECT r.*, p.name AS product_name FROM product_reviews r JOIN products p ON p.id = r.product_id WHERE r.user_id = $id ORDER BY r.created_at DESC")->fetch_all(MYSQLI_ASSOC);
$inter = $conn->query("SELECT * FROM crm_interactions WHERE user_id = $id ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
const INTER_TYPE = ['whatsapp' => 'WhatsApp', 'instagram' => 'Instagram', 'phone' => 'Telepon', 'store' => 'Di toko', 'email' => 'Email'];

admin_head('Riwayat Pelanggan', 'pelanggan');
?>
<a href="admin_pelanggan.php" class="text-sm text-gray-500 hover:text-black"><i class="fa-solid fa-arrow-left mr-1"></i> Semua pelanggan</a>
<div class="flex flex-wrap items-center justify-between gap-3 mt-2 mb-6">
    <div><h1 class="text-2xl font-bold"><?php echo e($u['name']); ?> <?php echo channel_badge($u['channel']); ?></h1>
        <p class="text-sm text-gray-500">Terdaftar <?php echo e(date_long($u['created_at'])); ?></p></div>
    <a href="admin_pelanggan_form.php?id=<?php echo $id; ?>" class="border border-gray-300 bg-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-100"><i class="fa-solid fa-pen mr-2"></i>Edit</a>
</div>
<div class="grid lg:grid-cols-3 gap-5 mb-6">
    <section class="bg-white rounded-xl border border-gray-200 p-5 text-sm space-y-1.5">
        <h2 class="font-bold mb-2">Kontak</h2>
        <p><i class="fa-solid fa-envelope w-5 text-gray-400"></i> <?php echo e($u['email'] ?: '-'); ?></p>
        <p><i class="fa-solid fa-phone w-5 text-gray-400"></i> <?php echo e($u['phone'] ?: '-'); ?></p>
        <p><i class="fa-solid fa-location-dot w-5 text-gray-400"></i> <?php echo e($u['address'] ?: '-'); ?> <?php echo e($u['postal_code']); ?></p>
    </section>
    <section class="bg-white rounded-xl border border-gray-200 p-5 grid grid-cols-2 gap-4 lg:col-span-2 content-center">
        <div><p class="text-xs text-gray-500 font-medium">Total pesanan</p><p class="font-head font-extrabold text-2xl"><?php echo count($orders); ?></p></div>
        <div><p class="text-xs text-gray-500 font-medium">Total belanja</p><p class="font-head font-extrabold text-2xl"><?php echo e(rp($spent)); ?></p></div>
    </section>
</div>

<section class="bg-white rounded-xl border border-gray-200 overflow-x-auto mb-6">
    <div class="px-5 py-4 border-b border-gray-100"><h2 class="font-bold">Riwayat Pesanan</h2></div>
    <table class="w-full text-left text-sm admin-table">
        <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase"><th class="px-5 py-3">Kode</th><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Produk</th><th class="px-5 py-3">Total</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($orders as $o): ?>
            <tr class="hover:bg-gray-50"><td class="px-5 py-3 font-semibold">#<?php echo e($o['order_code']); ?> <?php echo $o['channel'] === 'offline' ? channel_badge('offline') : ''; ?></td>
                <td class="px-5 py-3 text-gray-500"><?php echo e(date_short($o['created_at'])); ?></td>
                <td class="px-5 py-3"><?php echo e($o['names']); ?> <span class="text-xs text-gray-400">(<?php echo (int)$o['pcs']; ?> pcs)</span></td>
                <td class="px-5 py-3 font-semibold"><?php echo e(rp($o['total_amount'])); ?></td>
                <td class="px-5 py-3"><?php echo order_status_badge($o['status']); ?></td>
                <td class="px-5 py-3"><a href="admin_pesanan_detail.php?id=<?php echo (int)$o['id']; ?>" class="text-blue-600 bg-blue-50 rounded px-2.5 py-1 text-xs font-medium">Detail</a></td></tr>
        <?php endforeach; if (!$orders): ?><tr><td colspan="6" class="px-5 py-8 text-center text-gray-500">Belum ada pesanan.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>

<div class="grid lg:grid-cols-2 gap-5">
    <section class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-bold mb-3">Ulasan</h2>
        <?php foreach ($reviews as $rv): ?>
            <div class="border-b border-gray-100 last:border-0 py-3 text-sm"><div class="flex items-center justify-between gap-3"><b><?php echo e($rv['product_name']); ?></b><?php echo stars($rv['rating'], 'text-xs'); ?></div>
                <p class="text-gray-600 mt-1"><?php echo e($rv['comment']); ?></p><p class="text-xs text-gray-400 mt-1"><?php echo e(date_short($rv['created_at'])); ?></p></div>
        <?php endforeach; if (!$reviews): ?><p class="text-sm text-gray-500">Belum ada ulasan.</p><?php endif; ?>
    </section>
    <section class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-3"><h2 class="font-bold">Riwayat Interaksi</h2><a href="admin_crm.php?tab=interaksi" class="text-sm text-brand font-semibold hover:underline">Catat di CRM</a></div>
        <?php foreach ($inter as $it): ?>
            <div class="border-b border-gray-100 last:border-0 py-3 text-sm"><div class="flex items-center justify-between"><?php echo admin_pill(INTER_TYPE[$it['type']] ?? $it['type']); ?><span class="text-xs text-gray-400"><?php echo e(date_short($it['created_at'])); ?></span></div>
                <p class="text-gray-600 mt-1.5"><?php echo e($it['summary']); ?></p></div>
        <?php endforeach; if (!$inter): ?><p class="text-sm text-gray-500">Belum ada interaksi tercatat.</p><?php endif; ?>
    </section>
</div>
<?php admin_foot();
