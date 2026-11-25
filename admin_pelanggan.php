<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';

// Hapus pelanggan (hanya jika belum punya pesanan)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $u = $conn->query("SELECT id, name FROM users WHERE id = $id AND role = 'customer'")->fetch_assoc();
    if (!$u) flash('error', 'Pelanggan tidak ditemukan.');
    elseif ((int)$conn->query("SELECT COUNT(*) FROM orders WHERE user_id = $id")->fetch_row()[0] > 0)
        flash('error', $u['name'] . ' punya riwayat pesanan, jadi tidak bisa dihapus.');
    else { $conn->query("DELETE FROM users WHERE id = $id"); flash('success', 'Pelanggan ' . $u['name'] . ' dihapus.'); }
    redirect('admin_pelanggan.php');
}

$q = trim($_GET['q'] ?? '');
$ch = in_array($_GET['ch'] ?? '', ['online', 'offline'], true) ? $_GET['ch'] : '';
$where = ["u.role = 'customer'"]; $types = ''; $args = [];
if ($ch) { $where[] = 'u.channel = ?'; $types .= 's'; $args[] = $ch; }
if ($q !== '') { $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.city LIKE ?)'; $types .= 'ssss'; $l = "%$q%"; array_push($args, $l, $l, $l, $l); }
$st = $conn->prepare("SELECT u.*, COUNT(o.id) AS n_orders,
        COALESCE(SUM(CASE WHEN o.payment_status='paid' AND o.status<>'cancelled' THEN o.total_amount END),0) AS spent, MAX(o.created_at) AS last_order
    FROM users u LEFT JOIN orders o ON o.user_id = u.id WHERE " . implode(' AND ', $where) . " GROUP BY u.id ORDER BY spent DESC, u.id DESC");
if ($types) $st->bind_param($types, ...$args);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$cnt = ['online' => 0, 'offline' => 0];
$r = $conn->query("SELECT channel, COUNT(*) n FROM users WHERE role = 'customer' GROUP BY channel");
while ($row = $r->fetch_assoc()) $cnt[$row['channel']] = (int)$row['n'];

admin_head('Pelanggan', 'pelanggan');
?>
<div class="flex flex-wrap items-center gap-2 mb-4">
    <a href="admin_pelanggan.php" class="px-3.5 py-1.5 rounded-full text-sm font-medium <?php echo $ch === '' ? 'bg-black text-white' : 'bg-white border border-gray-200 hover:bg-gray-100'; ?>">Semua <span class="opacity-60"><?php echo array_sum($cnt); ?></span></a>
    <a href="?ch=online" class="px-3.5 py-1.5 rounded-full text-sm font-medium <?php echo $ch === 'online' ? 'bg-black text-white' : 'bg-white border border-gray-200 hover:bg-gray-100'; ?>">Website <span class="opacity-60"><?php echo $cnt['online']; ?></span></a>
    <a href="?ch=offline" class="px-3.5 py-1.5 rounded-full text-sm font-medium <?php echo $ch === 'offline' ? 'bg-black text-white' : 'bg-white border border-gray-200 hover:bg-gray-100'; ?>">Toko <span class="opacity-60"><?php echo $cnt['offline']; ?></span></a>
    <form class="ml-auto flex gap-2"><input type="hidden" name="ch" value="<?php echo e($ch); ?>"><input name="q" value="<?php echo e($q); ?>" placeholder="Cari nama / kontak / kota" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-56 outline-none focus:ring-2 focus:ring-black"></form>
    <a href="admin_pelanggan_form.php" class="bg-black text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-800"><i class="fa-solid fa-plus mr-2"></i>Tambah Pelanggan</a>
</div>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
<table class="w-full text-left text-sm admin-table">
    <thead><tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider"><th class="px-5 py-3">Pelanggan</th><th class="px-5 py-3">Kontak</th><th class="px-5 py-3">Kota</th><th class="px-5 py-3">Asal</th><th class="px-5 py-3 text-right">Pesanan</th><th class="px-5 py-3 text-right">Total Belanja</th><th class="px-5 py-3">Terakhir</th><th class="px-5 py-3"></th></tr></thead>
    <tbody class="divide-y divide-gray-100">
    <?php foreach ($rows as $u): ?>
        <tr class="hover:bg-gray-50"><td class="px-5 py-3 font-semibold"><a href="admin_pelanggan_detail.php?id=<?php echo (int)$u['id']; ?>" class="hover:underline"><?php echo e($u['name']); ?></a><div class="text-xs text-gray-400 font-normal"><?php echo e($u['email'] ?: '-'); ?></div></td>
        <td class="px-5 py-3 text-gray-600"><?php echo e($u['phone'] ?: '-'); ?></td><td class="px-5 py-3"><?php echo e($u['city'] ?: '-'); ?></td>
        <td class="px-5 py-3"><?php echo channel_badge($u['channel']); ?></td>
        <td class="px-5 py-3 text-right"><?php echo (int)$u['n_orders']; ?></td><td class="px-5 py-3 text-right font-semibold"><?php echo e(rp($u['spent'])); ?></td>
        <td class="px-5 py-3 text-gray-500"><?php echo $u['last_order'] ? e(date_short($u['last_order'])) : '-'; ?></td>
        <td class="px-5 py-3 whitespace-nowrap text-right">
            <a href="admin_pelanggan_detail.php?id=<?php echo (int)$u['id']; ?>" class="text-gray-700 font-medium text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded">Riwayat</a>
            <a href="admin_pelanggan_form.php?id=<?php echo (int)$u['id']; ?>" class="text-blue-600 font-medium text-xs bg-blue-50 px-2 py-1 rounded">Edit</a>
            <form method="post" class="inline" onsubmit="return confirm('Hapus pelanggan ini?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>"><button class="text-red-500 font-medium text-xs bg-red-50 px-2 py-1 rounded">Hapus</button></form></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="px-5 py-10 text-center text-gray-500">Belum ada pelanggan.</td></tr><?php endif; ?>
    </tbody></table></div>
<?php admin_foot();
