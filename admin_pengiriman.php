<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'save') {
        $name = trim($_POST['name'] ?? '');
        $cost = max(0, (int)($_POST['cost'] ?? 0));
        $free = ($_POST['free_min'] ?? '') !== '' ? max(0, (int)$_POST['free_min']) : null;
        $est = trim($_POST['estimate'] ?? '') ?: null;
        $note = trim($_POST['note'] ?? '') ?: null;
        $on = !empty($_POST['is_active']) ? 1 : 0;
        if ($name === '') { flash('error', 'Nama pengiriman wajib diisi.'); }
        elseif ($id) {
            $st = $conn->prepare("UPDATE shipping_methods SET name=?, cost=?, free_min=?, estimate=?, note=?, is_active=? WHERE id=?");
            $st->bind_param('siissii', $name, $cost, $free, $est, $note, $on, $id);
            $st->execute(); flash('success', 'Pengiriman diperbarui.');
        } else {
            $st = $conn->prepare("INSERT INTO shipping_methods (name, cost, free_min, estimate, note, is_active) VALUES (?,?,?,?,?,?)");
            $st->bind_param('siissi', $name, $cost, $free, $est, $note, $on);
            $st->execute(); flash('success', 'Pengiriman ditambahkan.');
        }
    } elseif ($action === 'delete' && $id) {
        $conn->query("DELETE FROM shipping_methods WHERE id = $id");
        flash('success', 'Pengiriman dihapus.');
    }
    redirect('admin_pengiriman.php');
}

$edit = null;
if (!empty($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $edit = $conn->query("SELECT * FROM shipping_methods WHERE id = $eid")->fetch_assoc() ?: null;
}
$methods = $conn->query("SELECT * FROM shipping_methods ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$ships = $conn->query("SELECT o.*, (SELECT SUM(qty) FROM order_items WHERE order_id = o.id) pcs FROM orders o
    WHERE o.channel = 'online' AND o.status IN ('placed','packing','shipped','delivered') ORDER BY FIELD(o.status,'packing','placed','shipped','delivered'), o.id DESC LIMIT 40")->fetch_all(MYSQLI_ASSOC);

admin_head('Pengiriman', 'pengiriman');
$inp = ADMIN_INP;
?>
<div class="grid lg:grid-cols-3 gap-5 mb-8">
    <section class="bg-white rounded-xl border border-gray-200 p-5 h-fit">
        <h2 class="font-bold mb-4"><?php echo $edit ? 'Edit Pengiriman' : 'Tambah Pengiriman'; ?></h2>
        <form method="post" class="space-y-3 text-sm">
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
            <div><label class="text-xs font-bold text-gray-500 uppercase">Nama</label><input name="name" value="<?php echo e($edit['name'] ?? ''); ?>" class="<?php echo $inp; ?> mt-1" required></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-xs font-bold text-gray-500 uppercase">Biaya (Rp)</label><input type="number" name="cost" min="0" value="<?php echo e($edit['cost'] ?? 0); ?>" class="<?php echo $inp; ?> mt-1" required></div>
                <div><label class="text-xs font-bold text-gray-500 uppercase">Gratis mulai (Rp)</label><input type="number" name="free_min" min="0" value="<?php echo e($edit['free_min'] ?? ''); ?>" class="<?php echo $inp; ?> mt-1" placeholder="tidak ada"></div>
            </div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Estimasi</label><input name="estimate" value="<?php echo e($edit['estimate'] ?? ''); ?>" class="<?php echo $inp; ?> mt-1" placeholder="mis. 3-4 hari kerja"></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Catatan</label><input name="note" value="<?php echo e($edit['note'] ?? ''); ?>" class="<?php echo $inp; ?> mt-1"></div>
            <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" <?php echo !$edit || !empty($edit['is_active']) ? 'checked' : ''; ?>> Aktif</label>
            <div class="flex gap-2">
                <button class="flex-1 bg-black text-white rounded-lg py-2.5 font-bold hover:bg-gray-800">Simpan</button>
                <?php if ($edit): ?><a href="admin_pengiriman.php" class="px-4 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 font-medium">Batal</a><?php endif; ?>
            </div>
        </form>
    </section>

    <section class="lg:col-span-2 bg-white rounded-xl border border-gray-200 overflow-x-auto h-fit">
        <div class="px-5 py-4 border-b border-gray-100"><h2 class="font-bold">Daftar Pengiriman &amp; Biaya</h2></div>
        <table class="w-full text-left text-sm admin-table">
            <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase"><th class="px-5 py-3">Nama</th><th class="px-5 py-3">Biaya</th><th class="px-5 py-3">Estimasi</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($methods as $m): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3"><b><?php echo e($m['name']); ?></b><?php if ($m['note']): ?><div class="text-xs text-gray-400"><?php echo e($m['note']); ?></div><?php endif; ?></td>
                    <td class="px-5 py-3 font-semibold"><?php echo $m['cost'] ? e(rp($m['cost'])) : 'Gratis'; ?><?php if ($m['free_min'] !== null): ?><div class="text-xs text-gray-400 font-normal">Gratis &ge; <?php echo e(rp($m['free_min'])); ?></div><?php endif; ?></td>
                    <td class="px-5 py-3 text-gray-600"><?php echo e($m['estimate'] ?: '-'); ?></td>
                    <td class="px-5 py-3"><?php echo $m['is_active'] ? admin_pill('Aktif', 'bg-green-50 text-green-700') : admin_pill('Nonaktif'); ?></td>
                    <td class="px-5 py-3 whitespace-nowrap text-right">
                        <a href="?edit=<?php echo (int)$m['id']; ?>" class="text-blue-600 font-medium text-xs bg-blue-50 px-2 py-1 rounded">Edit</a>
                        <form method="post" class="inline" onsubmit="return confirm('Hapus pengiriman ini?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>"><button class="text-red-500 font-medium text-xs bg-red-50 px-2 py-1 rounded">Hapus</button></form></td>
                </tr>
            <?php endforeach; if (!$methods): ?><tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">Belum ada data pengiriman.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<section class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between"><h2 class="font-bold">Pengiriman Pesanan Website</h2><span class="text-xs text-gray-400">No. resi diisi di halaman detail pesanan</span></div>
    <table class="w-full text-left text-sm admin-table">
        <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase"><th class="px-5 py-3">Pesanan</th><th class="px-5 py-3">Penerima</th><th class="px-5 py-3">Layanan</th><th class="px-5 py-3">Ongkir</th><th class="px-5 py-3">No. Resi</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($ships as $o): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-semibold">#<?php echo e($o['order_code']); ?><div class="text-xs text-gray-400 font-normal"><?php echo e(date_short($o['created_at'])); ?> &bull; <?php echo (int)$o['pcs']; ?> pcs</div></td>
                <td class="px-5 py-3"><?php echo e($o['shipping_name']); ?><div class="text-xs text-gray-400"><?php echo e($o['shipping_city']); ?></div></td>
                <td class="px-5 py-3"><?php echo $o['courier'] === 'express' ? 'Express' : 'Reguler'; ?></td>
                <td class="px-5 py-3"><?php echo $o['shipping_cost'] > 0 ? e(rp($o['shipping_cost'])) : 'Gratis'; ?></td>
                <td class="px-5 py-3 text-gray-600"><?php echo e($o['tracking_number'] ?: '-'); ?></td>
                <td class="px-5 py-3"><?php echo order_status_badge($o['status']); ?></td>
                <td class="px-5 py-3"><a href="admin_pesanan_detail.php?id=<?php echo (int)$o['id']; ?>" class="text-blue-600 bg-blue-50 hover:bg-blue-100 rounded px-2.5 py-1 text-xs font-medium">Detail</a></td>
            </tr>
        <?php endforeach; if (!$ships): ?><tr><td colspan="7" class="px-5 py-8 text-center text-gray-500">Belum ada pengiriman.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php admin_foot();
