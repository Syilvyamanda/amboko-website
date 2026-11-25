<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';

const EXP_CATS = ['Produksi', 'Bahan Baku', 'Packaging', 'Iklan', 'Operasional', 'Gaji', 'Lainnya'];

$tab = in_array($_GET['tab'] ?? '', ['ringkasan', 'pendapatan', 'pengeluaran'], true) ? $_GET['tab'] : 'ringkasan';
$minYear = (int)$conn->query("SELECT LEAST(COALESCE((SELECT MIN(YEAR(created_at)) FROM orders), YEAR(CURDATE())), COALESCE((SELECT MIN(YEAR(spent_at)) FROM expenses), YEAR(CURDATE())))")->fetch_row()[0];
$thisYear = (int)date('Y');
$y = (int)($_GET['y'] ?? $thisYear);
if ($y < $minYear || $y > $thisYear) $y = $thisYear;
$m = (int)($_GET['m'] ?? 0);
if ($m < 0 || $m > 12) $m = 0;
$keep = ['tab' => $tab, 'y' => $y, 'm' => $m];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'save_expense') {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['spent_at'] ?? '') ? $_POST['spent_at'] : date('Y-m-d');
        $cat = trim($_POST['category'] ?? '') ?: 'Lainnya';
        $note = trim($_POST['note'] ?? '') ?: null;
        $amount = (int)($_POST['amount'] ?? 0);
        if ($amount < 1) flash('error', 'Jumlah pengeluaran harus lebih dari 0.');
        elseif ($id) {
            $st = $conn->prepare("UPDATE expenses SET spent_at=?, category=?, note=?, amount=? WHERE id=?");
            $st->bind_param('sssii', $date, $cat, $note, $amount, $id); $st->execute(); flash('success', 'Pengeluaran diperbarui.');
        } else {
            $st = $conn->prepare("INSERT INTO expenses (spent_at, category, note, amount) VALUES (?,?,?,?)");
            $st->bind_param('sssi', $date, $cat, $note, $amount); $st->execute(); flash('success', 'Pengeluaran dicatat.');
        }
    } elseif ($action === 'delete_expense' && $id) {
        $conn->query("DELETE FROM expenses WHERE id = $id"); flash('success', 'Pengeluaran dihapus.');
    }
    redirect('admin_keuangan.php?' . http_build_query(['tab' => 'pengeluaran', 'y' => $y, 'm' => $m]));
}

// rentang periode terpilih
$from = sprintf('%04d-%02d-01', $y, $m ?: 1);
$to = $m ? date('Y-m-t', strtotime($from)) : sprintf('%04d-12-31', $y);

$incMonth = array_fill(1, 12, 0.0); $expMonth = array_fill(1, 12, 0.0);
$r = $conn->query("SELECT MONTH(created_at) mo, SUM(total_amount) s FROM orders WHERE payment_status = 'paid' AND status <> 'cancelled' AND YEAR(created_at) = $y GROUP BY mo");
while ($row = $r->fetch_assoc()) $incMonth[(int)$row['mo']] = (float)$row['s'];
$r = $conn->query("SELECT MONTH(spent_at) mo, SUM(amount) s FROM expenses WHERE YEAR(spent_at) = $y GROUP BY mo");
while ($row = $r->fetch_assoc()) $expMonth[(int)$row['mo']] = (float)$row['s'];
$income = $m ? $incMonth[$m] : array_sum($incMonth);
$expense = $m ? $expMonth[$m] : array_sum($expMonth);
$profit = $income - $expense;
$periodLabel = ($m ? ID_MONTHS[$m] . ' ' : '') . $y;

admin_head('Keuangan', 'keuangan');
$inp = ADMIN_INP;
?>
<form method="get" class="flex flex-wrap items-center gap-2 mb-4 no-print">
    <input type="hidden" name="tab" value="<?php echo e($tab); ?>">
    <select name="y" onchange="this.form.submit()" class="<?php echo $inp; ?> !w-28"><?php for ($yy = $thisYear; $yy >= $minYear; $yy--): ?><option <?php echo $yy === $y ? 'selected' : ''; ?>><?php echo $yy; ?></option><?php endfor; ?></select>
    <select name="m" onchange="this.form.submit()" class="<?php echo $inp; ?> !w-40"><option value="0">Setahun penuh</option><?php for ($mm = 1; $mm <= 12; $mm++): ?><option value="<?php echo $mm; ?>" <?php echo $mm === $m ? 'selected' : ''; ?>><?php echo ID_MONTHS[$mm]; ?></option><?php endfor; ?></select>
    <button type="button" onclick="window.print()" class="ml-auto border border-gray-300 bg-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-100"><i class="fa-solid fa-print mr-2"></i>Cetak</button>
</form>
<div class="grid sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5"><p class="text-xs text-gray-500 font-medium">Pendapatan &bull; <?php echo e($periodLabel); ?></p><p class="font-head font-extrabold text-2xl mt-1"><?php echo e(rp($income)); ?></p></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><p class="text-xs text-gray-500 font-medium">Pengeluaran</p><p class="font-head font-extrabold text-2xl mt-1"><?php echo e(rp($expense)); ?></p></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><p class="text-xs text-gray-500 font-medium"><?php echo $profit >= 0 ? 'Laba' : 'Rugi'; ?></p><p class="font-head font-extrabold text-2xl mt-1 <?php echo $profit >= 0 ? 'text-green-600' : 'text-red-600'; ?>"><?php echo e(rp(abs($profit))); ?></p></div>
</div>
<?php
echo admin_tabs(['ringkasan' => 'Laporan Keuangan', 'pendapatan' => 'Pendapatan', 'pengeluaran' => 'Pengeluaran'], $tab, ['y' => $y, 'm' => $m]);

/* ========================= RINGKASAN ========================= */
if ($tab === 'ringkasan'): ?>
<section class="bg-white rounded-xl border border-gray-200 p-5 mb-5">
    <h2 class="font-bold mb-4">Pendapatan per Bulan <?php echo $y; ?></h2>
    <?php $bars = []; for ($i = 1; $i <= 12; $i++) $bars[ID_MONTHS_SHORT[$i]] = (int)$incMonth[$i]; echo admin_bars($bars, fn($v) => number_format($v / 1000, 0, ',', '.') . 'k'); ?>
</section>
<section class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
    <table class="w-full text-left text-sm admin-table">
        <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase"><th class="px-5 py-3">Bulan</th><th class="px-5 py-3 text-right">Pendapatan</th><th class="px-5 py-3 text-right">Pengeluaran</th><th class="px-5 py-3 text-right">Laba / Rugi</th></tr></thead>
        <tbody class="divide-y divide-gray-100">
        <?php for ($i = 1; $i <= 12; $i++): $p = $incMonth[$i] - $expMonth[$i]; if (!$incMonth[$i] && !$expMonth[$i]) continue; ?>
            <tr class="hover:bg-gray-50"><td class="px-5 py-3 font-semibold"><a class="hover:underline" href="?<?php echo http_build_query(['tab' => 'ringkasan', 'y' => $y, 'm' => $i]); ?>"><?php echo ID_MONTHS[$i]; ?></a></td>
                <td class="px-5 py-3 text-right"><?php echo e(rp($incMonth[$i])); ?></td><td class="px-5 py-3 text-right"><?php echo e(rp($expMonth[$i])); ?></td>
                <td class="px-5 py-3 text-right font-bold <?php echo $p >= 0 ? 'text-green-600' : 'text-red-600'; ?>"><?php echo ($p < 0 ? '-' : '') . e(rp(abs($p))); ?></td></tr>
        <?php endfor; if (!array_sum($incMonth) && !array_sum($expMonth)): ?><tr><td colspan="4" class="px-5 py-10 text-center text-gray-500">Belum ada data di tahun ini.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr class="bg-gray-50 font-bold"><td class="px-5 py-3">Total <?php echo $y; ?></td><td class="px-5 py-3 text-right"><?php echo e(rp(array_sum($incMonth))); ?></td><td class="px-5 py-3 text-right"><?php echo e(rp(array_sum($expMonth))); ?></td>
            <?php $tp = array_sum($incMonth) - array_sum($expMonth); ?><td class="px-5 py-3 text-right <?php echo $tp >= 0 ? 'text-green-600' : 'text-red-600'; ?>"><?php echo ($tp < 0 ? '-' : '') . e(rp(abs($tp))); ?></td></tr></tfoot>
    </table>
</section>

<?php /* ========================= PENDAPATAN ========================= */
elseif ($tab === 'pendapatan'):
    $st = $conn->prepare("SELECT o.*, (SELECT channel FROM payments WHERE order_id = o.id ORDER BY id DESC LIMIT 1) AS pay_channel FROM orders o
        WHERE o.payment_status = 'paid' AND o.status <> 'cancelled' AND DATE(o.created_at) BETWEEN ? AND ? ORDER BY o.created_at DESC LIMIT 500");
    $st->bind_param('ss', $from, $to); $st->execute();
    $inc = $st->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<section class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
    <table class="w-full text-left text-sm admin-table">
        <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase"><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Pesanan</th><th class="px-5 py-3">Pelanggan</th><th class="px-5 py-3">Asal</th><th class="px-5 py-3">Pembayaran</th><th class="px-5 py-3 text-right">Jumlah</th></tr></thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($inc as $o): ?>
            <tr class="hover:bg-gray-50"><td class="px-5 py-3 text-gray-500"><?php echo e(date_short($o['created_at'])); ?></td>
                <td class="px-5 py-3 font-semibold"><a class="hover:underline" href="admin_pesanan_detail.php?id=<?php echo (int)$o['id']; ?>">#<?php echo e($o['order_code']); ?></a></td>
                <td class="px-5 py-3"><?php echo e($o['shipping_name']); ?></td><td class="px-5 py-3"><?php echo channel_badge($o['channel']); ?></td>
                <td class="px-5 py-3 text-gray-600"><?php echo e($o['pay_channel'] ?: '-'); ?></td><td class="px-5 py-3 text-right font-semibold"><?php echo e(rp($o['total_amount'])); ?></td></tr>
        <?php endforeach; if (!$inc): ?><tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">Tidak ada pendapatan pada periode ini.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr class="bg-gray-50 font-bold"><td class="px-5 py-3" colspan="5">Total</td><td class="px-5 py-3 text-right"><?php echo e(rp($income)); ?></td></tr></tfoot>
    </table>
</section>

<?php /* ========================= PENGELUARAN ========================= */
else:
    $edit = null;
    if (!empty($_GET['edit'])) { $eid = (int)$_GET['edit']; $edit = $conn->query("SELECT * FROM expenses WHERE id = $eid")->fetch_assoc() ?: null; }
    $st = $conn->prepare("SELECT * FROM expenses WHERE spent_at BETWEEN ? AND ? ORDER BY spent_at DESC, id DESC");
    $st->bind_param('ss', $from, $to); $st->execute();
    $exps = $st->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<div class="grid lg:grid-cols-3 gap-5">
    <section class="bg-white rounded-xl border border-gray-200 p-5 h-fit no-print">
        <h2 class="font-bold mb-4"><?php echo $edit ? 'Edit Pengeluaran' : 'Catat Pengeluaran'; ?></h2>
        <form method="post" action="?<?php echo http_build_query($keep); ?>" class="space-y-3 text-sm"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save_expense"><input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
            <div><label class="text-xs font-bold text-gray-500 uppercase">Tanggal</label><input type="date" name="spent_at" value="<?php echo e($edit['spent_at'] ?? date('Y-m-d')); ?>" class="<?php echo $inp; ?> mt-1" required></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Kategori</label><input name="category" list="exp-cats" value="<?php echo e($edit['category'] ?? ''); ?>" class="<?php echo $inp; ?> mt-1" placeholder="pilih / ketik" required>
                <datalist id="exp-cats"><?php foreach (EXP_CATS as $c): ?><option value="<?php echo e($c); ?>"><?php endforeach; ?></datalist></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Keterangan</label><input name="note" value="<?php echo e($edit['note'] ?? ''); ?>" class="<?php echo $inp; ?> mt-1"></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Jumlah (Rp)</label><input type="number" name="amount" min="1" value="<?php echo e($edit['amount'] ?? ''); ?>" class="<?php echo $inp; ?> mt-1" required></div>
            <div class="flex gap-2"><button class="flex-1 bg-black text-white rounded-lg py-2.5 font-bold hover:bg-gray-800">Simpan</button><?php if ($edit): ?><a href="?<?php echo http_build_query($keep); ?>" class="px-4 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 font-medium">Batal</a><?php endif; ?></div>
        </form>
    </section>
    <section class="lg:col-span-2 bg-white rounded-xl border border-gray-200 overflow-x-auto h-fit">
        <table class="w-full text-left text-sm admin-table">
            <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase"><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Kategori</th><th class="px-5 py-3">Keterangan</th><th class="px-5 py-3 text-right">Jumlah</th><th class="px-5 py-3 no-print"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($exps as $x): ?>
                <tr class="hover:bg-gray-50"><td class="px-5 py-3 text-gray-500 whitespace-nowrap"><?php echo e(date_short($x['spent_at'])); ?></td><td class="px-5 py-3"><?php echo admin_pill($x['category']); ?></td>
                    <td class="px-5 py-3 text-gray-600"><?php echo e($x['note']); ?></td><td class="px-5 py-3 text-right font-semibold"><?php echo e(rp($x['amount'])); ?></td>
                    <td class="px-5 py-3 whitespace-nowrap text-right no-print"><a href="?<?php echo http_build_query($keep + ['edit' => (int)$x['id']]); ?>" class="text-blue-600 font-medium text-xs bg-blue-50 px-2 py-1 rounded">Edit</a>
                        <form method="post" action="?<?php echo http_build_query($keep); ?>" class="inline" onsubmit="return confirm('Hapus pengeluaran ini?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_expense"><input type="hidden" name="id" value="<?php echo (int)$x['id']; ?>"><button class="text-red-500 font-medium text-xs bg-red-50 px-2 py-1 rounded">Hapus</button></form></td></tr>
            <?php endforeach; if (!$exps): ?><tr><td colspan="5" class="px-5 py-10 text-center text-gray-500">Tidak ada pengeluaran pada periode ini.</td></tr><?php endif; ?>
            </tbody>
            <tfoot><tr class="bg-gray-50 font-bold"><td class="px-5 py-3" colspan="3">Total</td><td class="px-5 py-3 text-right"><?php echo e(rp($expense)); ?></td><td class="no-print"></td></tr></tfoot>
        </table>
    </section>
</div>
<?php endif; ?>
<?php admin_foot();
