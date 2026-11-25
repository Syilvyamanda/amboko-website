<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';
include 'includes/admin_retur_consts.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$r = $conn->query("SELECT r.*, o.order_code, o.shipping_name, o.shipping_phone, o.channel, o.total_amount FROM order_returns r JOIN orders o ON o.id = r.order_id WHERE r.id = $id")->fetch_assoc();
if (!$r) { flash('error', 'Data tidak ditemukan.'); redirect('admin_retur.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $status = array_key_exists($_POST['status'] ?? '', RETUR_STATUS) ? $_POST['status'] : $r['status'];
        $note = trim($_POST['admin_note'] ?? '') ?: null;
        $refund = $r['type'] === 'return' ? max(0, (float)($_POST['refund_amount'] ?? 0)) : 0;
        $now = date('Y-m-d H:i:s');
        $up = $conn->prepare("UPDATE order_returns SET status = ?, admin_note = ?, refund_amount = ?, updated_at = ? WHERE id = ?");
        $up->bind_param('ssdsi', $status, $note, $refund, $now, $id);
        $up->execute();
        flash('success', 'Pengajuan diperbarui.');
        redirect('admin_retur_detail.php?id=' . $id);
    } elseif ($action === 'delete') {
        $conn->query("DELETE FROM order_returns WHERE id = $id");
        flash('success', 'Pengajuan dihapus.');
        redirect('admin_retur.php');
    }
}
[$lbl, $cls] = RETUR_STATUS[$r['status']];
admin_head($r['code'], 'retur');
$inp = ADMIN_INP;
?>
<a href="admin_retur.php" class="text-sm text-gray-500 hover:text-black"><i class="fa-solid fa-arrow-left mr-1"></i> Semua pengajuan</a>
<div class="flex flex-wrap items-center justify-between gap-3 mt-2 mb-6">
    <div><h1 class="text-2xl font-bold"><?php echo e($r['code']); ?></h1><p class="text-sm text-gray-500">Diajukan <?php echo e(date_long($r['created_at'])); ?>, <?php echo date('H:i', strtotime($r['created_at'])); ?> WIB</p></div>
    <?php echo admin_pill($lbl, $cls); ?>
</div>
<div class="grid lg:grid-cols-3 gap-5">
    <section class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5 text-sm">
        <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-4">
            <div><dt class="text-xs font-bold text-gray-500 uppercase">Jenis</dt><dd class="mt-0.5 font-semibold"><?php echo e(RETUR_TYPE[$r['type']]); ?></dd></div>
            <div><dt class="text-xs font-bold text-gray-500 uppercase">Produk</dt><dd class="mt-0.5 font-semibold"><?php echo e($r['product_name']); ?></dd></div>
            <div><dt class="text-xs font-bold text-gray-500 uppercase">Alasan</dt><dd class="mt-0.5"><?php echo e($r['reason']); ?></dd></div>
            <div><dt class="text-xs font-bold text-gray-500 uppercase">Keterangan</dt><dd class="mt-0.5"><?php echo e($r['detail'] ?: '-'); ?></dd></div>
            <div><dt class="text-xs font-bold text-gray-500 uppercase">Pesanan</dt><dd class="mt-0.5"><a class="text-brand font-semibold hover:underline" href="admin_pesanan.php?q=<?php echo urlencode($r['order_code']); ?>">#<?php echo e($r['order_code']); ?></a> <?php echo channel_badge($r['channel']); ?></dd></div>
            <div><dt class="text-xs font-bold text-gray-500 uppercase">Pelanggan</dt><dd class="mt-0.5"><?php echo e($r['shipping_name']); ?><div class="text-xs text-gray-400"><?php echo e($r['shipping_phone']); ?></div></dd></div>
            <div><dt class="text-xs font-bold text-gray-500 uppercase">Terakhir diubah</dt><dd class="mt-0.5"><?php echo e(date_short($r['updated_at'])); ?> <?php echo date('H:i', strtotime($r['updated_at'])); ?></dd></div>
            <?php if ($r['type'] === 'return'): ?><div><dt class="text-xs font-bold text-gray-500 uppercase">Nominal refund</dt><dd class="mt-0.5 font-semibold"><?php echo e(rp($r['refund_amount'])); ?></dd></div><?php endif; ?>
        </dl>
    </section>
    <aside class="bg-white rounded-xl border border-gray-200 p-5 h-fit">
        <h2 class="font-bold mb-3">Ubah Status</h2>
        <form method="post" class="space-y-3 text-sm"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo $id; ?>">
            <select name="status" class="<?php echo $inp; ?>"><?php foreach (RETUR_STATUS as $k => [$l]): ?><option value="<?php echo $k; ?>" <?php echo $k === $r['status'] ? 'selected' : ''; ?>><?php echo e($l); ?></option><?php endforeach; ?></select>
            <?php if ($r['type'] === 'return'): ?><div><label class="text-xs font-bold text-gray-500 uppercase">Nominal refund (Rp)</label><input type="number" name="refund_amount" min="0" step="1" value="<?php echo (int)$r['refund_amount']; ?>" class="<?php echo $inp; ?> mt-1"></div><?php endif; ?>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Catatan admin</label><textarea name="admin_note" rows="3" class="<?php echo $inp; ?> mt-1"><?php echo e($r['admin_note']); ?></textarea></div>
            <button class="w-full bg-black text-white rounded-lg py-2.5 font-bold hover:bg-gray-800">Simpan</button>
        </form>
        <form method="post" class="mt-3" onsubmit="return confirm('Hapus pengajuan ini?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $id; ?>"><button class="w-full border border-red-200 text-red-600 rounded-lg py-2 text-sm font-medium hover:bg-red-50">Hapus pengajuan</button></form>
    </aside>
</div>
<?php admin_foot();
