<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';

// ---------- AKSI (semua POST + CSRF, lalu redirect) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (isset($_POST['add_to_cart'])) {
        $pid = (int)($_POST['product_id'] ?? 0);
        $sel = [];
        foreach ((array)($_POST['options'] ?? []) as $gid => $vid) $sel[(int)$gid] = (int)$vid;
        [$ok, $msg] = cart_add($conn, $pid, $sel, 1);
        flash($ok ? 'success' : 'error', $msg);
        $back = $_POST['back'] ?? '';
        redirect($ok && $back ? safe_next($back, 'keranjang.php') : ($ok ? 'keranjang.php' : 'katalog.php'));
    }

    $key = $_POST['key'] ?? '';
    $action = $_POST['action'] ?? '';
    $cart = cart_get();

    if ($action === 'clear') {
        unset($_SESSION['cart'], $_SESSION['voucher']);
    } elseif (isset($cart[$key])) {
        if ($action === 'remove') {
            unset($_SESSION['cart'][$key]);
        } elseif ($action === 'inc' || $action === 'dec') {
            $pid = (int)$cart[$key]['product_id'];
            $stock = (int)($conn->query("SELECT stock FROM products WHERE id = $pid")->fetch_row()[0] ?? 0);
            $q = (int)$cart[$key]['qty'] + ($action === 'inc' ? 1 : -1);
            if ($q < 1) {
                unset($_SESSION['cart'][$key]);
            } elseif ($q > $stock) {
                flash('error', 'Stok tersedia hanya ' . $stock . ' pcs.');
            } else {
                $_SESSION['cart'][$key]['qty'] = $q;
            }
        }
    }
    redirect('keranjang.php');
}

$lines = cart_lines($conn);
$t = cart_totals($conn, $lines);

page_head('Keranjang Belanja - AMBOKO', ['bodyClass' => 'bg-white']);
store_header('app', '', $conn);
?>

<main class="max-w-[1440px] mx-auto px-5 md:px-8 pt-8 pb-4">
    <p class="text-[10px] font-bold tracking-[0.14em] uppercase mb-2">
        <a href="index.php" class="text-gray-500 hover:text-black">Home</a> <span class="text-gray-400">/</span> Keranjang Belanja
    </p>
    <h1 class="font-head font-extrabold text-[clamp(32px,4vw,44px)] tracking-tight leading-none uppercase mb-10">Shopping Bag</h1>

    <?php if (!$lines): ?>
        <div class="bg-paper rounded-2xl py-20 px-6 text-center max-w-2xl mx-auto">
            <i class="fa-solid fa-bag-shopping text-4xl text-gray-400 mb-4"></i>
            <h2 class="font-head font-bold text-2xl mb-2">Keranjang masih kosong.</h2>
            <p class="text-gray-500 text-sm mb-6">Temukan pakaian utilitarian favoritmu di katalog AMBOKO.</p>
            <a href="katalog.php" class="inline-block bg-black text-white font-bold text-sm tracking-wide px-8 py-3.5 rounded-xl hover:bg-gray-800 transition">LIHAT KATALOG</a>
        </div>
    <?php else: ?>
    <div class="grid lg:grid-cols-[1fr_minmax(340px,380px)] gap-8 lg:gap-10 items-start">

        <!-- ======== DAFTAR ITEM ======== -->
        <section class="space-y-4">
            <?php foreach ($lines as $l):
                $p = $l['product'];
                $name = $p['cart_name'] ?: $p['name'];
                $group = $p['group_label'] ?: 'PRODUCT';
            ?>
            <article class="bg-white rounded-2xl p-5 md:p-6 shadow-[0_1px_8px_rgba(0,0,0,.07)] border border-gray-100 flex gap-5">
                <a href="product.php?id=<?php echo (int)$p['id']; ?>" class="shrink-0 w-[96px] md:w-[120px] aspect-[3/4] rounded-xl bg-[#eee] overflow-hidden self-start">
                    <img src="<?php echo e(img($l['image'])); ?>" alt="<?php echo e($name); ?>" class="w-full h-full object-cover object-top">
                </a>
                <div class="flex-1 min-w-0 flex flex-col">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold tracking-[0.14em] text-gray-500 uppercase"><?php echo e($group . ' / ' . $p['sku']); ?></p>
                            <h2 class="font-head font-bold text-[18px] md:text-[20px] leading-snug mt-1"><?php echo e($name); ?></h2>
                            <div class="flex flex-wrap gap-2 mt-2.5">
                                <?php foreach ($l['options'] as $o): ?>
                                    <span class="bg-[#eeeeef] rounded-full px-3 py-1 text-[10px] font-semibold tracking-wide uppercase"><?php echo e($o['label'] . ': ' . $o['value']); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <form method="post" class="shrink-0">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="key" value="<?php echo e($l['key']); ?>">
                            <button name="action" value="remove" class="text-gray-500 hover:text-red-600 transition p-1" aria-label="Hapus item" onclick="return confirm('Hapus item ini dari keranjang?')"><i class="fa-regular fa-trash-can"></i></button>
                        </form>
                    </div>

                    <div class="flex items-end justify-between gap-4 mt-auto pt-5">
                        <div>
                            <?php if ($l['qty'] > 1): ?>
                                <p class="text-[10px] font-bold tracking-[0.12em] text-gray-500 uppercase">Subtotal (<?php echo $l['qty']; ?>x <?php echo e(rp($l['unit'])); ?>)</p>
                            <?php else: ?>
                                <p class="text-[10px] font-bold tracking-[0.12em] text-gray-500 uppercase">Harga Satuan</p>
                            <?php endif; ?>
                            <p class="font-head font-extrabold text-[26px] md:text-[28px] leading-tight"><?php echo e(rp($l['subtotal'])); ?></p>
                        </div>
                        <form method="post" class="inline-flex items-center bg-[#eeeeef] rounded-full">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="key" value="<?php echo e($l['key']); ?>">
                            <button name="action" value="dec" class="w-10 h-10 flex items-center justify-center text-gray-700 hover:text-black" aria-label="Kurangi"><i class="fa-solid fa-minus text-xs"></i></button>
                            <span class="w-8 text-center font-bold text-sm"><?php echo $l['qty']; ?></span>
                            <button name="action" value="inc" class="w-10 h-10 flex items-center justify-center text-gray-700 hover:text-black <?php echo $l['qty'] >= (int)$p['stock'] ? 'opacity-30 pointer-events-none' : ''; ?>" aria-label="Tambah"><i class="fa-solid fa-plus text-xs"></i></button>
                        </form>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>

            <form method="post" class="pt-2">
                <?php echo csrf_field(); ?>
                <button name="action" value="clear" class="text-sm text-gray-500 hover:text-red-600 underline underline-offset-4" onclick="return confirm('Kosongkan seluruh keranjang?')">Kosongkan keranjang</button>
                <span class="text-gray-300 mx-2">|</span>
                <a href="katalog.php" class="text-sm text-gray-500 hover:text-black underline underline-offset-4">Lanjut belanja</a>
            </form>
        </section>

        <!-- ======== RINGKASAN ======== -->
        <aside class="bg-paper rounded-2xl p-6 shadow-[0_2px_14px_rgba(0,0,0,.1)] lg:sticky lg:top-24">
            <div class="flex items-center justify-between pb-3 border-b border-gray-200 mb-5">
                <h2 class="font-head font-extrabold text-[17px] tracking-tight uppercase">Ringkasan Belanja</h2>
                <i class="fa-solid fa-receipt text-gray-500"></i>
            </div>
            <dl class="space-y-3 text-[15px]">
                <div class="flex justify-between"><dt class="text-gray-700">Total Harga Barang (<?php echo $t['pcs']; ?> pcs)</dt><dd class="font-bold"><?php echo e(rp($t['subtotal'])); ?></dd></div>
                <div class="flex justify-between"><dt class="text-brand font-medium">Diskon Voucher</dt><dd class="font-bold text-brand"><?php echo $t['discount'] ? '-' : ''; ?><?php echo e(rp($t['discount'])); ?></dd></div>
                <div class="flex justify-between items-baseline">
                    <dt class="text-gray-700">Estimasi Ongkos Kirim</dt>
                    <dd class="font-bold">
                        <?php if ($t['free_shipping']): ?>
                            <span class="line-through text-gray-400 text-xs font-medium mr-1.5"><?php echo e(rp($t['regular_fee'])); ?></span><?php echo e(rp(0)); ?>
                        <?php else: ?>
                            <?php echo e(rp($t['shipping'])); ?>
                        <?php endif; ?>
                    </dd>
                </div>
                <?php if (!$t['free_shipping']): ?>
                    <p class="text-[11px] text-brand -mt-1.5">Belanja <?php echo e(rp(FREE_SHIPPING_MIN - $t['subtotal'])); ?> lagi untuk gratis ongkir.</p>
                <?php endif; ?>
                <div class="flex justify-between text-[13px]"><dt class="text-gray-700">Biaya Layanan &amp; Penanganan</dt><dd class="font-bold"><?php echo e(rp($t['service'])); ?></dd></div>
            </dl>

            <div class="bg-white rounded-xl px-5 py-4 mt-5 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold tracking-[0.14em] text-gray-500 uppercase">Total Pembayaran</p>
                    <p class="text-[11px] text-gray-500 mt-1">Sudah Termasuk PPN <?php echo PPN_RATE; ?>%</p>
                </div>
                <p class="font-head font-extrabold text-[26px] leading-none ml-3 whitespace-nowrap"><?php echo e(rp($t['total'])); ?></p>
            </div>

            <a href="checkout.php" class="mt-5 flex items-center justify-center gap-2 bg-black text-white rounded-xl py-4 font-bold text-[13px] tracking-[0.1em] uppercase hover:bg-gray-800 transition">
                Checkout Sekarang <i class="fa-solid fa-arrow-right text-xs"></i>
            </a>
        </aside>
    </div>
    <?php endif; ?>
</main>

<?php
store_footer('app');
page_end();
