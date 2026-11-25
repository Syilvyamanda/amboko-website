<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';

const PAY_STATUS = [
    'pending'  => ['Menunggu', 'bg-amber-100 text-amber-800', 'pending'],
    'settled'  => ['Lunas',    'bg-green-100 text-green-800', 'paid'],
    'failed'   => ['Gagal',    'bg-red-100 text-red-700',     'failed'],
    'refunded' => ['Refund',   'bg-gray-100 text-gray-700',   'refunded'],
];
const PAY_TYPES = ['qris' => 'QRIS', 'va' => 'Virtual Account', 'card' => 'Kartu', 'cash' => 'Tunai'];

/** Samakan status pesanan dengan status pembayaran. */
function pay_sync_order(mysqli $conn, int $orderId, string $payStatus): void
{
    $os = PAY_STATUS[$payStatus][2];
    $conn->query("UPDATE orders SET payment_status = '$os' WHERE id = " . (int)$orderId);
    if ($payStatus === 'settled') {
        $cur = $conn->query("SELECT status FROM orders WHERE id = " . (int)$orderId)->fetch_row()[0] ?? '';
        if ($cur === 'pending_payment') {
            $conn->query("UPDATE orders SET status = 'packing' WHERE id = " . (int)$orderId);
            add_order_history($conn, $orderId, 'packing', 'Pembayaran dikonfirmasi admin');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'add_payment') {
        $code = strtoupper(trim($_POST['order_code'] ?? ''));
        $mid = (int)($_POST['method_id'] ?? 0);
        $status = array_key_exists($_POST['status'] ?? '', PAY_STATUS) ? $_POST['status'] : 'settled';
        $amount = (float)($_POST['amount'] ?? 0);
        $st = $conn->prepare("SELECT id, total_amount FROM orders WHERE order_code = ?");
        $st->bind_param('s', $code); $st->execute();
        $o = $st->get_result()->fetch_assoc();
        $m = $conn->query("SELECT * FROM payment_methods WHERE id = $mid")->fetch_assoc();
        if (!$o) { flash('error', 'Kode pesanan tidak ditemukan.'); }
        elseif (!$m) { flash('error', 'Pilih metode pembayaran.'); }
        else {
            if ($amount <= 0) $amount = (float)$o['total_amount'];
            $trx = 'TRX-' . date('ymd') . random_int(1000, 9999);
            $paidAt = $status === 'settled' ? date('Y-m-d H:i:s') : null;
            $ins = $conn->prepare("INSERT INTO payments (order_id, method, channel, channel_detail, transaction_id, status, amount, paid_at) VALUES (?,?,?,?,?,?,?,?)");
            $oid = (int)$o['id'];
            $ins->bind_param('isssssds', $oid, $m['type'], $m['name'], $m['detail'], $trx, $status, $amount, $paidAt);
            $ins->execute();
            pay_sync_order($conn, $oid, $status);
            flash('success', 'Pembayaran untuk #' . $code . ' dicatat.');
        }
    } elseif ($action === 'set_status' && $id && array_key_exists($_POST['status'] ?? '', PAY_STATUS)) {
        $status = $_POST['status'];
        $row = $conn->query("SELECT order_id FROM payments WHERE id = $id")->fetch_assoc();
        if ($row) {
            $paid = $status === 'settled' ? date('Y-m-d H:i:s') : null;
            $up = $conn->prepare("UPDATE payments SET status = ?, paid_at = COALESCE(?, paid_at) WHERE id = ?");
            $up->bind_param('ssi', $status, $paid, $id); $up->execute();
            pay_sync_order($conn, (int)$row['order_id'], $status);
            flash('success', 'Status pembayaran diperbarui.');
        }
    } elseif ($action === 'delete_payment' && $id) {
        $row = $conn->query("SELECT status FROM payments WHERE id = $id")->fetch_assoc();
        if ($row && in_array($row['status'], ['pending', 'failed'], true)) { $conn->query("DELETE FROM payments WHERE id = $id"); flash('success', 'Data pembayaran dihapus.'); }
        else flash('error', 'Pembayaran lunas / refund tidak bisa dihapus. Ubah statusnya dulu.');
    } elseif ($action === 'save_method') {
        $name = trim($_POST['name'] ?? '');
        $type = array_key_exists($_POST['type'] ?? '', PAY_TYPES) ? $_POST['type'] : 'qris';
        $detail = trim($_POST['detail'] ?? '') ?: null;
        $on = !empty($_POST['is_active']) ? 1 : 0;
        if ($name === '') flash('error', 'Nama metode wajib diisi.');
        elseif ($id) {
            $st = $conn->prepare("UPDATE payment_methods SET name=?, type=?, detail=?, is_active=? WHERE id=?");
            $st->bind_param('sssii', $name, $type, $detail, $on, $id); $st->execute(); flash('success', 'Metode diperbarui.');
        } else {
            $st = $conn->prepare("INSERT INTO payment_methods (name, type, detail, is_active) VALUES (?,?,?,?)");
            $st->bind_param('sssi', $name, $type, $detail, $on); $st->execute(); flash('success', 'Metode ditambahkan.');
        }
    } elseif ($action === 'save_pay_settings') {
        $err = null;
        $qr = save_upload($_FILES['qris_image'] ?? [], $err);
        if ($err) { flash('error', $err); redirect('admin_pembayaran.php'); }
        $vals = [];
        if ($qr) $vals['qris_image'] = $qr;
        foreach (VA_BANKS as $bk => $_) {
            $v = preg_replace('/\D/', '', (string)($_POST['va_' . $bk] ?? ''));
            if ($v !== '' && (strlen($v) < 3 || strlen($v) > 10)) { flash('error', 'Kode VA ' . VA_BANKS[$bk][0] . ' harus 3-10 digit angka.'); redirect('admin_pembayaran.php'); }
            $vals['va_' . $bk] = $v;
        }
        $sv = $conn->prepare("INSERT INTO site_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)");
        foreach ($vals as $k => $v) { $sv->bind_param('ss', $k, $v); $sv->execute(); }
        flash('success', 'Pengaturan QRIS & Virtual Account disimpan.');
    } elseif ($action === 'reset_qris') {
        $conn->query("INSERT INTO site_settings (k, v) VALUES ('qris_image', '') ON DUPLICATE KEY UPDATE v = ''");
        flash('success', 'QRIS dikembalikan ke gambar contoh.');
    } elseif ($action === 'delete_method' && $id) {
        $conn->query("DELETE FROM payment_methods WHERE id = $id"); flash('success', 'Metode dihapus.');
    }
    redirect('admin_pembayaran.php');
}

$fs = $_GET['status'] ?? '';
$sql = "SELECT p.*, o.order_code, o.channel AS order_channel, o.shipping_name FROM payments p JOIN orders o ON o.id = p.order_id"
     . (isset(PAY_STATUS[$fs]) ? " WHERE p.status = '" . $conn->real_escape_string($fs) . "'" : '') . " ORDER BY p.id DESC LIMIT 200";
$pays = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
$counts = [];
$r = $conn->query("SELECT status, COUNT(*) n FROM payments GROUP BY status");
while ($row = $r->fetch_assoc()) $counts[$row['status']] = (int)$row['n'];
$methods = $conn->query("SELECT * FROM payment_methods ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$editM = null;
if (!empty($_GET['edit_method'])) { $eid = (int)$_GET['edit_method']; $editM = $conn->query("SELECT * FROM payment_methods WHERE id = $eid")->fetch_assoc() ?: null; }

admin_head('Pembayaran', 'pembayaran');
$inp = ADMIN_INP;
?>
<div class="flex flex-wrap items-center gap-2 mb-4">
    <a href="admin_pembayaran.php" class="px-3.5 py-1.5 rounded-full text-sm font-medium <?php echo $fs === '' ? 'bg-black text-white' : 'bg-white border border-gray-200 hover:bg-gray-100'; ?>">Semua</a>
    <?php foreach (PAY_STATUS as $k => [$label]): ?>
        <a href="?status=<?php echo $k; ?>" class="px-3.5 py-1.5 rounded-full text-sm font-medium <?php echo $fs === $k ? 'bg-black text-white' : 'bg-white border border-gray-200 hover:bg-gray-100'; ?>"><?php echo e($label); ?> <span class="opacity-60"><?php echo $counts[$k] ?? 0; ?></span></a>
    <?php endforeach; ?>
</div>

<details class="bg-white rounded-xl border border-gray-200 mb-5">
    <summary class="px-5 py-3.5 cursor-pointer font-bold text-sm"><i class="fa-solid fa-plus mr-2"></i>Tambah Pembayaran</summary>
    <form method="post" class="px-5 pb-5 grid sm:grid-cols-5 gap-3 text-sm items-end">
        <?php echo csrf_field(); ?><input type="hidden" name="action" value="add_payment">
        <div><label class="text-xs font-bold text-gray-500 uppercase">Kode Pesanan</label><input name="order_code" class="<?php echo $inp; ?> mt-1 uppercase" placeholder="AMB-040701" required></div>
        <div><label class="text-xs font-bold text-gray-500 uppercase">Metode</label>
            <select name="method_id" class="<?php echo $inp; ?> mt-1" required><option value="">- pilih -</option>
                <?php foreach ($methods as $m) if ($m['is_active']): ?><option value="<?php echo (int)$m['id']; ?>"><?php echo e($m['name']); ?></option><?php endif; ?></select></div>
        <div><label class="text-xs font-bold text-gray-500 uppercase">Jumlah (Rp)</label><input type="number" name="amount" min="0" class="<?php echo $inp; ?> mt-1" placeholder="otomatis = total"></div>
        <div><label class="text-xs font-bold text-gray-500 uppercase">Status</label>
            <select name="status" class="<?php echo $inp; ?> mt-1"><?php foreach (PAY_STATUS as $k => [$label]): ?><option value="<?php echo $k; ?>" <?php echo $k === 'settled' ? 'selected' : ''; ?>><?php echo e($label); ?></option><?php endforeach; ?></select></div>
        <button class="bg-black text-white rounded-lg py-2 font-bold hover:bg-gray-800">Simpan</button>
    </form>
</details>

<section class="bg-white rounded-xl border border-gray-200 overflow-x-auto mb-8">
    <div class="px-5 py-4 border-b border-gray-100"><h2 class="font-bold">Daftar Pembayaran</h2></div>
    <table class="w-full text-left text-sm admin-table">
        <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase"><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Pesanan</th><th class="px-5 py-3">Metode</th><th class="px-5 py-3 text-right">Jumlah</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($pays as $p): [$lbl, $cls] = PAY_STATUS[$p['status']]; ?>
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 text-gray-500 whitespace-nowrap"><?php echo e(date_short($p['paid_at'] ?: $p['created_at'])); ?> <span class="text-xs"><?php echo date('H:i', strtotime($p['paid_at'] ?: $p['created_at'])); ?></span></td>
                <td class="px-5 py-3 font-semibold"><a class="hover:underline" href="admin_pesanan.php?q=<?php echo urlencode($p['order_code']); ?>">#<?php echo e($p['order_code']); ?></a> <?php echo $p['order_channel'] === 'offline' ? channel_badge('offline') : ''; ?><div class="text-xs text-gray-400 font-normal"><?php echo e($p['shipping_name']); ?></div></td>
                <td class="px-5 py-3"><?php echo e($p['channel']); ?><div class="text-xs text-gray-400"><?php echo e($p['channel_detail']); ?></div></td>
                <td class="px-5 py-3 text-right font-semibold"><?php echo e(rp($p['amount'])); ?></td>
                <td class="px-5 py-3">
                    <form method="post" class="inline"><?php echo csrf_field(); ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                        <select name="status" onchange="this.form.submit()" class="text-xs font-semibold rounded-full px-2.5 py-1 border-0 <?php echo $cls; ?>">
                            <?php foreach (PAY_STATUS as $k => [$l2]): ?><option value="<?php echo $k; ?>" <?php echo $k === $p['status'] ? 'selected' : ''; ?>><?php echo e($l2); ?></option><?php endforeach; ?>
                        </select></form></td>
                <td class="px-5 py-3 text-right">
                    <?php if (in_array($p['status'], ['pending', 'failed'], true)): ?>
                    <form method="post" class="inline" onsubmit="return confirm('Hapus data pembayaran ini?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_payment"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>"><button class="text-red-500 font-medium text-xs bg-red-50 px-2 py-1 rounded">Hapus</button></form>
                    <?php endif; ?></td>
            </tr>
        <?php endforeach; if (!$pays): ?><tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">Belum ada pembayaran.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>

<h2 class="text-lg font-bold mb-3">QRIS &amp; Virtual Account</h2>
<form method="post" enctype="multipart/form-data" class="grid lg:grid-cols-2 gap-5 mb-8">
    <?php echo csrf_field(); ?><input type="hidden" name="action" value="save_pay_settings">
    <section class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-bold mb-1">Kode QRIS</h3>
        <p class="text-xs text-gray-500 mb-4">Gambar ini tampil di halaman pembayaran pelanggan. Unggah gambar QRIS asli toko Anda (dari bank / GoPay Merchant / dll) untuk mengganti.</p>
        <div class="flex items-center gap-5 text-sm">
            <img src="<?php echo e(qris_image()); ?>" alt="QRIS aktif" class="w-36 h-36 object-contain rounded-lg bg-gray-50 border border-gray-200 p-1">
            <div class="flex-1 min-w-0">
                <input type="file" name="qris_image" accept="image/jpeg,image/png,image/webp" class="<?php echo $inp; ?>">
                <p class="text-[11px] text-gray-400 mt-1">JPG / PNG / WEBP, maks. 4 MB. Kosongkan jika tidak diganti.</p>
                <?php if (site_setting('qris_image') === ''): ?><p class="text-[11px] text-amber-700 bg-amber-50 rounded px-2 py-1 mt-2">Sekarang memakai QRIS contoh. Ganti dengan QRIS asli sebelum dipakai sungguhan.</p><?php endif; ?>
            </div>
        </div>
    </section>
    <section class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-bold mb-1">Kode Virtual Account</h3>
        <p class="text-xs text-gray-500 mb-4">Nomor VA pelanggan = kode bank di bawah + nomor pesanan (total 16 digit). Isi kode yang diberikan bank / payment gateway Anda.</p>
        <div class="grid sm:grid-cols-2 gap-3 text-sm">
            <?php foreach (VA_BANKS as $bk => [$bn]): ?>
            <div><label class="text-xs font-bold text-gray-500 uppercase"><?php echo e($bn); ?></label>
                <input name="va_<?php echo e($bk); ?>" value="<?php echo e(preg_replace('/\D/', '', site_setting('va_' . $bk, VA_BANKS[$bk][1]))); ?>" inputmode="numeric" maxlength="10" class="<?php echo $inp; ?> mt-1" placeholder="mis. <?php echo e(VA_BANKS[$bk][1]); ?>">
                <p class="text-[11px] text-gray-400 mt-1">Contoh nomor: <?php echo e(va_number($bk, 123)); ?></p></div>
            <?php endforeach; ?>
        </div>
    </section>
    <div class="lg:col-span-2 flex flex-wrap items-center gap-3">
        <button class="bg-black text-white rounded-lg px-8 py-2.5 font-bold hover:bg-gray-800">Simpan QRIS &amp; VA</button>
        <?php if (site_setting('qris_image') !== ''): ?><button name="action" value="reset_qris" formnovalidate class="px-4 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 font-medium text-sm">Kembalikan QRIS contoh</button><?php endif; ?>
        <span class="text-xs text-gray-400">Pelanggan membayar, lalu admin menekan <b>Tandai Sudah Dibayar</b> di detail pesanan.</span>
    </div>
</form>

<h2 class="text-lg font-bold mb-3">Metode Pembayaran</h2>
<div class="grid lg:grid-cols-3 gap-5">
    <section class="bg-white rounded-xl border border-gray-200 p-5 h-fit">
        <h3 class="font-bold mb-4"><?php echo $editM ? 'Edit Metode' : 'Tambah Metode'; ?></h3>
        <form method="post" class="space-y-3 text-sm">
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="save_method"><input type="hidden" name="id" value="<?php echo (int)($editM['id'] ?? 0); ?>">
            <div><label class="text-xs font-bold text-gray-500 uppercase">Nama</label><input name="name" value="<?php echo e($editM['name'] ?? ''); ?>" class="<?php echo $inp; ?> mt-1" required></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Jenis</label>
                <select name="type" class="<?php echo $inp; ?> mt-1"><?php foreach (PAY_TYPES as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo ($editM['type'] ?? 'qris') === $k ? 'selected' : ''; ?>><?php echo e($l); ?></option><?php endforeach; ?></select></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Keterangan</label><input name="detail" value="<?php echo e($editM['detail'] ?? ''); ?>" class="<?php echo $inp; ?> mt-1" placeholder="mis. BCA, Mandiri"></div>
            <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" <?php echo !$editM || !empty($editM['is_active']) ? 'checked' : ''; ?>> Aktif</label>
            <div class="flex gap-2"><button class="flex-1 bg-black text-white rounded-lg py-2.5 font-bold hover:bg-gray-800">Simpan</button>
                <?php if ($editM): ?><a href="admin_pembayaran.php" class="px-4 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 font-medium">Batal</a><?php endif; ?></div>
        </form>
    </section>
    <section class="lg:col-span-2 bg-white rounded-xl border border-gray-200 overflow-x-auto h-fit">
        <table class="w-full text-left text-sm admin-table">
            <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase"><th class="px-5 py-3">Metode</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($methods as $m): ?>
                <tr class="hover:bg-gray-50"><td class="px-5 py-3"><b><?php echo e($m['name']); ?></b><div class="text-xs text-gray-400"><?php echo e($m['detail']); ?></div></td>
                    <td class="px-5 py-3 text-gray-600"><?php echo e(PAY_TYPES[$m['type']] ?? $m['type']); ?></td>
                    <td class="px-5 py-3"><?php echo $m['is_active'] ? admin_pill('Aktif', 'bg-green-50 text-green-700') : admin_pill('Nonaktif'); ?></td>
                    <td class="px-5 py-3 whitespace-nowrap text-right"><a href="?edit_method=<?php echo (int)$m['id']; ?>" class="text-blue-600 font-medium text-xs bg-blue-50 px-2 py-1 rounded">Edit</a>
                        <form method="post" class="inline" onsubmit="return confirm('Hapus metode ini?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_method"><input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>"><button class="text-red-500 font-medium text-xs bg-red-50 px-2 py-1 rounded">Hapus</button></form></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
<p class="text-xs text-gray-400 mt-3">Daftar metode di atas adalah data master admin; pilihan bayar di halaman checkout tetap QRIS, Virtual Account, dan Kartu.</p>
<?php admin_foot();
