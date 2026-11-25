<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';

const LEAD_STATUS = [
    'new'        => ['Baru',          'bg-gray-100 text-gray-700'],
    'contacted'  => ['Dihubungi',     'bg-blue-100 text-blue-800'],
    'interested' => ['Tertarik',      'bg-amber-100 text-amber-800'],
    'won'        => ['Jadi pelanggan','bg-green-100 text-green-800'],
    'lost'       => ['Batal',         'bg-red-100 text-red-700'],
];
const LEAD_SOURCE = ['Instagram', 'TikTok', 'WhatsApp', 'Website', 'Toko Offline'];
const INTER_TYPE = ['whatsapp' => 'WhatsApp', 'instagram' => 'Instagram', 'phone' => 'Telepon', 'store' => 'Di toko', 'email' => 'Email'];

$tab = in_array($_GET['tab'] ?? '', ['leads', 'interaksi', 'analisis'], true) ? $_GET['tab'] : 'leads';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'save_lead') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '') ?: null;
        $source = in_array($_POST['source'] ?? '', LEAD_SOURCE, true) ? $_POST['source'] : 'Instagram';
        $interest = trim($_POST['interest'] ?? '') ?: null;
        $status = array_key_exists($_POST['status'] ?? '', LEAD_STATUS) ? $_POST['status'] : 'new';
        $note = trim($_POST['note'] ?? '') ?: null;
        if ($name === '') flash('error', 'Nama lead wajib diisi.');
        elseif ($id) {
            $st = $conn->prepare("UPDATE crm_leads SET name=?, phone=?, source=?, interest=?, status=?, note=? WHERE id=?");
            $st->bind_param('ssssssi', $name, $phone, $source, $interest, $status, $note, $id); $st->execute();
            flash('success', 'Lead diperbarui.');
        } else {
            $now = date('Y-m-d H:i:s');
            $st = $conn->prepare("INSERT INTO crm_leads (name, phone, source, interest, status, note, created_at) VALUES (?,?,?,?,?,?,?)");
            $st->bind_param('sssssss', $name, $phone, $source, $interest, $status, $note, $now); $st->execute();
            flash('success', 'Lead ditambahkan.');
        }
        redirect('admin_crm.php?tab=leads');
    } elseif ($action === 'delete_lead' && $id) {
        $conn->query("DELETE FROM crm_leads WHERE id = $id");
        flash('success', 'Lead dihapus.');
        redirect('admin_crm.php?tab=leads');
    } elseif ($action === 'add_inter') {
        $who = (string)($_POST['who'] ?? '');
        $type = array_key_exists($_POST['type'] ?? '', INTER_TYPE) ? $_POST['type'] : 'whatsapp';
        $summary = trim($_POST['summary'] ?? '');
        $uid = null; $lid = null;
        if (preg_match('/^u:(\d+)$/', $who, $m)) $uid = (int)$m[1];
        elseif (preg_match('/^l:(\d+)$/', $who, $m)) $lid = (int)$m[1];
        if (!$uid && !$lid) flash('error', 'Pilih pelanggan atau lead.');
        elseif ($summary === '') flash('error', 'Ringkasan interaksi wajib diisi.');
        else {
            $now = date('Y-m-d H:i:s');
            $st = $conn->prepare("INSERT INTO crm_interactions (user_id, lead_id, type, summary, created_at) VALUES (?,?,?,?,?)");
            $st->bind_param('iisss', $uid, $lid, $type, $summary, $now); $st->execute();
            flash('success', 'Interaksi dicatat.');
        }
        redirect('admin_crm.php?tab=interaksi');
    } elseif ($action === 'delete_inter' && $id) {
        $conn->query("DELETE FROM crm_interactions WHERE id = $id");
        flash('success', 'Interaksi dihapus.');
        redirect('admin_crm.php?tab=interaksi');
    }
}

admin_head('CRM', 'crm');
$inp = ADMIN_INP;
echo admin_tabs(['leads' => 'Leads', 'interaksi' => 'Riwayat Interaksi', 'analisis' => 'Analisis Pelanggan'], $tab);

/* ============================ LEADS ============================ */
if ($tab === 'leads'):
    $edit = null;
    if (!empty($_GET['edit'])) { $eid = (int)$_GET['edit']; $edit = $conn->query("SELECT * FROM crm_leads WHERE id = $eid")->fetch_assoc() ?: null; }
    $leads = $conn->query("SELECT * FROM crm_leads ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
?>
<div class="grid lg:grid-cols-3 gap-5">
    <section class="bg-white rounded-xl border border-gray-200 p-5 h-fit">
        <h2 class="font-bold mb-4"><?php echo $edit ? 'Edit Lead' : 'Tambah Lead'; ?></h2>
        <form method="post" class="space-y-3 text-sm"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save_lead"><input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
            <div><label class="text-xs font-bold text-gray-500 uppercase">Nama</label><input name="name" value="<?php echo e($edit['name'] ?? ''); ?>" class="<?php echo $inp; ?> mt-1" required></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-xs font-bold text-gray-500 uppercase">Telepon</label><input name="phone" value="<?php echo e($edit['phone'] ?? ''); ?>" class="<?php echo $inp; ?> mt-1"></div>
                <div><label class="text-xs font-bold text-gray-500 uppercase">Sumber</label><select name="source" class="<?php echo $inp; ?> mt-1"><?php foreach (LEAD_SOURCE as $s): ?><option <?php echo ($edit['source'] ?? '') === $s ? 'selected' : ''; ?>><?php echo e($s); ?></option><?php endforeach; ?></select></div>
            </div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Tertarik pada</label><input name="interest" value="<?php echo e($edit['interest'] ?? ''); ?>" class="<?php echo $inp; ?> mt-1" placeholder="mis. Loose Fit Jacket"></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Status</label><select name="status" class="<?php echo $inp; ?> mt-1"><?php foreach (LEAD_STATUS as $k => [$l]): ?><option value="<?php echo $k; ?>" <?php echo ($edit['status'] ?? 'new') === $k ? 'selected' : ''; ?>><?php echo e($l); ?></option><?php endforeach; ?></select></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Catatan</label><input name="note" value="<?php echo e($edit['note'] ?? ''); ?>" class="<?php echo $inp; ?> mt-1"></div>
            <div class="flex gap-2"><button class="flex-1 bg-black text-white rounded-lg py-2.5 font-bold hover:bg-gray-800">Simpan</button><?php if ($edit): ?><a href="admin_crm.php?tab=leads" class="px-4 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 font-medium">Batal</a><?php endif; ?></div>
        </form>
    </section>
    <section class="lg:col-span-2 bg-white rounded-xl border border-gray-200 overflow-x-auto h-fit">
        <table class="w-full text-left text-sm admin-table">
            <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase"><th class="px-5 py-3">Lead</th><th class="px-5 py-3">Sumber</th><th class="px-5 py-3">Tertarik pada</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($leads as $l): [$lbl, $cls] = LEAD_STATUS[$l['status']]; ?>
                <tr class="hover:bg-gray-50"><td class="px-5 py-3"><b><?php echo e($l['name']); ?></b><div class="text-xs text-gray-400"><?php echo e($l['phone'] ?: '-'); ?> &bull; <?php echo e(date_short($l['created_at'])); ?></div><?php if ($l['note']): ?><div class="text-xs text-gray-500 mt-0.5"><?php echo e($l['note']); ?></div><?php endif; ?></td>
                    <td class="px-5 py-3"><?php echo e($l['source']); ?></td><td class="px-5 py-3 text-gray-600"><?php echo e($l['interest'] ?: '-'); ?></td>
                    <td class="px-5 py-3"><?php echo admin_pill($lbl, $cls); ?></td>
                    <td class="px-5 py-3 whitespace-nowrap text-right"><a href="?tab=leads&edit=<?php echo (int)$l['id']; ?>" class="text-blue-600 font-medium text-xs bg-blue-50 px-2 py-1 rounded">Edit</a>
                        <form method="post" class="inline" onsubmit="return confirm('Hapus lead ini?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_lead"><input type="hidden" name="id" value="<?php echo (int)$l['id']; ?>"><button class="text-red-500 font-medium text-xs bg-red-50 px-2 py-1 rounded">Hapus</button></form></td></tr>
            <?php endforeach; if (!$leads): ?><tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">Belum ada lead.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<?php
/* ============================ INTERAKSI ============================ */
elseif ($tab === 'interaksi'):
    $custs = $conn->query("SELECT id, name FROM users WHERE role = 'customer' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    $leadOpts = $conn->query("SELECT id, name FROM crm_leads ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    $log = $conn->query("SELECT i.*, u.name AS uname, l.name AS lname FROM crm_interactions i LEFT JOIN users u ON u.id = i.user_id LEFT JOIN crm_leads l ON l.id = i.lead_id ORDER BY i.created_at DESC, i.id DESC LIMIT 150")->fetch_all(MYSQLI_ASSOC);
?>
<div class="grid lg:grid-cols-3 gap-5">
    <section class="bg-white rounded-xl border border-gray-200 p-5 h-fit">
        <h2 class="font-bold mb-4">Catat Interaksi</h2>
        <form method="post" class="space-y-3 text-sm"><?php echo csrf_field(); ?><input type="hidden" name="action" value="add_inter">
            <div><label class="text-xs font-bold text-gray-500 uppercase">Pelanggan / Lead</label>
                <select name="who" class="<?php echo $inp; ?> mt-1" required><option value="">- pilih -</option>
                    <optgroup label="Pelanggan"><?php foreach ($custs as $c): ?><option value="u:<?php echo (int)$c['id']; ?>"><?php echo e($c['name']); ?></option><?php endforeach; ?></optgroup>
                    <optgroup label="Lead"><?php foreach ($leadOpts as $c): ?><option value="l:<?php echo (int)$c['id']; ?>"><?php echo e($c['name']); ?></option><?php endforeach; ?></optgroup></select></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Lewat</label><select name="type" class="<?php echo $inp; ?> mt-1"><?php foreach (INTER_TYPE as $k => $l): ?><option value="<?php echo $k; ?>"><?php echo e($l); ?></option><?php endforeach; ?></select></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Ringkasan</label><textarea name="summary" rows="3" class="<?php echo $inp; ?> mt-1" required></textarea></div>
            <button class="w-full bg-black text-white rounded-lg py-2.5 font-bold hover:bg-gray-800">Simpan</button>
        </form>
    </section>
    <section class="lg:col-span-2 bg-white rounded-xl border border-gray-200 overflow-x-auto h-fit">
        <table class="w-full text-left text-sm admin-table">
            <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase"><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Dengan</th><th class="px-5 py-3">Lewat</th><th class="px-5 py-3">Ringkasan</th><th class="px-5 py-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($log as $i): ?>
                <tr class="hover:bg-gray-50"><td class="px-5 py-3 text-gray-500 whitespace-nowrap"><?php echo e(date_short($i['created_at'])); ?> <span class="text-xs"><?php echo date('H:i', strtotime($i['created_at'])); ?></span></td>
                    <td class="px-5 py-3 font-semibold"><?php if ($i['user_id']): ?><a class="hover:underline" href="admin_pelanggan_detail.php?id=<?php echo (int)$i['user_id']; ?>"><?php echo e($i['uname']); ?></a><?php else: echo e($i['lname']); ?> <span class="text-xs text-gray-400 font-normal">(lead)</span><?php endif; ?></td>
                    <td class="px-5 py-3"><?php echo admin_pill(INTER_TYPE[$i['type']] ?? $i['type']); ?></td>
                    <td class="px-5 py-3 text-gray-600"><?php echo e($i['summary']); ?></td>
                    <td class="px-5 py-3 text-right"><form method="post" class="inline" onsubmit="return confirm('Hapus catatan ini?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_inter"><input type="hidden" name="id" value="<?php echo (int)$i['id']; ?>"><button class="text-red-500 font-medium text-xs bg-red-50 px-2 py-1 rounded">Hapus</button></form></td></tr>
            <?php endforeach; if (!$log): ?><tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">Belum ada interaksi.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<?php
/* ============================ ANALISIS ============================ */
else:
    $cust = $conn->query("SELECT u.id, u.name, u.city, u.channel, COUNT(o.id) n, COALESCE(SUM(o.total_amount),0) spent
        FROM users u LEFT JOIN orders o ON o.user_id = u.id AND o.payment_status = 'paid' AND o.status <> 'cancelled'
        WHERE u.role = 'customer' GROUP BY u.id ORDER BY spent DESC")->fetch_all(MYSQLI_ASSOC);
    $total = count($cust);
    $buyers = array_filter($cust, fn($c) => (int)$c['n'] >= 1);
    $loyal = array_filter($cust, fn($c) => (int)$c['n'] >= 2);
    $one = array_filter($cust, fn($c) => (int)$c['n'] === 1);
    $none = $total - count($buyers);
    $sumSpent = array_sum(array_column($cust, 'spent'));
    $avgSpend = $buyers ? $sumSpent / count($buyers) : 0;
    $orderCount = array_sum(array_column($cust, 'n'));
    $aov = $orderCount ? $sumSpent / $orderCount : 0;

    $byCh = ['online' => ['cust' => 0, 'orders' => 0, 'rev' => 0], 'offline' => ['cust' => 0, 'orders' => 0, 'rev' => 0]];
    foreach ($cust as $c) { $byCh[$c['channel']]['cust']++; $byCh[$c['channel']]['orders'] += (int)$c['n']; $byCh[$c['channel']]['rev'] += (float)$c['spent']; }
    $cities = [];
    foreach ($cust as $c) if ($c['city']) $cities[$c['city']] = ($cities[$c['city']] ?? 0) + 1;
    arsort($cities);
    $cities = array_slice($cities, 0, 5, true);
    $leadCount = [];
    $r = $conn->query("SELECT status, COUNT(*) n FROM crm_leads GROUP BY status");
    while ($row = $r->fetch_assoc()) $leadCount[$row['status']] = (int)$row['n'];
    $leadTotal = array_sum($leadCount);
    $conv = $leadTotal ? round(($leadCount['won'] ?? 0) / $leadTotal * 100) : 0;
    $card = fn(string $label, string $value, string $sub = '') => '<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5"><p class="text-xs text-gray-500 font-medium">' . $label . '</p><p class="font-head font-extrabold text-2xl leading-tight mt-1">' . $value . '</p>' . ($sub ? '<p class="text-[11px] text-gray-400 mt-0.5">' . $sub . '</p>' : '') . '</div>';
?>
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <?php echo $card('Total Pelanggan', (string)$total, count($buyers) . ' sudah membeli'); ?>
    <?php echo $card('Pelanggan Setia', (string)count($loyal), 'Beli 2x atau lebih'); ?>
    <?php echo $card('Rata-rata Belanja', e(rp($avgSpend)), 'Per pelanggan yang membeli'); ?>
    <?php echo $card('Rata-rata per Pesanan', e(rp($aov)), $orderCount . ' pesanan lunas'); ?>
</div>
<div class="grid lg:grid-cols-3 gap-5 mb-5">
    <section class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-bold mb-3">Segmen Pelanggan</h2>
        <ul class="space-y-2.5 text-sm">
            <li class="flex justify-between"><span>Setia (&ge; 2 pesanan)</span><b><?php echo count($loyal); ?></b></li>
            <li class="flex justify-between"><span>Baru (1 pesanan)</span><b><?php echo count($one); ?></b></li>
            <li class="flex justify-between"><span>Belum pernah beli</span><b><?php echo $none; ?></b></li>
        </ul>
    </section>
    <section class="bg-white rounded-xl border border-gray-200 p-5 lg:col-span-2 overflow-x-auto">
        <h2 class="font-bold mb-3">Website vs Toko Offline</h2>
        <table class="w-full text-sm text-left"><thead><tr class="text-xs text-gray-500 uppercase"><th class="py-2">Asal</th><th class="py-2 text-right">Pelanggan</th><th class="py-2 text-right">Pesanan</th><th class="py-2 text-right">Pendapatan</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($byCh as $k => $v): ?><tr><td class="py-2.5"><?php echo channel_badge($k); ?></td><td class="py-2.5 text-right"><?php echo $v['cust']; ?></td><td class="py-2.5 text-right"><?php echo $v['orders']; ?></td><td class="py-2.5 text-right font-semibold"><?php echo e(rp($v['rev'])); ?></td></tr><?php endforeach; ?>
            </tbody></table>
    </section>
</div>
<div class="grid lg:grid-cols-3 gap-5">
    <section class="bg-white rounded-xl border border-gray-200 p-5 lg:col-span-2 overflow-x-auto">
        <h2 class="font-bold mb-3">Pelanggan Teratas</h2>
        <table class="w-full text-sm text-left"><thead><tr class="text-xs text-gray-500 uppercase"><th class="py-2">Pelanggan</th><th class="py-2">Asal</th><th class="py-2 text-right">Pesanan</th><th class="py-2 text-right">Total Belanja</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach (array_slice($cust, 0, 5) as $c): ?><tr><td class="py-2.5 font-semibold"><a class="hover:underline" href="admin_pelanggan_detail.php?id=<?php echo (int)$c['id']; ?>"><?php echo e($c['name']); ?></a></td><td class="py-2.5"><?php echo channel_badge($c['channel']); ?></td><td class="py-2.5 text-right"><?php echo (int)$c['n']; ?></td><td class="py-2.5 text-right font-semibold"><?php echo e(rp($c['spent'])); ?></td></tr><?php endforeach; ?>
            </tbody></table>
    </section>
    <div class="space-y-5">
        <section class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-bold mb-3">Kota Terbanyak</h2>
            <ul class="space-y-2 text-sm"><?php foreach ($cities as $city => $n): ?><li class="flex justify-between"><span><?php echo e($city); ?></span><b><?php echo $n; ?></b></li><?php endforeach; if (!$cities) echo '<li class="text-gray-500">Belum ada data.</li>'; ?></ul>
        </section>
        <section class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-bold mb-3">Konversi Lead</h2>
            <p class="font-head font-extrabold text-2xl"><?php echo $conv; ?>%</p>
            <p class="text-xs text-gray-400"><?php echo (int)($leadCount['won'] ?? 0); ?> dari <?php echo $leadTotal; ?> lead menjadi pelanggan</p>
        </section>
    </div>
</div>
<?php endif; ?>
<?php admin_foot();
