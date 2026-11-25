<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/order_view.php';

require_login('konfirmasi.php?order=' . urlencode($_GET['order'] ?? ''));

$o = order_load($conn, (string)($_GET['order'] ?? ''));
if (!$o || ((int)$o['user_id'] !== user_id() && !is_admin())) {
    flash('error', 'Pesanan tidak ditemukan.');
    redirect('pesanan.php');
}

$pay = $o['payment'];
$methods = payment_methods();
$paid = $o['payment_status'] === 'paid';
$firstName = explode(' ', trim($o['shipping_name']))[0];
$createdAt = strtotime($o['created_at']);
$isToday = date('Y-m-d', $createdAt) === date('Y-m-d');
$whenLabel = ($isToday ? 'Hari ini' : date_short($o['created_at'])) . ', ' . date('H:i', $createdAt) . ' WIB';
$payLabel = $pay ? ($methods[$pay['method']]['paid_label'] ?? 'Terverifikasi') : '-';
$statusPill = ['pending_payment' => 'Menunggu Bayar', 'placed' => 'Diproses', 'packing' => 'Diproses', 'shipped' => 'Dikirim', 'delivered' => 'Selesai', 'cancelled' => 'Dibatalkan'][$o['status']] ?? 'Diproses';
$phoneDisp = preg_replace('/^(\+62|62)/', '0', $o['shipping_phone']);
$phoneDisp = preg_match('/^0(\d{3})(\d{4})(\d+)$/', $phoneDisp, $m) ? "0{$m[1]}-{$m[2]}-{$m[3]}" : $phoneDisp;
$waNumber = preg_replace('/^0/', '62', preg_replace('/\D/', '', $o['shipping_phone']));

page_head('Konfirmasi Pesanan - AMBOKO', ['bodyClass' => 'bg-white']);
store_header('app', '', $conn);
ticker_static(['ORDER CONFIRMED • BATCH #26-AUTUMN', '·', 'EST. DISPATCH 24H', '·', 'AMBOKO LOGISTICS SYSTEM', '·', 'SECURE ESCROW SETTLED', '·', 'URBAN UTILITY STATEMENT'], false);
?>

<main class="max-w-[1320px] mx-auto px-5 md:px-8 pt-5">
    <div class="flex items-center justify-between flex-wrap gap-3 text-[10px] font-bold tracking-[0.12em] uppercase">
        <p><a href="index.php" class="text-gray-500 hover:text-black">Home</a> <span class="text-gray-400">/</span> <a href="keranjang.php" class="text-gray-500 hover:text-black">Checkout</a> <span class="text-gray-400">/</span> Status Pesanan</p>
        <p class="hidden sm:flex items-center gap-2 bg-paper rounded-full px-4 py-1.5 normal-case tracking-normal text-[11px]">
            <span><i class="inline-block w-1.5 h-1.5 rounded-full bg-black mr-1.5"></i>1. Kantong</span><span class="text-gray-400">→</span>
            <span><i class="inline-block w-1.5 h-1.5 rounded-full bg-black mr-1.5"></i>2. Pembayaran</span><span class="text-gray-400">→</span>
            <span><i class="inline-block w-1.5 h-1.5 rounded-full bg-black mr-1.5"></i>3. Selesai</span>
        </p>
    </div>

    <!-- HERO KONFIRMASI -->
    <section class="text-center pt-10 pb-8 border-b border-gray-100">
        <span class="inline-flex w-14 h-14 rounded-full bg-black text-white items-center justify-center text-xl mb-5"><i class="fa-solid fa-check"></i></span>
        <p class="text-[10px] font-bold tracking-[0.16em] text-gray-500 uppercase">Transaksi <?php echo $paid ? 'Sukses' : 'Dibuat'; ?> &bull; #<?php echo e($o['order_code']); ?></p>
        <h1 class="font-head font-extrabold text-[clamp(30px,4.4vw,52px)] tracking-tight leading-none uppercase mt-3 mb-4"><?php echo $paid ? 'Pesanan Berhasil Diterima' : 'Pesanan Diterima'; ?></h1>
        <p class="text-gray-600 text-[14px] leading-relaxed max-w-xl mx-auto">
            Terima kasih, <b class="text-black"><?php echo e($firstName); ?></b>. Pesanan <b class="text-black">#<?php echo e($o['order_code']); ?></b>
            <?php echo $paid ? 'telah dikonfirmasi dan langsung dialokasikan ke unit packing AMBOKO Warehouse Jakarta.' : 'sudah kami catat. Selesaikan pembayaran agar pesanan segera diproses.'; ?>
        </p>
        <div class="flex flex-wrap justify-center gap-2.5 mt-5 text-[11px] font-semibold">
            <?php if ($paid): ?>
                <span class="bg-paper rounded-full px-4 py-2 flex items-center gap-2"><i class="inline-block w-2 h-2 rounded-full bg-black"></i><b>LUNAS (<?php echo e($payLabel); ?>)</b></span>
            <?php else: ?>
                <span class="bg-amber-100 text-amber-800 rounded-full px-4 py-2 font-bold">MENUNGGU PEMBAYARAN</span>
            <?php endif; ?>
            <span class="bg-paper rounded-full px-4 py-2 text-gray-700"><i class="fa-regular fa-clock mr-1.5"></i><?php echo e($whenLabel); ?></span>
            <?php if ($paid): ?><span class="bg-paper rounded-full px-4 py-2 text-gray-700"><i class="fa-regular fa-circle-check mr-1.5"></i>Settlement Otomatis</span><?php endif; ?>
        </div>
    </section>

    <div class="grid lg:grid-cols-[1fr_minmax(360px,470px)] gap-8 lg:gap-10 pt-8 items-start">
        <!-- ================= KIRI ================= -->
        <div class="space-y-8 min-w-0">
            <?php if (!$paid && $pay && $o['status'] !== 'cancelled'):
                $waAdmin = preg_replace('/\D/', '', site_setting('whatsapp', '+62 812-8920-3301'));
                $waAdmin = preg_replace('/^0/', '62', $waAdmin);
                $waPay = 'https://wa.me/' . $waAdmin . '?text=' . urlencode('Halo AMBOKO, saya sudah membayar pesanan ' . $o['order_code'] . ' sebesar ' . rp($o['total_amount']) . '. Berikut bukti pembayarannya.');
            ?>
            <!-- Instruksi pembayaran -->
            <section class="bg-paper rounded-2xl p-6">
                <div class="flex items-center justify-between mb-5 gap-3">
                    <h2 class="font-head font-extrabold text-[17px] uppercase tracking-tight flex items-center gap-2.5"><i class="fa-solid fa-qrcode text-[15px]"></i> Selesaikan Pembayaran</h2>
                    <span class="bg-amber-100 text-amber-800 text-[10px] font-bold rounded-full px-3 py-1">MENUNGGU BAYAR</span>
                </div>
                <div class="bg-white rounded-xl px-5 py-4 flex items-center justify-between gap-4 mb-4">
                    <div><p class="text-[9px] font-bold tracking-[0.14em] text-gray-500 uppercase">Total yang harus dibayar</p>
                        <p class="font-head font-extrabold text-[26px] leading-none mt-1"><?php echo e(rp($o['total_amount'])); ?></p></div>
                    <button type="button" data-copy="<?php echo (int)$o['total_amount']; ?>" class="copy-btn text-[10px] font-bold tracking-[0.1em] uppercase bg-paper hover:bg-gray-200 rounded-full px-4 py-2 transition"><i class="fa-regular fa-copy mr-1"></i>Salin nominal</button>
                </div>

                <?php if ($pay['method'] === 'qris'): ?>
                <div class="grid sm:grid-cols-[220px_1fr] gap-5 items-center">
                    <div class="bg-white rounded-xl p-3 mx-auto w-[220px]"><img src="<?php echo e(qris_image()); ?>" alt="Kode QRIS AMBOKO" class="w-full h-auto block"></div>
                    <ol class="text-[13px] text-gray-700 leading-relaxed space-y-2 list-decimal pl-5">
                        <li>Buka aplikasi e-wallet atau mobile banking (GoPay, OVO, ShopeePay, Dana, BCA, dll).</li>
                        <li>Pilih <b>Scan / Bayar QRIS</b>, lalu pindai kode di samping.</li>
                        <li>Masukkan nominal <b><?php echo e(rp($o['total_amount'])); ?></b> lalu konfirmasi.</li>
                        <li>Kirim bukti bayar lewat tombol WhatsApp di bawah; admin akan menandai pesanan lunas.</li>
                    </ol>
                </div>
                <?php elseif ($pay['method'] === 'va'): ?>
                <p class="text-[10px] font-bold tracking-[0.12em] uppercase mb-2">Pilih Bank</p>
                <div class="flex flex-wrap gap-2 mb-4" id="vaTabs">
                    <?php $first = true; foreach (VA_BANKS as $bk => [$bn]): ?>
                        <button type="button" data-bank="<?php echo e($bk); ?>" class="va-tab rounded-full px-4 py-2 text-[11px] font-bold tracking-wider uppercase transition <?php echo $first ? 'bg-black text-white' : 'bg-white hover:bg-gray-200'; ?>"><?php echo e($bn); ?></button>
                    <?php $first = false; endforeach; ?>
                </div>
                <?php $first = true; foreach (VA_BANKS as $bk => [$bn]): $vn = va_number($bk, (int)$o['id']); ?>
                <div class="va-box bg-white rounded-xl px-5 py-4 flex items-center justify-between gap-4 <?php echo $first ? '' : 'hidden'; ?>" data-bank="<?php echo e($bk); ?>">
                    <div class="min-w-0"><p class="text-[9px] font-bold tracking-[0.14em] text-gray-500 uppercase">Nomor Virtual Account <?php echo e($bn); ?></p>
                        <p class="font-head font-extrabold text-[clamp(20px,2.4vw,28px)] tracking-[0.08em] mt-1 break-all"><?php echo e(trim(chunk_split($vn, 4, ' '))); ?></p></div>
                    <button type="button" data-copy="<?php echo e($vn); ?>" class="copy-btn shrink-0 text-[10px] font-bold tracking-[0.1em] uppercase bg-paper hover:bg-gray-200 rounded-full px-4 py-2 transition"><i class="fa-regular fa-copy mr-1"></i>Salin</button>
                </div>
                <?php $first = false; endforeach; ?>
                <ol class="text-[13px] text-gray-700 leading-relaxed space-y-2 list-decimal pl-5 mt-4">
                    <li>Buka m-banking / ATM / internet banking bank yang dipilih, lalu pilih menu <b>Transfer &rarr; Virtual Account</b>.</li>
                    <li>Masukkan nomor VA di atas dan pastikan nominal <b><?php echo e(rp($o['total_amount'])); ?></b>.</li>
                    <li>Selesaikan transfer, lalu kirim bukti bayar lewat tombol WhatsApp di bawah; admin akan menandai pesanan lunas.</li>
                </ol>
                <?php else: ?>
                <p class="text-[13px] text-gray-700 leading-relaxed">Untuk pembayaran kartu kredit / debit, tautan pembayaran aman akan dikirim oleh Customer Care melalui WhatsApp. Klik tombol di bawah untuk meminta tautannya.</p>
                <?php endif; ?>

                <a href="<?php echo e($waPay); ?>" target="_blank" rel="noopener" class="mt-5 flex items-center justify-center gap-2 bg-black text-white rounded-xl py-3.5 text-[12px] font-bold tracking-[0.1em] uppercase hover:bg-gray-800 transition"><i class="fa-brands fa-whatsapp text-[15px]"></i> <?php echo $pay['method'] === 'card' ? 'Minta Tautan Pembayaran' : 'Kirim Bukti Pembayaran'; ?></a>
            </section>
            <?php endif; ?>

            <!-- Status pengiriman -->
            <section class="bg-paper rounded-2xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-head font-extrabold text-[17px] uppercase tracking-tight flex items-center gap-2.5"><i class="fa-solid fa-truck text-[15px]"></i> Status Pengiriman</h2>
                    <span class="bg-black text-white text-[10px] font-bold rounded-full px-3 py-1"><?php echo e($statusPill); ?></span>
                </div>
                <?php if ($o['status'] === 'cancelled'): ?>
                    <p class="bg-red-50 text-red-700 text-sm rounded-xl px-4 py-3">Pesanan ini telah dibatalkan.</p>
                <?php else: ?>
                    <?php echo order_timeline_html($o); ?>
                <?php endif; ?>
                <div class="bg-white rounded-xl px-5 py-4 mt-6 flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <p class="text-[9px] font-bold tracking-[0.14em] text-gray-500 uppercase">Nomor Resi / AWB</p>
                        <p class="font-bold text-[14px] mt-0.5 flex items-center gap-2"><span id="awb"><?php echo e($o['tracking_number']); ?></span>
                            <button type="button" id="copyAwb" class="text-gray-500 hover:text-black" aria-label="Salin"><i class="fa-regular fa-copy text-[13px]"></i></button></p>
                        <p class="text-[10px] text-gray-500 mt-0.5"><?php echo $o['status'] === 'shipped' || $o['status'] === 'delivered' ? 'Resi aktif. Lacak pesanan Anda.' : 'Belum aktif, menunggu kurir scan barcode saat pick-up sore ini.'; ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-[9px] font-bold tracking-[0.14em] text-gray-500 uppercase">Layanan</p>
                        <p class="font-bold text-[14px] mt-0.5">AMBOKO Logistics <?php echo $o['courier'] === 'express' ? 'Express' : 'Standard'; ?></p>
                    </div>
                </div>
            </section>

            <!-- Detail pengiriman -->
            <section>
                <h2 class="font-head font-extrabold text-[17px] uppercase tracking-tight flex items-center gap-2.5 mb-4"><i class="fa-solid fa-location-dot text-[15px]"></i> Detail Pengiriman</h2>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="bg-paper rounded-xl p-4">
                        <p class="text-[9px] font-bold tracking-[0.14em] text-gray-500 uppercase mb-1.5">Penerima</p>
                        <p class="font-bold text-[14px]"><?php echo e($o['shipping_name']); ?></p>
                        <p class="text-[12px] text-gray-600 mt-0.5"><?php echo e($phoneDisp); ?></p>
                        <p class="text-[12px] text-gray-600"><?php echo e($o['shipping_email']); ?></p>
                    </div>
                    <div class="bg-paper rounded-xl p-4">
                        <p class="text-[9px] font-bold tracking-[0.14em] text-gray-500 uppercase mb-1.5">Kurir &amp; Estimasi</p>
                        <p class="font-bold text-[14px]"><?php echo $o['courier'] === 'express' ? 'Express Next Day (AMBOKO Express)' : 'Reguler (AMBOKO Express)'; ?></p>
                        <p class="text-[12px] text-gray-600 mt-0.5">Estimasi Tiba: <?php echo $o['courier'] === 'express' ? '1 Hari Kerja' : '2 - 3 Hari Kerja'; ?></p>
                        <p class="text-[11px] text-gray-500 mt-0.5">Tarif: <?php echo (float)$o['shipping_cost'] > 0 ? e(rp($o['shipping_cost'])) : 'Subsidi Bebas Ongkir (Rp0)'; ?></p>
                    </div>
                </div>
                <div class="bg-paper rounded-xl p-4 mt-3">
                    <p class="text-[9px] font-bold tracking-[0.14em] text-gray-500 uppercase mb-1.5">Alamat Tujuan Pengiriman</p>
                    <p class="font-bold text-[14px]"><?php echo e($o['shipping_address']); ?></p>
                    <p class="text-[12px] text-gray-600 mt-0.5"><?php echo e($o['shipping_city'] . ' ' . $o['shipping_postal']); ?></p>
                    <?php if ($o['address_note']): ?><p class="text-[11px] text-gray-500 mt-1">Patokan: <?php echo e($o['address_note']); ?></p><?php endif; ?>
                </div>
            </section>

            <!-- Metode pembayaran -->
            <section>
                <h2 class="font-head font-extrabold text-[17px] uppercase tracking-tight flex items-center gap-2.5 mb-4"><i class="fa-solid fa-wallet text-[15px]"></i> Metode Pembayaran</h2>
                <?php if ($pay): ?>
                <div class="grid sm:grid-cols-3 gap-3">
                    <div class="bg-paper rounded-xl p-4"><p class="text-[9px] font-bold tracking-[0.14em] text-gray-500 uppercase mb-1.5">Kanal Bayar</p><p class="font-bold text-[14px]"><?php echo e($pay['channel']); ?></p><p class="text-[11px] text-gray-500 mt-0.5"><?php echo e($pay['channel_detail']); ?></p></div>
                    <div class="bg-paper rounded-xl p-4"><p class="text-[9px] font-bold tracking-[0.14em] text-gray-500 uppercase mb-1.5">ID Transaksi</p><p class="font-bold text-[14px]">#<?php echo e($pay['transaction_id']); ?></p><p class="text-[11px] text-gray-500 mt-0.5">Merchant: AMBOKO APPAREL</p></div>
                    <div class="bg-paper rounded-xl p-4"><p class="text-[9px] font-bold tracking-[0.14em] text-gray-500 uppercase mb-1.5">Status Dana</p>
                        <p class="font-bold text-[14px]"><i class="inline-block w-2 h-2 rounded-full <?php echo $pay['status'] === 'settled' ? 'bg-black' : 'bg-amber-500'; ?> mr-1.5"></i><?php echo $pay['status'] === 'settled' ? 'Settled / Sukses' : 'Menunggu'; ?></p>
                        <p class="text-[11px] text-gray-500 mt-0.5"><?php echo $pay['status'] === 'settled' ? 'Verifikasi 0 detik' : 'Belum diterima'; ?></p></div>
                </div>
                <?php endif; ?>
            </section>
        </div>

        <!-- ================= KANAN ================= -->
        <aside class="space-y-4 min-w-0">
            <section>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-head font-extrabold text-[17px] uppercase tracking-tight flex items-center gap-2.5"><i class="fa-solid fa-bag-shopping text-[15px]"></i> Item Pesanan (<?php echo count($o['items']); ?>)</h2>
                    <span class="text-[10px] font-bold tracking-[0.12em] text-gray-500 uppercase">Lookbook 2026</span>
                </div>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($o['items'] as $it): ?>
                    <div class="flex items-center gap-4 py-4 first:pt-0">
                        <span class="w-[58px] h-[68px] rounded-lg bg-[#ececed] overflow-hidden shrink-0"><img src="<?php echo e(img($it['image_url'])); ?>" alt="" class="w-full h-full object-cover object-top"></span>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-[13px] uppercase tracking-wide truncate"><?php echo e($it['product_name']); ?></p>
                            <p class="text-[11px] text-gray-500 mt-0.5"><?php echo e(order_item_variant($it)); ?></p>
                            <p class="text-[11px] text-gray-500">Qty: <?php echo (int)$it['qty']; ?> pcs</p>
                        </div>
                        <p class="font-bold text-[14px] shrink-0"><?php echo e(rp($it['price'] * $it['qty'])); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="bg-paper rounded-2xl p-5">
                <dl class="space-y-2 text-[13px]">
                    <div class="flex justify-between"><dt class="text-gray-600">Subtotal Produk</dt><dd class="font-bold"><?php echo e(rp($o['subtotal'])); ?></dd></div>
                    <?php if ((float)$o['discount_amount'] > 0): ?>
                    <div class="flex justify-between"><dt class="text-brand">Diskon Voucher (<?php echo e($o['voucher_code']); ?>)</dt><dd class="font-bold text-brand">-<?php echo e(rp($o['discount_amount'])); ?></dd></div>
                    <?php endif; ?>
                    <div class="flex justify-between"><dt class="text-gray-600">Biaya Pengiriman (<?php echo $o['courier'] === 'express' ? 'Express' : 'Reguler'; ?>)</dt><dd class="font-bold"><?php echo (float)$o['shipping_cost'] > 0 ? e(rp($o['shipping_cost'])) : 'Gratis (Rp0)'; ?></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">Biaya Penanganan / Layanan</dt><dd class="font-bold"><?php echo e(rp($o['service_fee'])); ?></dd></div>
                </dl>
                <div class="border-t border-gray-300 mt-4 pt-4 flex items-end justify-between">
                    <span class="text-[11px] font-bold tracking-[0.12em] uppercase">Total Pembayaran</span>
                    <span class="text-right"><b class="block font-head font-extrabold text-[30px] leading-none"><?php echo e(rp($o['total_amount'])); ?></b><span class="text-[10px] text-gray-500">Termasuk PPN <?php echo PPN_RATE; ?>%</span></span>
                </div>
            </section>

            <section class="bg-paper rounded-xl p-4 flex gap-3 text-[12px]">
                <i class="fa-regular fa-message mt-0.5"></i>
                <div><p class="font-bold text-[10px] tracking-[0.12em] uppercase mb-1">Pemberitahuan Otomatis Terkirim</p>
                <p class="text-gray-600 leading-relaxed">Salinan invoice resmi &amp; tautan live tracking telah dikirimkan ke WhatsApp (<b class="text-black"><?php echo e(preg_replace('/\D/', '', $phoneDisp)); ?></b>) dan inbox <b class="text-black"><?php echo e($o['shipping_email']); ?></b>.</p></div>
            </section>

            <a href="lacak.php?order=<?php echo urlencode($o['order_code']); ?>" class="flex items-center justify-center gap-2 bg-black text-white rounded-xl py-4 text-[12px] font-bold tracking-[0.1em] uppercase hover:bg-gray-800 transition"><i class="fa-regular fa-compass"></i> Lacak Pesanan (Track Order)</a>
            <a href="katalog.php" class="flex items-center justify-center gap-2 bg-[#e6e6e7] rounded-xl py-3.5 text-[12px] font-bold tracking-[0.1em] uppercase hover:bg-[#d9d9db] transition"><i class="fa-solid fa-store"></i> Kembali ke Katalog AMBOKO</a>
            <p class="text-center text-[10px] font-bold tracking-[0.12em] text-gray-500 uppercase pt-1"><i class="fa-solid fa-shield-halved mr-1"></i>Garansi tukar ukuran 3 hari &nbsp;•&nbsp; <i class="fa-solid fa-headset mr-1"></i>Bantuan 24/7</p>
        </aside>
    </div>

    <div class="bg-paper rounded-2xl mt-10 px-5 py-4 flex items-center justify-between gap-4 max-w-[560px] ml-auto">
        <div class="flex items-center gap-3"><span class="w-9 h-9 rounded-full bg-[#e0e0e1] flex items-center justify-center"><i class="fa-regular fa-circle-question"></i></span>
            <div><p class="text-[10px] font-bold tracking-[0.12em] uppercase">Butuh Bantuan Cepat?</p><p class="text-[12px] text-gray-600">Konsultasi pengiriman &amp; kurir via Customer Care</p></div></div>
        <a href="https://wa.me/<?php echo e($waNumber ?: '6281200000000'); ?>?text=<?php echo urlencode('Halo AMBOKO, saya butuh bantuan untuk pesanan ' . $o['order_code']); ?>" target="_blank" rel="noopener" class="bg-black text-white text-[10px] font-bold tracking-[0.1em] rounded-full px-5 py-2.5 shrink-0 hover:bg-gray-800">CHAT CS</a>
    </div>
</main>

<?php
store_footer('app');
page_end(<<<'JS'
<script>
document.getElementById('copyAwb')?.addEventListener('click',function(){var t=document.getElementById('awb').textContent;navigator.clipboard&&navigator.clipboard.writeText(t);this.innerHTML='<i class="fa-solid fa-check text-[13px]"></i>';});
document.querySelectorAll('.copy-btn').forEach(function(b){b.addEventListener('click',function(){
  navigator.clipboard&&navigator.clipboard.writeText(b.dataset.copy);
  var o=b.innerHTML; b.innerHTML='<i class="fa-solid fa-check mr-1"></i>Tersalin'; setTimeout(function(){b.innerHTML=o;},1500);
});});
document.querySelectorAll('.va-tab').forEach(function(t){t.addEventListener('click',function(){
  document.querySelectorAll('.va-tab').forEach(function(x){var on=x===t;x.classList.toggle('bg-black',on);x.classList.toggle('text-white',on);x.classList.toggle('bg-white',!on);});
  document.querySelectorAll('.va-box').forEach(function(v){v.classList.toggle('hidden',v.dataset.bank!==t.dataset.bank);});
});});
</script>
JS);
