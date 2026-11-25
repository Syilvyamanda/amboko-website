<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';

$one = fn(string $sql) => $conn->query($sql)->fetch_row()[0] ?? 0;

$revenue   = (float)$one("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status = 'paid' AND status <> 'cancelled'");
$ordersAll = (int)$one("SELECT COUNT(*) FROM orders WHERE status <> 'cancelled'");
$ordersNew = (int)$one("SELECT COUNT(*) FROM orders WHERE status IN ('placed','packing','pending_payment')");
$ordersToday = (int)$one("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE() AND status <> 'cancelled'");
$products  = (int)$one("SELECT COUNT(*) FROM products WHERE is_active = 1");
$lowCount  = (int)$one("SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock <= " . LOW_STOCK_LIMIT);
$customers = (int)$one("SELECT COUNT(*) FROM users WHERE role = 'customer'");
$unitsSold = (int)$one("SELECT COALESCE(SUM(oi.qty),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.status <> 'cancelled'");

// Pendapatan 7 hari terakhir
$days = [];
for ($i = 6; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = 0;
$r = $conn->query("SELECT DATE(created_at) d, SUM(total_amount) s FROM orders WHERE payment_status='paid' AND status<>'cancelled' AND created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(created_at)");
while ($row = $r->fetch_assoc()) if (isset($days[$row['d']])) $days[$row['d']] = (float)$row['s'];
$maxDay = max(1, max($days));

$recent = $conn->query("SELECT * FROM orders ORDER BY id DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);
$low = $conn->query("SELECT id, name, short_name, stock, image_url FROM products WHERE is_active = 1 AND stock <= " . LOW_STOCK_LIMIT . " ORDER BY stock ASC LIMIT 6")->fetch_all(MYSQLI_ASSOC);
$top = $conn->query("SELECT oi.product_name, SUM(oi.qty) q FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.status <> 'cancelled' GROUP BY oi.product_id, oi.product_name ORDER BY q DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

$showArchived = !empty($_GET['archived']);
$inv = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id " . ($showArchived ? '' : 'WHERE p.is_active = 1 ') . "ORDER BY p.id DESC")->fetch_all(MYSQLI_ASSOC);

admin_head('Dashboard', 'dashboard');

$card = function (string $icon, string $label, string $value, string $sub = '', string $tone = 'bg-black') {
    echo '<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4">' .
        '<span class="w-11 h-11 rounded-xl ' . $tone . ' text-white flex items-center justify-center shrink-0"><i class="fa-solid ' . $icon . '"></i></span>' .
        '<div class="min-w-0"><p class="text-xs text-gray-500 font-medium">' . $label . '</p><p class="font-head font-extrabold text-2xl leading-tight truncate">' . $value . '</p>' .
        ($sub ? '<p class="text-[11px] text-gray-400">' . $sub . '</p>' : '') . '</div></div>';
};
?>
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <?php
    $card('fa-sack-dollar', 'Total Pendapatan', e(rp($revenue)), 'Pesanan lunas', 'bg-black');
    $card('fa-receipt', 'Total Pesanan', (string)$ordersAll, $ordersToday . ' pesanan hari ini', 'bg-brand');
    $card('fa-hourglass-half', 'Perlu Diproses', (string)$ordersNew, 'Masuk / packing / menunggu bayar', 'bg-amber-500');
    $card('fa-triangle-exclamation', 'Stok Menipis', (string)$lowCount, 'Stok ≤ ' . LOW_STOCK_LIMIT . ' pcs', $lowCount ? 'bg-red-600' : 'bg-green-600');
    $card('fa-shirt', 'Produk Aktif', (string)$products, $unitsSold . ' pcs terjual', 'bg-gray-700');
    $card('fa-users', 'Pelanggan', (string)$customers, 'Akun terdaftar', 'bg-gray-700');
    ?>
</div>

<div class="grid lg:grid-cols-3 gap-4 mb-8">
    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 lg:col-span-2">
        <h2 class="font-bold mb-4">Pendapatan 7 Hari Terakhir</h2>
        <div class="flex items-end gap-3 h-44">
            <?php foreach ($days as $d => $v): $h = max(3, (int)round($v / $maxDay * 100)); ?>
                <div class="flex-1 flex flex-col items-center justify-end h-full gap-1.5" title="<?php echo e(date_short($d) . ': ' . rp($v)); ?>">
                    <span class="text-[10px] text-gray-500"><?php echo $v ? e(number_format($v / 1000, 0, ',', '.') . 'k') : ''; ?></span>
                    <div class="w-full rounded-t-md <?php echo $d === date('Y-m-d') ? 'bg-brand' : 'bg-black'; ?>" style="height:<?php echo $h; ?>%"></div>
                    <span class="text-[10px] text-gray-500"><?php echo date('d/m', strtotime($d)); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h2 class="font-bold mb-3">Produk Terlaris</h2>
        <?php if (!$top): ?><p class="text-sm text-gray-500">Belum ada penjualan.</p><?php endif; ?>
        <ol class="space-y-2.5 text-sm">
            <?php foreach ($top as $i => $t): ?>
                <li class="flex items-center gap-3"><span class="w-6 h-6 rounded-full bg-gray-100 text-xs font-bold flex items-center justify-center"><?php echo $i + 1; ?></span><span class="flex-1 truncate"><?php echo e($t['product_name']); ?></span><b><?php echo (int)$t['q']; ?> pcs</b></li>
            <?php endforeach; ?>
        </ol>
    </section>
</div>

<div class="grid lg:grid-cols-3 gap-4 mb-8">
    <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden lg:col-span-2">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100"><h2 class="font-bold">Pesanan Terbaru</h2><a href="admin_pesanan.php" class="text-sm text-brand font-semibold hover:underline">Lihat semua</a></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm admin-table">
            <thead><tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider"><th class="px-5 py-3">Kode</th><th class="px-5 py-3">Pelanggan</th><th class="px-5 py-3">Total</th><th class="px-5 py-3">Status</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($recent as $o): ?>
                <tr class="hover:bg-gray-50"><td class="px-5 py-3 font-semibold"><a class="hover:underline" href="admin_pesanan_detail.php?id=<?php echo (int)$o['id']; ?>">#<?php echo e($o['order_code']); ?></a><div class="text-[11px] text-gray-400 font-normal"><?php echo e(date_short($o['created_at'])); ?></div></td>
                <td class="px-5 py-3"><?php echo e($o['shipping_name']); ?></td><td class="px-5 py-3 font-semibold"><?php echo e(rp($o['total_amount'])); ?></td><td class="px-5 py-3"><?php echo order_status_badge($o['status']); ?></td></tr>
            <?php endforeach; if (!$recent): ?><tr><td colspan="4" class="px-5 py-8 text-center text-gray-500">Belum ada pesanan.</td></tr><?php endif; ?>
            </tbody></table></div>
    </section>
    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h2 class="font-bold mb-3">Peringatan Stok</h2>
        <?php if (!$low): ?><p class="text-sm text-green-700"><i class="fa-solid fa-circle-check mr-1"></i>Semua stok aman.</p><?php endif; ?>
        <ul class="space-y-3">
            <?php foreach ($low as $l): ?>
                <li class="flex items-center gap-3 text-sm"><img src="<?php echo e(img($l['image_url'])); ?>" class="w-9 h-11 object-cover rounded" alt="">
                    <span class="flex-1 min-w-0 truncate"><?php echo e($l['short_name'] ?: $l['name']); ?></span>
                    <a href="admin_stok.php?product=<?php echo (int)$l['id']; ?>" class="text-xs font-bold <?php echo $l['stock'] < 1 ? 'bg-red-600 text-white' : 'bg-red-50 text-red-600'; ?> px-2 py-1 rounded"><?php echo (int)$l['stock']; ?> pcs</a></li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<!-- INVENTORI -->
<div class="flex flex-wrap justify-between items-center gap-3 mb-4" id="inventori">
    <h1 class="text-2xl font-bold text-gray-800">Manajemen Inventori</h1>
    <div class="flex items-center gap-3">
        <a href="dashboard.php?<?php echo $showArchived ? '' : 'archived=1'; ?>#inventori" class="text-sm text-gray-500 hover:text-black underline"><?php echo $showArchived ? 'Sembunyikan arsip' : 'Tampilkan arsip'; ?></a>
        <a href="tambah_produk.php" class="bg-black text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-800"><i class="fa-solid fa-plus mr-2"></i>Tambah Produk</a>
    </div>
</div>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
    <table class="w-full text-left border-collapse admin-table">
        <thead><tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider border-b border-gray-200">
            <th class="px-5 py-4 font-medium">Produk</th><th class="px-5 py-4 font-medium">SKU</th><th class="px-5 py-4 font-medium">Kategori</th><th class="px-5 py-4 font-medium">Harga</th><th class="px-5 py-4 font-medium">Stok (ERP)</th><th class="px-5 py-4 font-medium">Status</th><th class="px-5 py-4 font-medium">Aksi</th></tr></thead>
        <tbody class="text-sm text-gray-700 divide-y divide-gray-200">
        <?php foreach ($inv as $row):
            $cls = $row['stock'] <= LOW_STOCK_LIMIT ? 'text-red-600 font-bold' : 'text-green-600 font-bold'; ?>
            <tr class="hover:bg-gray-50 <?php echo $row['is_active'] ? '' : 'opacity-50'; ?>">
                <td class="px-5 py-3"><div class="flex items-center gap-3"><img src="<?php echo e(img($row['image_url'])); ?>" alt="" class="w-10 h-12 object-cover rounded-md bg-gray-100"><a href="product.php?id=<?php echo (int)$row['id']; ?>" target="_blank" class="font-medium text-gray-900 hover:underline"><?php echo e($row['name']); ?></a></div></td>
                <td class="px-5 py-3 text-gray-500"><?php echo e($row['sku']); ?></td>
                <td class="px-5 py-3"><?php echo e($row['category_name']); ?></td>
                <td class="px-5 py-3"><?php echo e(rp($row['price'])); ?></td>
                <td class="px-5 py-3 <?php echo $cls; ?>"><?php echo (int)$row['stock']; ?> Pcs</td>
                <td class="px-5 py-3 text-xs"><?php echo $row['is_active'] ? '<span class="px-2 py-1 rounded-full bg-green-50 text-green-700 font-semibold">Aktif</span>' : '<span class="px-2 py-1 rounded-full bg-gray-100 text-gray-500 font-semibold">Diarsipkan</span>'; ?> <span class="text-gray-400 ml-1"><?php echo e(['in_stock' => '', 'pre_order' => 'Pre-Order', 'limited' => 'Limited'][$row['availability']]); ?></span></td>
                <td class="px-5 py-3 whitespace-nowrap">
                    <a href="edit_produk.php?id=<?php echo (int)$row['id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium text-xs bg-blue-50 px-2 py-1 rounded">Edit</a>
                    <a href="admin_stok.php?product=<?php echo (int)$row['id']; ?>" class="text-gray-700 hover:text-black font-medium text-xs bg-gray-100 px-2 py-1 rounded">Stok</a>
                    <form method="post" action="hapus_produk.php" class="inline" onsubmit="return confirm('Yakin ingin menghapus / mengarsipkan produk ini?')">
                        <?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                        <button class="text-red-500 hover:text-red-700 font-medium text-xs bg-red-50 px-2 py-1 rounded">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; if (!$inv): ?><tr><td colspan="7" class="px-6 py-6 text-center text-gray-500">Tidak ada data produk.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php admin_foot();
