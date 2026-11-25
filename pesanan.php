<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
require_login('pesanan.php');

$uid = user_id();
$st = $conn->prepare("SELECT o.*, (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS n_items FROM orders o WHERE o.user_id = ? ORDER BY o.id DESC");
$st->bind_param('i', $uid);
$st->execute();
$orders = $st->get_result()->fetch_all(MYSQLI_ASSOC);

page_head('Pesanan Saya - AMBOKO');
store_header('app', '', $conn);
?>
<main class="max-w-[1000px] mx-auto px-5 md:px-8 pt-8">
    <p class="text-[10px] font-bold tracking-[0.14em] uppercase mb-2"><a href="index.php" class="text-gray-500 hover:text-black">Home</a> <span class="text-gray-400">/</span> Pesanan Saya</p>
    <h1 class="font-head font-extrabold text-[clamp(30px,4vw,42px)] tracking-tight uppercase leading-none mb-8">Pesanan Saya</h1>

    <?php if (!$orders): ?>
        <div class="bg-paper rounded-2xl py-16 text-center">
            <i class="fa-solid fa-box-open text-4xl text-gray-400 mb-4"></i>
            <p class="font-head font-bold text-xl mb-4">Belum ada pesanan.</p>
            <a href="katalog.php" class="inline-block bg-black text-white font-bold text-sm px-8 py-3.5 rounded-xl hover:bg-gray-800">MULAI BELANJA</a>
        </div>
    <?php else: foreach ($orders as $o): ?>
        <a href="konfirmasi.php?order=<?php echo urlencode($o['order_code']); ?>" class="block bg-paper hover:bg-[#e9e9ea] transition rounded-2xl p-5 mb-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-head font-bold text-[18px]">#<?php echo e($o['order_code']); ?></p>
                    <p class="text-[12px] text-gray-500"><?php echo e(date_long($o['created_at'])); ?>, <?php echo date('H:i', strtotime($o['created_at'])); ?> WIB &bull; <?php echo (int)$o['n_items']; ?> jenis item</p>
                </div>
                <div class="text-right">
                    <p class="font-head font-extrabold text-[20px]"><?php echo e(rp($o['total_amount'])); ?></p>
                    <div class="mt-1"><?php echo order_status_badge($o['status']); ?></div>
                </div>
            </div>
        </a>
    <?php endforeach; endif; ?>
</main>
<?php store_footer('app'); page_end();
