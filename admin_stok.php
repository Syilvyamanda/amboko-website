<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';

$selected = (int)($_GET['product'] ?? $_POST['product_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pid = (int)($_POST['product_id'] ?? 0);
    $mode = $_POST['mode'] ?? 'restock';
    $qty = (int)($_POST['qty'] ?? 0);
    $note = trim($_POST['note'] ?? '') ?: null;
    $row = $conn->query("SELECT stock, name FROM products WHERE id = $pid")->fetch_assoc();
    if (!$row) { flash('error', 'Produk tidak ditemukan.'); }
    elseif ($qty === 0 && $mode !== 'set') { flash('error', 'Jumlah tidak boleh 0.'); }
    else {
        $conn->begin_transaction();
        $cur = (int)$conn->query("SELECT stock FROM products WHERE id = $pid FOR UPDATE")->fetch_row()[0];
        if ($mode === 'restock')       { $delta = abs($qty);  $type = 'restock'; }
        elseif ($mode === 'reduce')    { $delta = -abs($qty); $type = 'adjustment'; }
        else                           { $delta = max(0, $qty) - $cur; $type = 'adjustment'; }
        if ($cur + $delta < 0) { $conn->rollback(); flash('error', 'Stok tidak boleh negatif (stok saat ini ' . $cur . ').'); }
        elseif ($delta === 0) { $conn->rollback(); flash('error', 'Tidak ada perubahan stok.'); }
        else {
            $conn->query("UPDATE products SET stock = stock + ($delta) WHERE id = $pid");
            stock_log($conn, $pid, $type, $delta, $note ?: ($mode === 'set' ? 'Stok opname' : null), null, user_id());
            $conn->commit();
            flash('success', 'Stok ' . $row['name'] . ' diperbarui (' . ($delta > 0 ? '+' : '') . $delta . ').');
        }
    }
    redirect('admin_stok.php?product=' . $pid);
}

$prods = $conn->query("SELECT id, name, short_name, sku, stock, is_active, image_url FROM products ORDER BY is_active DESC, name")->fetch_all(MYSQLI_ASSOC);
$empty = array_filter($prods, fn($p) => $p['is_active'] && (int)$p['stock'] < 1);
$lowList = array_filter($prods, fn($p) => $p['is_active'] && (int)$p['stock'] >= 1 && (int)$p['stock'] <= LOW_STOCK_LIMIT);
$sql = "SELECT m.*, p.name, p.sku, u.name AS uname, o.order_code FROM stock_movements m JOIN products p ON p.id = m.product_id LEFT JOIN users u ON u.id = m.user_id LEFT JOIN orders o ON o.id = m.order_id"
     . ($selected ? " WHERE m.product_id = $selected" : '') . " ORDER BY m.id DESC LIMIT 100";
$moves = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
$typeLabel = ['initial' => ['Stok Awal', 'bg-gray-100 text-gray-700'], 'sale' => ['Penjualan', 'bg-blue-50 text-blue-700'], 'restock' => ['Restock', 'bg-green-50 text-green-700'],
              'adjustment' => ['Penyesuaian', 'bg-amber-50 text-amber-700'], 'cancel_return' => ['Retur Batal', 'bg-purple-50 text-purple-700']];

admin_head('Stok', 'stok');
$inp = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-black bg-white';
?>
<?php if ($empty || $lowList): ?>
<div class="mb-5 space-y-2">
    <?php if ($empty): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-5 py-3 text-sm"><i class="fa-solid fa-circle-exclamation mr-2"></i><b>Stok habis:</b>
        <?php echo e(implode(', ', array_map(fn($p) => $p['short_name'] ?: $p['name'], $empty))); ?></div><?php endif; ?>
    <?php if ($lowList): ?><div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-5 py-3 text-sm"><i class="fa-solid fa-triangle-exclamation mr-2"></i><b>Stok menipis (&le; <?php echo LOW_STOCK_LIMIT; ?> pcs):</b>
        <?php echo e(implode(', ', array_map(fn($p) => ($p['short_name'] ?: $p['name']) . ' (' . $p['stock'] . ')', $lowList))); ?></div><?php endif; ?>
</div>
<?php endif; ?>

<section class="bg-white rounded-xl border border-gray-200 overflow-x-auto mb-5">
    <div class="px-5 py-4 border-b border-gray-100"><h2 class="font-bold">Daftar Stok</h2></div>
    <table class="w-full text-left text-sm admin-table">
        <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase"><th class="px-5 py-3">Produk</th><th class="px-5 py-3">SKU</th><th class="px-5 py-3 text-right">Stok</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($prods as $p): $n = (int)$p['stock'];
            [$lbl, $cls] = $n < 1 ? ['Habis', 'bg-red-100 text-red-700'] : ($n <= LOW_STOCK_LIMIT ? ['Menipis', 'bg-amber-100 text-amber-800'] : ['Aman', 'bg-green-50 text-green-700']); ?>
            <tr class="hover:bg-gray-50 <?php echo $p['is_active'] ? '' : 'opacity-50'; ?> <?php echo $selected === (int)$p['id'] ? 'bg-blue-50/50' : ''; ?>">
                <td class="px-5 py-2.5"><div class="flex items-center gap-3"><img src="<?php echo e(img($p['image_url'])); ?>" alt="" class="w-8 h-10 object-cover rounded bg-gray-100"><span class="font-medium"><?php echo e($p['short_name'] ?: $p['name']); ?></span></div></td>
                <td class="px-5 py-2.5 text-gray-500"><?php echo e($p['sku']); ?></td>
                <td class="px-5 py-2.5 text-right font-bold"><?php echo $n; ?> pcs</td>
                <td class="px-5 py-2.5"><?php echo admin_pill($lbl, $cls); ?></td>
                <td class="px-5 py-2.5 text-right"><a href="admin_stok.php?product=<?php echo (int)$p['id']; ?>#ubah-stok" class="text-xs bg-gray-100 hover:bg-gray-200 rounded px-2 py-1 font-medium">Ubah</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<div class="grid lg:grid-cols-3 gap-5">
    <section id="ubah-stok" class="bg-white rounded-xl border border-gray-200 p-5 h-fit">
        <h2 class="font-bold mb-4">Ubah Stok</h2>
        <form method="post" class="space-y-3">
            <?php echo csrf_field(); ?>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Produk</label>
                <select name="product_id" class="<?php echo $inp; ?> mt-1" required onchange="location='admin_stok.php?product='+this.value">
                    <option value="">- pilih produk -</option>
                    <?php foreach ($prods as $p): ?><option value="<?php echo (int)$p['id']; ?>" <?php echo $selected === (int)$p['id'] ? 'selected' : ''; ?>><?php echo e($p['sku'] . ' - ' . ($p['short_name'] ?: $p['name']) . ' (stok ' . $p['stock'] . ')'); ?></option><?php endforeach; ?>
                </select></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Jenis</label>
                <select name="mode" class="<?php echo $inp; ?> mt-1"><option value="restock">Tambah stok</option><option value="reduce">Kurangi stok (rusak / hilang)</option><option value="set">Edit jumlah stok (opname)</option></select></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Jumlah</label><input type="number" name="qty" min="0" class="<?php echo $inp; ?> mt-1" required></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Catatan</label><input name="note" class="<?php echo $inp; ?> mt-1" placeholder="mis. PO #12 dari konveksi"></div>
            <button class="w-full bg-black text-white rounded-lg py-2.5 text-sm font-bold hover:bg-gray-800">Simpan Perubahan</button>
        </form>
    </section>

    <section class="lg:col-span-2 bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between"><h2 class="font-bold">Riwayat Stok</h2>
            <?php if ($selected): ?><a href="admin_stok.php" class="text-sm text-brand font-semibold">Tampilkan semua</a><?php endif; ?></div>
        <div class="overflow-x-auto"><table class="w-full text-sm text-left admin-table">
            <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase"><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Produk</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3 text-right">Perubahan</th><th class="px-5 py-3 text-right">Stok Akhir</th><th class="px-5 py-3">Catatan</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($moves as $m): [$tl, $tc] = $typeLabel[$m['type']]; ?>
                <tr class="hover:bg-gray-50"><td class="px-5 py-2.5 text-gray-500 whitespace-nowrap"><?php echo e(date_short($m['created_at'])); ?> <span class="text-xs"><?php echo date('H:i', strtotime($m['created_at'])); ?></span></td>
                <td class="px-5 py-2.5"><?php echo e($m['name']); ?></td>
                <td class="px-5 py-2.5"><span class="text-xs font-semibold px-2 py-1 rounded-full <?php echo $tc; ?>"><?php echo $tl; ?></span></td>
                <td class="px-5 py-2.5 text-right font-bold <?php echo $m['qty_change'] > 0 ? 'text-green-600' : 'text-red-600'; ?>"><?php echo ($m['qty_change'] > 0 ? '+' : '') . (int)$m['qty_change']; ?></td>
                <td class="px-5 py-2.5 text-right"><?php echo (int)$m['stock_after']; ?></td>
                <td class="px-5 py-2.5 text-gray-500 text-xs"><?php echo e($m['note']); ?><?php echo $m['uname'] ? ' &bull; ' . e($m['uname']) : ''; ?></td></tr>
            <?php endforeach; if (!$moves): ?><tr><td colspan="6" class="px-5 py-8 text-center text-gray-500">Belum ada pergerakan stok.</td></tr><?php endif; ?>
            </tbody></table></div>
    </section>
</div>
<?php admin_foot();
