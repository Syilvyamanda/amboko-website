<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';

$tab = in_array($_GET['tab'] ?? '', ['pengunjung', 'penjualan', 'terlaris'], true) ? $_GET['tab'] : 'pengunjung';
$p = in_array($_GET['p'] ?? '', ['bulan', '3bulan', 'tahun', 'semua'], true) ? $_GET['p'] : 'tahun';
[$from, $to, $plabel] = admin_period($p);
$f = $conn->real_escape_string($from); $t = $conn->real_escape_string($to);
$one = fn(string $sql) => $conn->query($sql)->fetch_row()[0] ?? 0;
$card = fn(string $label, string $value, string $sub = '') => '<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5"><p class="text-xs text-gray-500 font-medium">' . $label . '</p><p class="font-head font-extrabold text-2xl leading-tight mt-1">' . $value . '</p>' . ($sub ? '<p class="text-[11px] text-gray-400 mt-0.5">' . $sub . '</p>' : '') . '</div>';

/** Kelompokkan deret tanggal -> label per hari (<= 31 hari) atau per bulan */
$monthly = in_array($p, ['3bulan', 'tahun', 'semua'], true);

admin_head('Analitik', 'analitik');
echo admin_tabs(['pengunjung' => 'Pengunjung', 'penjualan' => 'Penjualan', 'terlaris' => 'Produk Terlaris'], $tab, ['p' => $p]);
echo '<div class="flex flex-wrap items-center justify-between gap-3 mb-5">' . admin_period_pills($p, ['tab' => $tab]) . '<span class="text-sm text-gray-500">' . e($plabel) . ': ' . e(date_short($from)) . ' - ' . e(date_short($to)) . '</span></div>';

/* ========================= PENGUNJUNG ========================= */
if ($tab === 'pengunjung'):
    $sum = $conn->query("SELECT COALESCE(SUM(visitors),0) v, COALESCE(SUM(page_views),0) pv, COALESCE(SUM(src_instagram),0) ig, COALESCE(SUM(src_tiktok),0) tk,
        COALESCE(SUM(src_search),0) se, COALESCE(SUM(src_direct),0) dr, COUNT(*) days FROM visitor_stats WHERE stat_date BETWEEN '$f' AND '$t'")->fetch_assoc();
    $series = [];
    if ($monthly) {
        $r = $conn->query("SELECT DATE_FORMAT(stat_date,'%Y-%m') ym, SUM(visitors) v FROM visitor_stats WHERE stat_date BETWEEN '$f' AND '$t' GROUP BY ym ORDER BY ym");
        while ($row = $r->fetch_assoc()) { [$yy, $mm] = explode('-', $row['ym']); $series[ID_MONTHS_SHORT[(int)$mm] . ($p === 'semua' ? ' ' . substr($yy, 2) : '')] = (int)$row['v']; }
    } else {
        $r = $conn->query("SELECT stat_date d, visitors v FROM visitor_stats WHERE stat_date BETWEEN '$f' AND '$t' ORDER BY stat_date");
        while ($row = $r->fetch_assoc()) $series[date('j', strtotime($row['d']))] = (int)$row['v'];
    }
    $days = max(1, (int)$sum['days']);
    $src = ['Instagram' => (int)$sum['ig'], 'TikTok' => (int)$sum['tk'], 'Pencarian (Google)' => (int)$sum['se'], 'Langsung' => (int)$sum['dr']];
    $srcTotal = max(1, array_sum($src));
    $orders = (int)$one("SELECT COUNT(*) FROM orders WHERE channel = 'online' AND status <> 'cancelled' AND DATE(created_at) BETWEEN '$f' AND '$t'");
    $convRate = $sum['v'] > 0 ? $orders / $sum['v'] * 100 : 0;
?>
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
    <?php echo $card('Pengunjung', number_format((int)$sum['v'], 0, ',', '.'), 'Rata-rata ' . number_format($sum['v'] / $days, 0, ',', '.') . ' / hari'); ?>
    <?php echo $card('Halaman Dilihat', number_format((int)$sum['pv'], 0, ',', '.'), $sum['v'] > 0 ? number_format($sum['pv'] / $sum['v'], 1, ',', '.') . ' halaman / pengunjung' : ''); ?>
    <?php echo $card('Pesanan Website', (string)$orders, 'Pesanan tidak dibatalkan'); ?>
    <?php echo $card('Konversi', number_format($convRate, 2, ',', '.') . '%', 'Pesanan / pengunjung'); ?>
</div>
<div class="grid lg:grid-cols-3 gap-5">
    <section class="bg-white rounded-xl border border-gray-200 p-5 lg:col-span-2">
        <h2 class="font-bold mb-4">Pengunjung <?php echo $monthly ? 'per Bulan' : 'per Hari'; ?></h2>
        <?php echo $series ? admin_bars($series, fn($v) => number_format($v, 0, ',', '.')) : '<p class="text-sm text-gray-500">Belum ada data.</p>'; ?>
    </section>
    <section class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-bold mb-4">Sumber Pengunjung</h2>
        <ul class="space-y-3 text-sm">
            <?php foreach ($src as $label => $v): $pc = round($v / $srcTotal * 100); ?>
                <li><div class="flex justify-between mb-1"><span><?php echo e($label); ?></span><b><?php echo $pc; ?>%</b></div><div class="h-2 bg-gray-100 rounded-full"><div class="h-2 bg-black rounded-full" style="width:<?php echo $pc; ?>%"></div></div></li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
<p class="text-xs text-gray-400 mt-3">Data kunjungan masih contoh (dummy) karena website belum mencatat kunjungan.</p>

<?php /* ========================= PENJUALAN ========================= */
elseif ($tab === 'penjualan'):
    $where = "o.payment_status = 'paid' AND o.status <> 'cancelled' AND DATE(o.created_at) BETWEEN '$f' AND '$t'";
    $tot = $conn->query("SELECT COUNT(*) n, COALESCE(SUM(o.total_amount),0) rev FROM orders o WHERE $where")->fetch_assoc();
    $pcs = (int)$one("SELECT COALESCE(SUM(oi.qty),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE $where");
    $aov = $tot['n'] ? $tot['rev'] / $tot['n'] : 0;
    $series = [];
    if ($monthly) {
        $r = $conn->query("SELECT DATE_FORMAT(o.created_at,'%Y-%m') ym, SUM(o.total_amount) s FROM orders o WHERE $where GROUP BY ym ORDER BY ym");
        while ($row = $r->fetch_assoc()) { [$yy, $mm] = explode('-', $row['ym']); $series[ID_MONTHS_SHORT[(int)$mm] . ($p === 'semua' ? ' ' . substr($yy, 2) : '')] = (int)$row['s']; }
    } else {
        $r = $conn->query("SELECT DATE(o.created_at) d, SUM(o.total_amount) s FROM orders o WHERE $where GROUP BY d ORDER BY d");
        while ($row = $r->fetch_assoc()) $series[date('j', strtotime($row['d']))] = (int)$row['s'];
    }
    $byCh = $conn->query("SELECT o.channel, COUNT(*) n, SUM(o.total_amount) s FROM orders o WHERE $where GROUP BY o.channel")->fetch_all(MYSQLI_ASSOC);
    $byCat = $conn->query("SELECT COALESCE(c.name,'Tanpa kategori') name, SUM(oi.qty) q, SUM(oi.qty * oi.price) s FROM order_items oi JOIN orders o ON o.id = oi.order_id
        LEFT JOIN products p ON p.id = oi.product_id LEFT JOIN categories c ON c.id = p.category_id WHERE $where GROUP BY name ORDER BY s DESC")->fetch_all(MYSQLI_ASSOC);
?>
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
    <?php echo $card('Pendapatan', e(rp($tot['rev'])), 'Pesanan lunas'); ?>
    <?php echo $card('Pesanan', (string)(int)$tot['n']); ?>
    <?php echo $card('Produk Terjual', $pcs . ' pcs'); ?>
    <?php echo $card('Rata-rata per Pesanan', e(rp($aov))); ?>
</div>
<div class="grid lg:grid-cols-3 gap-5">
    <section class="bg-white rounded-xl border border-gray-200 p-5 lg:col-span-2">
        <h2 class="font-bold mb-4">Penjualan <?php echo $monthly ? 'per Bulan' : 'per Hari'; ?></h2>
        <?php echo $series ? admin_bars($series, fn($v) => number_format($v / 1000, 0, ',', '.') . 'k') : '<p class="text-sm text-gray-500">Belum ada penjualan pada periode ini.</p>'; ?>
    </section>
    <div class="space-y-5">
        <section class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-bold mb-3">Website vs Toko</h2>
            <ul class="space-y-2.5 text-sm">
                <?php foreach ($byCh as $c): ?><li class="flex items-center justify-between"><?php echo channel_badge($c['channel']); ?><span><b><?php echo e(rp($c['s'])); ?></b> <span class="text-gray-400">&bull; <?php echo (int)$c['n']; ?> pesanan</span></span></li><?php endforeach; if (!$byCh) echo '<li class="text-gray-500">Belum ada data.</li>'; ?>
            </ul>
        </section>
        <section class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-bold mb-3">Per Kategori</h2>
            <ul class="space-y-2.5 text-sm">
                <?php foreach ($byCat as $c): ?><li class="flex items-center justify-between"><span><?php echo e($c['name']); ?></span><span><b><?php echo e(rp($c['s'])); ?></b> <span class="text-gray-400">&bull; <?php echo (int)$c['q']; ?> pcs</span></span></li><?php endforeach; if (!$byCat) echo '<li class="text-gray-500">Belum ada data.</li>'; ?>
            </ul>
        </section>
    </div>
</div>

<?php /* ========================= TERLARIS ========================= */
else:
    $top = $conn->query("SELECT oi.product_id, oi.product_name, SUM(oi.qty) q, SUM(oi.qty * oi.price) s, p.image_url, p.stock, p.rating_avg, p.review_total
        FROM order_items oi JOIN orders o ON o.id = oi.order_id LEFT JOIN products p ON p.id = oi.product_id
        WHERE o.payment_status = 'paid' AND o.status <> 'cancelled' AND DATE(o.created_at) BETWEEN '$f' AND '$t'
        GROUP BY oi.product_id, oi.product_name, p.image_url, p.stock, p.rating_avg, p.review_total ORDER BY q DESC, s DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
    $maxQ = max(1, $top ? (int)$top[0]['q'] : 1);
?>
<section class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
    <table class="w-full text-left text-sm admin-table">
        <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase"><th class="px-5 py-3">#</th><th class="px-5 py-3">Produk</th><th class="px-5 py-3 w-1/4">Terjual</th><th class="px-5 py-3 text-right">Pendapatan</th><th class="px-5 py-3">Rating</th><th class="px-5 py-3 text-right">Stok</th></tr></thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($top as $i => $x): ?>
            <tr class="hover:bg-gray-50"><td class="px-5 py-3"><span class="w-6 h-6 rounded-full bg-gray-100 text-xs font-bold inline-flex items-center justify-center"><?php echo $i + 1; ?></span></td>
                <td class="px-5 py-3"><div class="flex items-center gap-3"><img src="<?php echo e(img($x['image_url'])); ?>" alt="" class="w-9 h-11 object-cover rounded bg-gray-100"><b><?php echo e($x['product_name']); ?></b></div></td>
                <td class="px-5 py-3"><div class="flex items-center gap-2"><div class="flex-1 h-2 bg-gray-100 rounded-full"><div class="h-2 bg-black rounded-full" style="width:<?php echo (int)round($x['q'] / $maxQ * 100); ?>%"></div></div><b class="w-12 text-right"><?php echo (int)$x['q']; ?> pcs</b></div></td>
                <td class="px-5 py-3 text-right font-semibold"><?php echo e(rp($x['s'])); ?></td>
                <td class="px-5 py-3"><?php echo $x['rating_avg'] !== null ? stars($x['rating_avg'], 'text-xs') . ' <span class="text-xs text-gray-400">' . e($x['rating_avg']) . ' (' . (int)$x['review_total'] . ')</span>' : '-'; ?></td>
                <td class="px-5 py-3 text-right"><?php echo $x['stock'] !== null ? (int)$x['stock'] : '-'; ?></td></tr>
        <?php endforeach; if (!$top): ?><tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">Belum ada penjualan pada periode ini.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>
<?php admin_foot();
