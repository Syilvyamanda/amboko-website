<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/order_view.php';

$code = strtoupper(trim($_GET['order'] ?? $_POST['order'] ?? ''));
$email = trim($_POST['email'] ?? '');
$o = null; $err = null;

if ($code !== '') {
    $cand = order_load($conn, $code);
    $owner = $cand && is_logged_in() && ((int)$cand['user_id'] === user_id() || is_admin());
    $emailOk = $cand && $email !== '' && strcasecmp($cand['shipping_email'] ?? '', $email) === 0;
    if ($cand && ($owner || $emailOk)) $o = $cand;
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' || $cand) $err = $cand && !is_logged_in() && $email === ''
        ? 'Masukkan email pemesan untuk melihat status pesanan.' : 'Kombinasi nomor pesanan dan email tidak cocok.';
    else $err = 'Nomor pesanan tidak ditemukan.';
}

page_head('Order Tracking - AMBOKO');
store_header('app', '', $conn);
?>
<main class="max-w-[760px] mx-auto px-5 md:px-8 pt-8">
    <p class="text-[10px] font-bold tracking-[0.14em] uppercase mb-2"><a href="index.php" class="text-gray-500 hover:text-black">Home</a> <span class="text-gray-400">/</span> Order Tracking</p>
    <h1 class="font-head font-extrabold text-[clamp(30px,4vw,42px)] tracking-tight uppercase leading-none mb-6">Order Tracking</h1>

    <?php if (!$o): ?>
    <form method="post" class="bg-paper rounded-2xl p-6 space-y-4">
        <?php if ($err): ?><p class="bg-red-50 text-red-700 text-sm rounded-lg px-4 py-2.5"><?php echo e($err); ?></p><?php endif; ?>
        <div><label class="block text-[10px] font-bold tracking-[0.12em] uppercase mb-1.5">Nomor Pesanan</label>
            <input name="order" value="<?php echo e($code); ?>" placeholder="AMB-892401" class="w-full bg-white rounded-lg px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-black uppercase"></div>
        <?php if (!is_logged_in()): ?>
        <div><label class="block text-[10px] font-bold tracking-[0.12em] uppercase mb-1.5">Email Pemesan</label>
            <input type="email" name="email" value="<?php echo e($email); ?>" class="w-full bg-white rounded-lg px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-black"></div>
        <?php endif; ?>
        <button class="bg-black text-white font-bold text-[12px] tracking-[0.1em] uppercase px-8 py-3.5 rounded-xl hover:bg-gray-800">Lacak Pesanan</button>
    </form>
    <?php else: ?>
    <section class="bg-paper rounded-2xl p-6">
        <div class="flex items-center justify-between flex-wrap gap-2 mb-6">
            <div><p class="font-head font-bold text-[20px]">#<?php echo e($o['order_code']); ?></p>
                <p class="text-[12px] text-gray-500">Dipesan <?php echo e(date_long($o['created_at'])); ?></p></div>
            <?php echo order_status_badge($o['status']); ?>
        </div>
        <?php echo $o['status'] === 'cancelled' ? '<p class="bg-red-50 text-red-700 text-sm rounded-xl px-4 py-3">Pesanan ini telah dibatalkan.</p>' : order_timeline_html($o); ?>
        <div class="bg-white rounded-xl px-5 py-4 mt-6 text-sm">
            <p class="text-[9px] font-bold tracking-[0.14em] text-gray-500 uppercase">Nomor Resi / AWB</p>
            <p class="font-bold"><?php echo e($o['tracking_number']); ?></p>
        </div>
    </section>

    <section class="mt-6">
        <h2 class="font-head font-extrabold text-[16px] uppercase mb-3">Riwayat Status</h2>
        <ul class="space-y-2 text-sm">
            <?php foreach (array_reverse($o['history']) as $h): ?>
                <li class="flex gap-3"><span class="text-gray-500 w-36 shrink-0 text-[12px]"><?php echo e(date_short($h['created_at'])); ?>, <?php echo date('H:i', strtotime($h['created_at'])); ?></span><span><?php echo e($h['note'] ?: (ORDER_STATUS[$h['status']][0] ?? $h['status'])); ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
</main>
<?php store_footer('app'); page_end();
