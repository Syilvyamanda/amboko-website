<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';

$status = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');
$where = ['1=1']; $types = ''; $args = [];
if (isset(ORDER_STATUS[$status])) { $where[] = 'o.status = ?'; $types .= 's'; $args[] = $status; }
if ($q !== '') { $where[] = '(o.order_code LIKE ? OR o.shipping_name LIKE ? OR o.shipping_email LIKE ?)'; $types .= 'sss'; $l = "%$q%"; array_push($args, $l, $l, $l); }
$st = $conn->prepare("SELECT o.*, (SELECT SUM(qty) FROM order_items WHERE order_id = o.id) pcs FROM orders o WHERE " . implode(' AND ', $where) . " ORDER BY o.id DESC LIMIT 200");
if ($types) $st->bind_param($types, ...$args);
$st->execute();
$orders = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$counts = [];
$r = $conn->query("SELECT status, COUNT(*) n FROM orders GROUP BY status");
while ($row = $r->fetch_assoc()) $counts[$row['status']] = (int)$row['n'];

admin_head('Pesanan', 'pesanan');
?>
<div class="flex flex-wrap items-center gap-2 mb-5">
    <a href="admin_pesanan.php" class="px-3.5 py-1.5 rounded-full text-sm font-medium <?php echo $status === '' ? 'bg-black text-white' : 'bg-white border border-gray-200 hover:bg-gray-100'; ?>">Semua</a>
    <?php foreach (ORDER_STATUS as $k => [$label]): ?>
        <a href="?status=<?php echo $k; ?>" class="px-3.5 py-1.5 rounded-full text-sm font-medium <?php echo $status === $k ? 'bg-black text-white' : 'bg-white border border-gray-200 hover:bg-gray-100'; ?>"><?php echo e($label); ?> <span class="opacity-60"><?php echo $counts[$k] ?? 0; ?></span></a>
    <?php endforeach; ?>
    <form class="ml-auto"><input type="hidden" name="status" value="<?php echo e($status); ?>"><input name="q" value="<?php echo e($q); ?>" placeholder="Cari kode / nama / email" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-60 outline-none focus:ring-2 focus:ring-black"></form>
</div>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
<table class="w-full text-left text-sm admin-table">
    <thead><tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider"><th class="px-5 py-3">Kode</th><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Penerima</th><th class="px-5 py-3">Item</th><th class="px-5 py-3">Total</th><th class="px-5 py-3">Bayar</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
    <tbody class="divide-y divide-gray-100">
    <?php foreach ($orders as $o): ?>
        <tr class="hover:bg-gray-50">
            <td class="px-5 py-3 font-semibold">#<?php echo e($o['order_code']); ?> <?php if (($o['channel'] ?? 'online') === 'offline') echo '<span class="ml-1 align-middle">' . channel_badge('offline') . '</span>'; ?></td>
            <td class="px-5 py-3 text-gray-500"><?php echo e(date_short($o['created_at'])); ?> <span class="text-xs"><?php echo date('H:i', strtotime($o['created_at'])); ?></span></td>
            <td class="px-5 py-3"><?php echo e($o['shipping_name']); ?><div class="text-xs text-gray-400"><?php echo e($o['shipping_city']); ?></div></td>
            <td class="px-5 py-3"><?php echo (int)$o['pcs']; ?> pcs</td>
            <td class="px-5 py-3 font-semibold"><?php echo e(rp($o['total_amount'])); ?></td>
            <td class="px-5 py-3 text-xs"><?php echo ['paid' => '<span class="text-green-700 font-semibold">Lunas</span>', 'pending' => '<span class="text-amber-700 font-semibold">Pending</span>', 'failed' => 'Gagal', 'refunded' => 'Refund'][$o['payment_status']]; ?></td>
            <td class="px-5 py-3"><?php echo order_status_badge($o['status']); ?></td>
            <td class="px-5 py-3"><a href="admin_pesanan_detail.php?id=<?php echo (int)$o['id']; ?>" class="text-blue-600 bg-blue-50 hover:bg-blue-100 rounded px-2.5 py-1 text-xs font-medium">Detail</a></td>
        </tr>
    <?php endforeach; if (!$orders): ?><tr><td colspan="8" class="px-5 py-10 text-center text-gray-500">Tidak ada pesanan.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?php admin_foot();
