<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';

require_login('checkout.php');

$lines = cart_lines($conn);
if (!$lines) { flash('error', 'Keranjang Anda masih kosong.'); redirect('keranjang.php'); }

$user = current_user($conn);
$methods = payment_methods();

// Nilai form (default dari profil pelanggan)
$f = [
    'name'    => $user['name'] ?? '',
    'phone'   => $user['phone'] ?? '',
    'address' => $user['address'] ?? '',
    'city'    => $user['city'] ?? '',
    'postal'  => $user['postal_code'] ?? '',
    'courier' => 'regular',
    'method'  => 'qris',
    'voucher' => $_SESSION['voucher'] ?? '',
];
$errors = [];
$voucherMsg = null; $voucherOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['name', 'phone', 'address', 'city', 'postal', 'courier', 'method'] as $k) {
        if (isset($_POST[$k])) $f[$k] = trim((string)$_POST[$k]);
    }
    if (!in_array($f['courier'], ['regular', 'express'], true)) $f['courier'] = 'regular';
    if (!isset($methods[$f['method']])) $f['method'] = 'qris';
    $action = $_POST['action'] ?? 'pay';
    $sub = array_sum(array_column($lines, 'subtotal'));

    if ($action === 'voucher') {
        $f['voucher'] = trim((string)($_POST['voucher'] ?? ''));
        $chk = voucher_check($conn, $f['voucher'], $sub);
        if ($chk['ok']) { $_SESSION['voucher'] = $chk['row']['code']; $voucherOk = true; }
        else unset($_SESSION['voucher']);
        $voucherMsg = $chk['msg'];
    } elseif ($action === 'remove_voucher') {
        unset($_SESSION['voucher']); $f['voucher'] = '';
        $voucherMsg = 'Voucher dihapus.';
    } elseif ($action === 'pay') {
        // ---------- VALIDASI ----------
        if (mb_strlen($f['name']) < 3) $errors['name'] = 'Nama lengkap wajib diisi.';
        if (!preg_match('/^(\+62|62|0)8[0-9]{7,12}$/', preg_replace('/[\s\-]/', '', $f['phone']))) $errors['phone'] = 'Nomor WhatsApp tidak valid (contoh: 081298457211).';
        if (mb_strlen($f['address']) < 10) $errors['address'] = 'Alamat lengkap minimal 10 karakter.';
        if ($f['city'] === '') $errors['city'] = 'Kota / kabupaten wajib diisi.';
        if (!preg_match('/^[0-9]{5}$/', $f['postal'])) $errors['postal'] = 'Kode pos harus 5 digit.';

        if (!$errors) {
            $t = cart_totals($conn, $lines, $f['courier']);
            $conn->begin_transaction();
            try {
                // kunci & validasi stok (urut id untuk menghindari deadlock)
                usort($lines, fn($a, $b) => $a['product']['id'] <=> $b['product']['id']);
                $need = [];
                foreach ($lines as $l) $need[$l['product']['id']] = ($need[$l['product']['id']] ?? 0) + $l['qty'];
                $lock = $conn->prepare("SELECT stock, name FROM products WHERE id = ? AND is_active = 1 FOR UPDATE");
                foreach ($need as $pid => $qty) {
                    $lock->bind_param('i', $pid);
                    $lock->execute();
                    $row = $lock->get_result()->fetch_assoc();
                    if (!$row) throw new RuntimeException('Salah satu produk tidak lagi tersedia.');
                    if ((int)$row['stock'] < $qty) throw new RuntimeException('Stok ' . $row['name'] . ' tidak mencukupi (tersisa ' . $row['stock'] . ' pcs).');
                }

                // kode pesanan unik: AMB-892401
                do {
                    $suffix = (string)random_int(100000, 999999);
                    $code = 'AMB-' . $suffix;
                    $x = $conn->query("SELECT 1 FROM orders WHERE order_code = '$code'");
                } while ($x->num_rows > 0);
                $tracking = 'AMB-EXP-' . $suffix;

                $paid = PAYMENT_SIMULATION;
                $status = $paid ? 'packing' : 'pending_payment';
                $payStatus = $paid ? 'paid' : 'pending';
                $uid = user_id();
                $email = $user['email'] ?? null;
                $phone = preg_replace('/[\s\-]/', '', $f['phone']);

                $io = $conn->prepare("INSERT INTO orders (order_code, user_id, subtotal, discount_amount, voucher_code, shipping_cost, service_fee, tax_amount, total_amount, status, payment_status, courier, shipping_name, shipping_phone, shipping_email, shipping_address, shipping_city, shipping_postal, tracking_number) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $io->bind_param('sidd' . 'sddd' . 'd' . 'ssssssssss',
                    $code, $uid, $t['subtotal'], $t['discount'], $t['voucher'], $t['shipping'], $t['service'], $t['tax'], $t['total'],
                    $status, $payStatus, $f['courier'], $f['name'], $phone, $email, $f['address'], $f['city'], $f['postal'], $tracking);
                if (!$io->execute()) throw new RuntimeException('Gagal menyimpan pesanan.');
                $orderId = $conn->insert_id;

                $ii = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, sku, image_url, options, qty, price) VALUES (?,?,?,?,?,?,?,?)");
                $us = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                foreach ($lines as $l) {
                    $p = $l['product'];
                    $nm = $p['checkout_name'] ?: product_card_name($p);
                    $opts = json_encode(array_map(fn($o) => ['label' => $o['label'], 'value' => $o['value']], $l['options']), JSON_UNESCAPED_UNICODE);
                    $pid = (int)$p['id']; $q = (int)$l['qty']; $pr = (float)$l['unit'];
                    $ii->bind_param('iissssid', $orderId, $pid, $nm, $p['sku'], $l['image'], $opts, $q, $pr);
                    $ii->execute();
                    $us->bind_param('iii', $q, $pid, $q);
                    $us->execute();
                    if ($us->affected_rows !== 1) throw new RuntimeException('Stok berubah. Silakan coba lagi.');
                    stock_log($conn, $pid, 'sale', -$q, 'Penjualan ' . $code, $orderId, $uid);
                }

                $m = $methods[$f['method']];
                $trx = 'TRX-' . $suffix . '-IDN';
                $pst = $paid ? 'settled' : 'pending';
                $paidAt = $paid ? date('Y-m-d H:i:s') : null;
                $ip = $conn->prepare("INSERT INTO payments (order_id, method, channel, channel_detail, transaction_id, status, amount, paid_at) VALUES (?,?,?,?,?,?,?,?)");
                $ip->bind_param('isssssds', $orderId, $f['method'], $m['channel'], $m['detail'], $trx, $pst, $t['total'], $paidAt);
                $ip->execute();

                if ($t['voucher']) {
                    $uv = $conn->prepare("UPDATE vouchers SET used_count = used_count + 1 WHERE code = ?");
                    $uv->bind_param('s', $t['voucher']);
                    $uv->execute();
                }
                add_order_history($conn, $orderId, 'placed', 'Pesanan masuk');
                add_order_history($conn, $orderId, $paid ? 'packing' : 'pending_payment', $paid ? 'Pembayaran terverifikasi, pesanan masuk antrean packing' : 'Menunggu pembayaran');

                // simpan alamat ke profil untuk checkout berikutnya
                $up = $conn->prepare("UPDATE users SET phone = ?, address = ?, city = ?, postal_code = ? WHERE id = ?");
                $up->bind_param('ssssi', $phone, $f['address'], $f['city'], $f['postal'], $uid);
                $up->execute();

                $conn->commit();
                unset($_SESSION['cart'], $_SESSION['voucher']);
                redirect('konfirmasi.php?order=' . urlencode($code));
            } catch (Throwable $err) {
                $conn->rollback();
                error_log('Checkout gagal: ' . $err->getMessage());
                $errors['general'] = $err instanceof RuntimeException ? $err->getMessage() : 'Checkout tidak dapat diproses. Silakan coba lagi.';
            }
        }
    }
}

$lines = cart_lines($conn);
if (!$lines) redirect('keranjang.php');
$t = cart_totals($conn, $lines, $f['courier']);
$f['voucher'] = $f['voucher'] ?: ($_SESSION['voucher'] ?? '');

$inp = 'w-full bg-paper rounded-lg px-4 py-3 text-[14px] outline-none focus:ring-2 focus:ring-black placeholder-gray-400';
$err = fn($k) => isset($errors[$k]) ? '<p class="text-[11px] text-red-600 mt-1">' . e($errors[$k]) . '</p>' : '';

page_head('Checkout & Pembayaran - AMBOKO', ['bodyClass' => 'bg-white']);
store_header('app', '', $conn);
echo '<div class="bg-black text-white"><div class="max-w-[1440px] mx-auto px-5 md:px-8 py-1.5 flex items-center justify-between text-[10px] font-bold tracking-[0.12em] uppercase gap-4 overflow-x-auto no-scrollbar whitespace-nowrap">'
   . '<span class="flex items-center gap-2"><i class="inline-block w-1.5 h-1.5 rounded-full bg-brand"></i>AMBOKO Secure Checkout</span>'
   . '<span class="hidden sm:inline">Gratis ongkir seluruh Jawa &nbsp;•&nbsp; Garansi penukaran 3 hari</span>'
   . '<span class="bg-white/0">IDR / EN-ID</span></div></div>';
?>

<main class="max-w-[1440px] mx-auto px-5 md:px-8 pt-6">
    <p class="text-[10px] font-bold tracking-[0.14em] uppercase mb-3">
        <a href="index.php" class="text-gray-500 hover:text-black">Home</a> <span class="text-gray-400">/</span>
        <a href="keranjang.php" class="text-gray-500 hover:text-black">Keranjang</a> <span class="text-gray-400">/</span> Checkout
    </p>

    <div class="bg-paper rounded-2xl px-6 md:px-8 py-6 flex items-end justify-between gap-4">
        <div>
            <p class="text-[10px] font-bold tracking-[0.14em] text-gray-500 uppercase mb-1.5">Langkah 02 dari 02 &mdash; Pembayaran</p>
            <h1 class="font-head font-extrabold text-[clamp(28px,3.6vw,44px)] leading-none tracking-tight uppercase">Checkout &amp; Pembayaran</h1>
        </div>
        <p class="hidden sm:flex items-center gap-2 text-[12px] text-gray-600"><i class="fa-solid fa-lock"></i> Sesi terenkripsi 256-bit</p>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="mt-5 bg-red-50 text-red-700 text-sm rounded-xl px-5 py-3"><i class="fa-solid fa-circle-exclamation mr-2"></i><?php echo e($errors['general']); ?></div>
    <?php endif; ?>

    <form method="post" action="checkout.php" id="checkoutForm" class="grid lg:grid-cols-[1fr_minmax(380px,520px)] gap-8 lg:gap-10 mt-6 items-start" novalidate>
        <?php echo csrf_field(); ?>
        <button type="submit" name="action" value="pay" class="hidden" tabindex="-1" aria-hidden="true"></button>

        <div class="space-y-8 min-w-0">
            <!-- 1. INFORMASI PENGIRIMAN -->
            <section class="rounded-2xl p-1 md:p-2">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="flex items-center gap-3 font-head font-extrabold text-[19px] uppercase tracking-tight"><span class="w-6 h-6 rounded-full bg-black text-white text-[11px] flex items-center justify-center font-sans font-bold">1</span> Informasi Pengiriman</h2>
                    <span class="text-[10px] font-bold tracking-[0.12em] text-gray-500 uppercase">Wajib diisi</span>
                </div>

                <label class="block text-[10px] font-bold tracking-[0.12em] uppercase mb-1.5">Nama Lengkap</label>
                <input type="text" name="name" value="<?php echo e($f['name']); ?>" class="<?php echo $inp; ?> mb-1" autocomplete="name"><?php echo $err('name'); ?>

                <label class="block text-[10px] font-bold tracking-[0.12em] uppercase mt-4 mb-1.5">Nomor Telepon / WhatsApp</label>
                <input type="tel" name="phone" value="<?php echo e($f['phone']); ?>" class="<?php echo $inp; ?> mb-1" autocomplete="tel"><?php echo $err('phone'); ?>

                <label class="block text-[10px] font-bold tracking-[0.12em] uppercase mt-4 mb-1.5">Alamat Lengkap (Jalan, No. Rumah, RT/RW, Kelurahan)</label>
                <textarea name="address" rows="3" class="<?php echo $inp; ?> mb-1 resize-none" autocomplete="street-address"><?php echo e($f['address']); ?></textarea><?php echo $err('address'); ?>

                <div class="grid grid-cols-[1fr_130px] sm:grid-cols-[1fr_190px] gap-4 mt-4">
                    <div>
                        <label class="block text-[10px] font-bold tracking-[0.12em] uppercase mb-1.5">Kota / Kabupaten</label>
                        <input type="text" name="city" value="<?php echo e($f['city']); ?>" class="<?php echo $inp; ?>"><?php echo $err('city'); ?>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold tracking-[0.12em] uppercase mb-1.5">Kode Pos</label>
                        <input type="text" name="postal" value="<?php echo e($f['postal']); ?>" maxlength="5" inputmode="numeric" class="<?php echo $inp; ?>"><?php echo $err('postal'); ?>
                    </div>
                </div>

                <p class="text-[10px] font-bold tracking-[0.12em] uppercase mt-6 mb-2">Opsi Kurir Pengiriman</p>
                <?php $regFee = $t['free_shipping'] ? 0 : REGULAR_SHIPPING; ?>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="cursor-pointer">
                        <input type="radio" name="courier" value="regular" class="peer sr-only" <?php echo $f['courier'] === 'regular' ? 'checked' : ''; ?>>
                        <span class="flex items-center gap-3 rounded-xl px-4 py-4 bg-paper text-ink peer-checked:bg-black peer-checked:text-white peer-checked:[&>i]:bg-white transition h-full">
                            <i class="w-4 h-4 rounded-full border-2 border-current shrink-0 courier-dot"></i>
                            <span class="flex-1"><b class="block text-[14px]">Reguler</b><span class="text-[11px] opacity-70">Estimasi 2-3 hari kerja</span></span>
                            <span class="text-[10px] font-bold tracking-wider rounded-full px-2.5 py-1 bg-gray-300 text-black"><?php echo $regFee ? e(rp($regFee)) : 'GRATIS'; ?></span>
                        </span>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="courier" value="express" class="peer sr-only" <?php echo $f['courier'] === 'express' ? 'checked' : ''; ?>>
                        <span class="flex items-center gap-3 rounded-xl px-4 py-4 bg-paper text-ink peer-checked:bg-black peer-checked:text-white peer-checked:[&>i]:bg-white transition h-full">
                            <i class="w-4 h-4 rounded-full border-2 border-current shrink-0"></i>
                            <span class="flex-1"><b class="block text-[14px]">Express Next Day</b><span class="text-[11px] opacity-70">Estimasi 1 hari kerja</span></span>
                            <span class="text-[11px] font-bold">+<?php echo e(rp(EXPRESS_SURCHARGE)); ?></span>
                        </span>
                    </label>
                </div>
            </section>

            <!-- 2. METODE PEMBAYARAN -->
            <section class="rounded-2xl p-1 md:p-2">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="flex items-center gap-3 font-head font-extrabold text-[19px] uppercase tracking-tight"><span class="w-6 h-6 rounded-full bg-black text-white text-[11px] flex items-center justify-center font-sans font-bold">2</span> Metode Pembayaran</h2>
                    <span class="text-[10px] font-bold tracking-[0.12em] text-gray-500 uppercase">Instan &amp; Otomatis</span>
                </div>
                <div class="space-y-3">
                    <?php foreach ($methods as $key => $m): ?>
                    <label class="cursor-pointer block">
                        <input type="radio" name="method" value="<?php echo $key; ?>" class="peer sr-only" <?php echo $f['method'] === $key ? 'checked' : ''; ?>>
                        <span class="flex items-start gap-4 rounded-xl px-5 py-4 bg-paper text-ink peer-checked:bg-black peer-checked:text-white peer-checked:[&>i]:bg-white transition">
                            <i class="w-4 h-4 mt-1 rounded-full border-2 border-current shrink-0"></i>
                            <span class="flex-1 min-w-0">
                                <span class="flex items-center justify-between gap-3">
                                    <b class="text-[14px]"><?php echo e($m['name']); ?></b>
                                    <span class="text-[9px] font-bold tracking-[0.1em] shrink-0 <?php echo $key === 'qris' ? 'bg-brand text-white rounded px-1.5 py-0.5' : 'text-gray-500'; ?>"><?php echo e($m['tag']); ?></span>
                                </span>
                                <span class="block text-[12px] opacity-70 mt-1"><?php echo e($m['desc']); ?></span>
                            </span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="bg-paper rounded-xl px-5 py-4 mt-4 flex gap-3 text-[12px] text-gray-600 leading-relaxed">
                    <i class="fa-solid fa-circle-info mt-0.5 text-gray-800"></i>
                    <p>Instruksi pembayaran serta nomor barcode/Virtual Account akan langsung ditampilkan di layar dan dikirimkan ke WhatsApp setelah Anda menekan tombol <b class="text-black">Bayar Sekarang</b>.</p>
                </div>
            </section>
        </div>

        <!-- RINGKASAN PESANAN -->
        <aside class="bg-paper rounded-2xl p-6 lg:sticky lg:top-24">
            <div class="flex items-center justify-between mb-5">
                <h2 class="font-head font-extrabold text-[19px] uppercase tracking-tight">Ringkasan Pesanan</h2>
                <span class="bg-black text-white text-[10px] font-bold rounded-full px-2.5 py-1"><?php echo count($lines); ?> Item</span>
            </div>
            <div class="space-y-3">
                <?php foreach ($lines as $l):
                    $p = $l['product'];
                    $opts = [];
                    foreach ($l['options'] as $o) if (in_array($o['label'], ['WARNA', 'SIZE'], true)) $opts[$o['label']] = $o['value'];
                    $sub = [];
                    if (isset($opts['WARNA'])) $sub[] = $opts['WARNA'];
                    if (isset($opts['SIZE'])) $sub[] = 'Size ' . $opts['SIZE'];
                    $sub[] = $l['qty'] . 'x';
                ?>
                <div class="bg-white rounded-xl p-2.5 flex items-center gap-3.5">
                    <span class="w-[52px] h-[60px] rounded-lg bg-[#ececed] overflow-hidden shrink-0"><img src="<?php echo e(img($l['image'])); ?>" alt="" class="w-full h-full object-cover object-top"></span>
                    <div class="min-w-0">
                        <p class="font-bold text-[14px] leading-tight truncate"><?php echo e($p['checkout_name'] ?: product_card_name($p)); ?></p>
                        <p class="text-[11px] text-gray-500 mt-0.5"><?php echo e(implode(' • ', $sub)); ?></p>
                        <p class="font-bold text-[13px] mt-0.5"><?php echo e(rp($l['subtotal'])); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Voucher -->
            <div class="mt-4 bg-white rounded-xl flex items-center gap-2 pl-4 pr-1.5 py-1.5">
                <i class="fa-solid fa-ticket text-gray-500 text-xs"></i>
                <input type="text" name="voucher" value="<?php echo e($f['voucher']); ?>" placeholder="KODE VOUCHER / PROMO" class="flex-1 min-w-0 text-[11px] font-semibold tracking-wider uppercase outline-none bg-transparent placeholder-gray-500">
                <?php if (!empty($_SESSION['voucher'])): ?>
                    <button type="submit" name="action" value="remove_voucher" formnovalidate class="bg-gray-200 hover:bg-gray-300 text-[10px] font-bold tracking-wider rounded-lg px-4 py-2.5 transition">HAPUS</button>
                <?php else: ?>
                    <button type="submit" name="action" value="voucher" formnovalidate class="bg-gray-200 hover:bg-gray-300 text-[10px] font-bold tracking-wider rounded-lg px-4 py-2.5 transition">GUNAKAN</button>
                <?php endif; ?>
            </div>
            <?php if ($voucherMsg): ?><p class="text-[11px] mt-1.5 ml-1 <?php echo $voucherOk ? 'text-green-700' : 'text-red-600'; ?>"><?php echo e($voucherMsg); ?></p><?php endif; ?>

            <dl class="space-y-2.5 text-[13px] mt-5">
                <div class="flex justify-between"><dt class="text-gray-600">Subtotal Produk</dt><dd class="font-bold"><?php echo e(rp($t['subtotal'])); ?></dd></div>
                <?php if ($t['discount']): ?>
                <div class="flex justify-between"><dt class="text-brand">Diskon Voucher (<?php echo e($t['voucher']); ?>)</dt><dd class="font-bold text-brand">-<?php echo e(rp($t['discount'])); ?></dd></div>
                <?php endif; ?>
                <div class="flex justify-between items-center">
                    <dt class="text-gray-600">Biaya Pengiriman <?php if ($t['free_shipping'] && $f['courier'] === 'regular'): ?><span class="ml-1 bg-brand/10 text-brand text-[9px] font-bold tracking-wider rounded px-1.5 py-0.5">PROMO</span><?php endif; ?></dt>
                    <dd class="font-bold"><?php echo $t['shipping'] ? e(rp($t['shipping'])) : 'Rp0 (Bebas Ongkir)'; ?></dd>
                </div>
                <div class="flex justify-between"><dt class="text-gray-600">Biaya Layanan &amp; Verifikasi</dt><dd class="font-bold"><?php echo $t['service'] ? e(rp($t['service'])) : 'Gratis'; ?></dd></div>
            </dl>

            <div class="bg-white rounded-xl px-5 py-4 mt-4 flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-bold tracking-[0.14em] text-gray-500 uppercase">Total Pembayaran</p>
                    <p class="text-[10px] text-gray-500 mt-0.5">Termasuk Pajak (PPN <?php echo PPN_RATE; ?>%)</p>
                </div>
                <p class="font-head font-extrabold text-[clamp(24px,2.6vw,32px)] leading-none"><?php echo e(rp($t['total'])); ?></p>
            </div>

            <button type="submit" name="action" value="pay" class="mt-5 w-full flex items-center justify-center gap-2 bg-black text-white rounded-xl py-4 text-[12px] font-bold tracking-[0.1em] uppercase hover:bg-gray-800 active:scale-[.99] transition">
                Bayar Sekarang &mdash; <?php echo e(rp($t['total'])); ?> <i class="fa-solid fa-arrow-right text-xs"></i>
            </button>
            <p class="text-center text-[11px] text-gray-600 mt-3"><i class="fa-solid fa-shield-halved text-brand mr-1"></i>Transaksi aman &amp; terenkripsi SSL 256-bit</p>

            <div class="grid grid-cols-2 gap-4 mt-6 pt-5 border-t border-gray-200 text-[11px]">
                <div class="flex gap-2.5"><i class="fa-solid fa-truck-fast mt-0.5"></i><div><b class="block text-[12px]">Kirim Cepat</b><span class="text-gray-500">Dispatch dalam 24 jam</span></div></div>
                <div class="flex gap-2.5"><i class="fa-solid fa-arrows-rotate mt-0.5"></i><div><b class="block text-[12px]">Tukar Ukuran</b><span class="text-gray-500">Gratis biaya retur</span></div></div>
            </div>
        </aside>
    </form>
</main>

<?php
store_footer('app');
page_end("<script>
document.querySelectorAll('input[name=courier]').forEach(function(r){
  r.addEventListener('change', function(){
    var f=document.getElementById('checkoutForm'), h=document.createElement('input');
    h.type='hidden'; h.name='action'; h.value='recalc'; f.appendChild(h); f.submit();
  });
});
</script>");
