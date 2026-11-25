<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['code'] ?? ''));
        $type = ($_POST['type'] ?? '') === 'fixed' ? 'fixed' : 'percent';
        $value = (int)($_POST['value'] ?? 0);
        $min = max(0, (int)($_POST['min_purchase'] ?? 0));
        $max = ($_POST['max_discount'] ?? '') !== '' ? (int)$_POST['max_discount'] : null;
        $limit = ($_POST['usage_limit'] ?? '') !== '' ? (int)$_POST['usage_limit'] : null;
        $exp = ($_POST['expires_at'] ?? '') !== '' ? $_POST['expires_at'] : null;
        if ($code === '' || $value < 1 || ($type === 'percent' && $value > 100)) {
            flash('error', 'Kode wajib diisi dan nilai harus valid (persen maks. 100).');
        } else {
            $st = $conn->prepare("INSERT INTO vouchers (code, type, value, min_purchase, max_discount, usage_limit, expires_at) VALUES (?,?,?,?,?,?,?)");
            $st->bind_param('ssiiiis', $code, $type, $value, $min, $max, $limit, $exp);
            if ($st->execute()) flash('success', 'Voucher ' . $code . ' dibuat.'); else flash('error', 'Kode voucher sudah ada.');
        }
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $conn->query("UPDATE vouchers SET is_active = 1 - is_active WHERE id = $id");
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM vouchers WHERE id = $id");
        flash('success', 'Voucher dihapus.');
    }
    redirect('admin_voucher.php');
}
$vs = $conn->query("SELECT * FROM vouchers ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
admin_head('Voucher', 'voucher');
$inp = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-black bg-white';
?>
<div class="grid lg:grid-cols-3 gap-5">
    <section class="bg-white rounded-xl border border-gray-200 p-5 h-fit">
        <h2 class="font-bold mb-4">Buat Voucher</h2>
        <form method="post" class="space-y-3 text-sm"><?php echo csrf_field(); ?><input type="hidden" name="action" value="create">
            <div><label class="text-xs font-bold text-gray-500 uppercase">Kode</label><input name="code" class="<?php echo $inp; ?> mt-1 uppercase" required></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-xs font-bold text-gray-500 uppercase">Jenis</label><select name="type" class="<?php echo $inp; ?> mt-1"><option value="percent">Persen (%)</option><option value="fixed">Nominal (Rp)</option></select></div>
                <div><label class="text-xs font-bold text-gray-500 uppercase">Nilai</label><input type="number" name="value" min="1" class="<?php echo $inp; ?> mt-1" required></div>
            </div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Min. Belanja (Rp)</label><input type="number" name="min_purchase" min="0" value="0" class="<?php echo $inp; ?> mt-1"></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Maks. Potongan (Rp, utk persen)</label><input type="number" name="max_discount" min="0" class="<?php echo $inp; ?> mt-1"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-xs font-bold text-gray-500 uppercase">Kuota</label><input type="number" name="usage_limit" min="1" class="<?php echo $inp; ?> mt-1" placeholder="tak terbatas"></div>
                <div><label class="text-xs font-bold text-gray-500 uppercase">Berlaku s/d</label><input type="date" name="expires_at" class="<?php echo $inp; ?> mt-1"></div>
            </div>
            <button class="w-full bg-black text-white rounded-lg py-2.5 font-bold hover:bg-gray-800">Simpan Voucher</button>
        </form>
    </section>
    <section class="lg:col-span-2 bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table class="w-full text-left text-sm admin-table">
            <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase"><th class="px-5 py-3">Kode</th><th class="px-5 py-3">Potongan</th><th class="px-5 py-3">Min. Belanja</th><th class="px-5 py-3">Terpakai</th><th class="px-5 py-3">Berlaku</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($vs as $v): $expired = $v['expires_at'] && $v['expires_at'] < date('Y-m-d'); ?>
                <tr><td class="px-5 py-3 font-bold"><?php echo e($v['code']); ?></td>
                <td class="px-5 py-3"><?php echo $v['type'] === 'percent' ? (int)$v['value'] . '%' . ($v['max_discount'] ? ' (maks ' . e(rp($v['max_discount'])) . ')' : '') : e(rp($v['value'])); ?></td>
                <td class="px-5 py-3"><?php echo e(rp($v['min_purchase'])); ?></td>
                <td class="px-5 py-3"><?php echo (int)$v['used_count'] . ($v['usage_limit'] ? ' / ' . (int)$v['usage_limit'] : ''); ?></td>
                <td class="px-5 py-3 text-gray-500"><?php echo $v['expires_at'] ? e(date_short($v['expires_at'])) : 'Tanpa batas'; ?></td>
                <td class="px-5 py-3"><?php echo $expired ? '<span class="text-xs font-semibold px-2 py-1 rounded-full bg-gray-100 text-gray-500">Kedaluwarsa</span>' : ($v['is_active'] ? '<span class="text-xs font-semibold px-2 py-1 rounded-full bg-green-50 text-green-700">Aktif</span>' : '<span class="text-xs font-semibold px-2 py-1 rounded-full bg-gray-100 text-gray-500">Nonaktif</span>'); ?></td>
                <td class="px-5 py-3 whitespace-nowrap">
                    <form method="post" class="inline"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>"><button name="action" value="toggle" class="text-xs bg-gray-100 hover:bg-gray-200 rounded px-2 py-1 font-medium"><?php echo $v['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?></button></form>
                    <form method="post" class="inline" onsubmit="return confirm('Hapus voucher ini?')"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>"><button name="action" value="delete" class="text-xs bg-red-50 text-red-600 hover:bg-red-100 rounded px-2 py-1 font-medium">Hapus</button></form></td></tr>
            <?php endforeach; ?></tbody></table>
    </section>
</div>
<?php admin_foot();
