<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';
include 'includes/order_view.php';
include 'includes/admin_retur_consts.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $itemId = (int)($_POST['item_id'] ?? 0);
    $type = array_key_exists($_POST['type'] ?? '', RETUR_TYPE) ? $_POST['type'] : 'exchange';
    $reason = trim($_POST['reason'] ?? '');
    $detail = trim($_POST['detail'] ?? '') ?: null;
    $it = $conn->query("SELECT oi.order_id, oi.product_name, oi.price, oi.qty FROM order_items oi WHERE oi.id = $itemId")->fetch_assoc();
    if (!$it) { flash('error', 'Pilih pesanan / produk yang diretur.'); }
    elseif ($reason === '') { flash('error', 'Alasan wajib diisi.'); }
    else {
        $refund = $type === 'return' ? (float)$it['price'] * (int)$it['qty'] : 0;
        do {
            $code = 'RTN-' . date('md') . '-' . random_int(10, 99);
            $dup = $conn->query("SELECT 1 FROM order_returns WHERE code = '$code'")->num_rows;
        } while ($dup);
        $now = date('Y-m-d H:i:s');
        $oid = (int)$it['order_id'];
        $st = $conn->prepare("INSERT INTO order_returns (code, order_id, product_name, type, reason, detail, status, refund_amount, created_at, updated_at) VALUES (?,?,?,?,?,?,'requested',?,?,?)");
        $st->bind_param('sissssdss', $code, $oid, $it['product_name'], $type, $reason, $detail, $refund, $now, $now);
        $st->execute();
        flash('success', 'Pengajuan ' . $code . ' dicatat.');
    }
    redirect('admin_retur.php');
}

$fs = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');
$where = ['1=1']; $types = ''; $args = [];
if (isset(RETUR_STATUS[$fs])) { $where[] = 'r.status = ?'; $types .= 's'; $args[] = $fs; }
if ($q !== '') { $where[] = '(r.code LIKE ? OR o.order_code LIKE ? OR o.shipping_name LIKE ?)'; $types .= 'sss'; $l = "%$q%"; array_push($args, $l, $l, $l); }
$st = $conn->prepare("SELECT r.*, o.order_code, o.shipping_name, o.channel FROM order_returns r JOIN orders o ON o.id = r.order_id WHERE " . implode(' AND ', $where) . " ORDER BY r.id DESC LIMIT 200");
if ($types) $st->bind_param($types, ...$args);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$counts = [];
$r = $conn->query("SELECT status, COUNT(*) n FROM order_returns GROUP BY status");
while ($row = $r->fetch_assoc()) $counts[$row['status']] = (int)$row['n'];
$items = $conn->query("SELECT oi.id, oi.product_name, oi.options, o.order_code, o.shipping_name FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE o.status IN ('shipped','delivered') ORDER BY o.id DESC, oi.id LIMIT 300")->fetch_all(MYSQLI_ASSOC);

admin_head('Retur & Tukar', 'retur');
$inp = ADMIN_INP;
?>
<div class="flex flex-wrap items-center gap-2 mb-4">
    <a href="admin_retur.php" class="px-3.5 py-1.5 rounded-full text-sm font-medium <?php echo $fs === '' ? 'bg-black text-white' : 'bg-white border border-gray-200 hover:bg-gray-100'; ?>">Semua</a>
    <?php foreach (RETUR_STATUS as $k => [$label]): ?>
        <a href="?status=<?php echo $k; ?>" class="px-3.5 py-1.5 rounded-full text-sm font-medium <?php echo $fs === $k ? 'bg-black text-white' : 'bg-white border border-gray-200 hover:bg-gray-100'; ?>"><?php echo e($label); ?> <span class="opacity-60"><?php echo $counts[$k] ?? 0; ?></span></a>
    <?php endforeach; ?>
    <form class="ml-auto"><input type="hidden" name="status" value="<?php echo e($fs); ?>"><input name="q" value="<?php echo e($q); ?>" placeholder="Cari kode / nama" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-52 outline-none focus:ring-2 focus:ring-black"></form>
</div>

<details class="bg-white rounded-xl border border-gray-200 mb-5">
    <summary class="px-5 py-3.5 cursor-pointer font-bold text-sm"><i class="fa-solid fa-plus mr-2"></i>Catat Pengembalian / Penukaran</summary>
    <form method="post" class="px-5 pb-5 grid md:grid-cols-4 gap-3 text-sm items-end">
        <?php echo csrf_field(); ?>
        <div class="md:col-span-2"><label class="text-xs font-bold text-gray-500 uppercase">Pesanan &amp; Produk</label>
            <select name="item_id" class="<?php echo $inp; ?> mt-1" required><option value="">- pilih -</option>
                <?php foreach ($items as $i): ?><option value="<?php echo (int)$i['id']; ?>">#<?php echo e($i['order_code'] . ' - ' . $i['shipping_name'] . ' - ' . $i['product_name'] . ' (' . order_item_variant($i) . ')'); ?></option><?php endforeach; ?></select></div>
        <div><label class="text-xs font-bold text-gray-500 uppercase">Jenis</label>
            <select name="type" class="<?php echo $inp; ?> mt-1"><?php foreach (RETUR_TYPE as $k => $l): ?><option value="<?php echo $k; ?>"><?php echo e($l); ?></option><?php endforeach; ?></select></div>
        <div><label class="text-xs font-bold text-gray-500 uppercase">Alasan</label><input name="reason" class="<?php echo $inp; ?> mt-1" placeholder="mis. Ukuran terlalu besar" required></div>
        <div class="md:col-span-3"><label class="text-xs font-bold text-gray-500 uppercase">Keterangan (opsional)</label><input name="detail" class="<?php echo $inp; ?> mt-1" placeholder="mis. Size L -> M"></div>
        <button class="bg-black text-white rounded-lg py-2 font-bold hover:bg-gray-800">Simpan</button>
        <p class="md:col-span-4 text-xs text-gray-400">Penukaran ukuran hanya berlaku 3 hari sejak barang diterima.</p>
    </form>
</details>

<section class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
    <table class="w-full text-left text-sm admin-table">
        <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase"><th class="px-5 py-3">Kode</th><th class="px-5 py-3">Pesanan</th><th class="px-5 py-3">Produk</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Alasan</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($rows as $r): [$lbl, $cls] = RETUR_STATUS[$r['status']]; ?>
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-semibold"><?php echo e($r['code']); ?><div class="text-xs text-gray-400 font-normal"><?php echo e(date_short($r['created_at'])); ?></div></td>
                <td class="px-5 py-3">#<?php echo e($r['order_code']); ?><div class="text-xs text-gray-400"><?php echo e($r['shipping_name']); ?></div></td>
                <td class="px-5 py-3"><?php echo e($r['product_name']); ?></td>
                <td class="px-5 py-3"><?php echo e(RETUR_TYPE[$r['type']]); ?></td>
                <td class="px-5 py-3 text-gray-600 max-w-[14rem]"><?php echo e($r['reason']); ?><?php if ($r['detail']): ?><div class="text-xs text-gray-400"><?php echo e($r['detail']); ?></div><?php endif; ?></td>
                <td class="px-5 py-3"><?php echo admin_pill($lbl, $cls); ?></td>
                <td class="px-5 py-3"><a href="admin_retur_detail.php?id=<?php echo (int)$r['id']; ?>" class="text-blue-600 bg-blue-50 hover:bg-blue-100 rounded px-2.5 py-1 text-xs font-medium">Detail</a></td>
            </tr>
        <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="px-5 py-10 text-center text-gray-500">Belum ada pengajuan.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php admin_foot();
