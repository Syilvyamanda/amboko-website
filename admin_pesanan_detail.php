<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';
include 'includes/order_view.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$code = $conn->query("SELECT order_code FROM orders WHERE id = $id")->fetch_row()[0] ?? null;
if (!$code) { flash('error', 'Pesanan tidak ditemukan.'); redirect('admin_pesanan.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $o = order_load($conn, $code);
    $action = $_POST['action'] ?? '';
    $flow = ['pending_payment' => null, 'placed' => 'packing', 'packing' => 'shipped', 'shipped' => 'delivered'];

    if ($action === 'mark_paid' && $o['payment_status'] === 'pending') {
        $conn->query("UPDATE orders SET payment_status = 'paid', status = 'packing' WHERE id = $id");
        $conn->query("UPDATE payments SET status = 'settled', paid_at = NOW() WHERE order_id = $id");
        add_order_history($conn, $id, 'packing', 'Pembayaran dikonfirmasi admin');
        flash('success', 'Pembayaran ditandai lunas. Pesanan masuk tahap packing.');
    } elseif ($action === 'advance' && !empty($flow[$o['status']])) {
        $next = $flow[$o['status']];
        $up = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $up->bind_param('si', $next, $id); $up->execute();
        add_order_history($conn, $id, $next, ORDER_STATUS[$next][0]);
        flash('success', 'Status diperbarui: ' . ORDER_STATUS[$next][0] . '.');
    } elseif ($action === 'tracking') {
        $trk = trim($_POST['tracking_number'] ?? '');
        $up = $conn->prepare("UPDATE orders SET tracking_number = ? WHERE id = ?");
        $up->bind_param('si', $trk, $id); $up->execute();
        flash('success', 'Nomor resi disimpan.');
    } elseif ($action === 'note') {
        $note = trim($_POST['address_note'] ?? '') ?: null;
        $up = $conn->prepare("UPDATE orders SET address_note = ? WHERE id = ?");
        $up->bind_param('si', $note, $id); $up->execute();
        flash('success', 'Patokan alamat disimpan.');
    } elseif ($action === 'cancel' && !in_array($o['status'], ['cancelled', 'delivered'], true)) {
        $conn->begin_transaction();
        try {
            restore_order_stock($conn, $id);
            $refund = $o['payment_status'] === 'paid' ? 'refunded' : 'failed';
            $conn->query("UPDATE orders SET status = 'cancelled', payment_status = '$refund' WHERE id = $id");
            $conn->query("UPDATE payments SET status = '" . ($refund === 'refunded' ? 'refunded' : 'failed') . "' WHERE order_id = $id");
            if ($o['voucher_code']) { $vc = $conn->prepare("UPDATE vouchers SET used_count = GREATEST(used_count - 1, 0) WHERE code = ?"); $vc->bind_param('s', $o['voucher_code']); $vc->execute(); }
            add_order_history($conn, $id, 'cancelled', 'Pesanan dibatalkan admin; stok dikembalikan');
            $conn->commit();
            flash('success', 'Pesanan dibatalkan dan stok dikembalikan.');
        } catch (Throwable $e) { $conn->rollback(); flash('error', 'Gagal membatalkan pesanan.'); }
    }
    redirect('admin_pesanan_detail.php?id=' . $id);
}

$o = order_load($conn, $code);
$nextLabel = ['placed' => 'Mulai Packing', 'packing' => 'Tandai Dikirim (Pick-up Kurir)', 'shipped' => 'Tandai Diterima'][$o['status']] ?? null;
admin_head('Pesanan #' . $o['order_code'], 'pesanan');
?>
<a href="admin_pesanan.php" class="text-sm text-gray-500 hover:text-black no-print"><i class="fa-solid fa-arrow-left mr-1"></i> Semua pesanan</a>
<div class="flex flex-wrap items-center justify-between gap-3 mt-2 mb-6">
    <div><h1 class="text-2xl font-bold">#<?php echo e($o['order_code']); ?></h1><p class="text-sm text-gray-500"><?php echo e(date_long($o['created_at'])); ?>, <?php echo date('H:i', strtotime($o['created_at'])); ?> WIB</p></div>
    <div class="flex items-center gap-2 no-print"><?php echo order_status_badge($o['status']); ?>
        <button onclick="window.print()" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 hover:bg-gray-100"><i class="fa-solid fa-print mr-1"></i> Cetak</button></div>
</div>

<div class="grid lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 space-y-5">
        <section class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm"><thead><tr class="bg-gray-50 text-xs uppercase text-gray-500"><th class="px-5 py-3 text-left">Item</th><th class="px-5 py-3 text-right">Harga</th><th class="px-5 py-3 text-right">Qty</th><th class="px-5 py-3 text-right">Subtotal</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($o['items'] as $it): ?>
                <tr><td class="px-5 py-3"><div class="flex items-center gap-3"><img src="<?php echo e(img($it['image_url'])); ?>" class="w-10 h-12 object-cover rounded bg-gray-100" alt=""><div><b><?php echo e($it['product_name']); ?></b><div class="text-xs text-gray-500"><?php echo e($it['sku']); ?> &bull; <?php echo e(order_item_variant($it)); ?></div></div></div></td>
                <td class="px-5 py-3 text-right"><?php echo e(rp($it['price'])); ?></td><td class="px-5 py-3 text-right"><?php echo (int)$it['qty']; ?></td><td class="px-5 py-3 text-right font-semibold"><?php echo e(rp($it['price'] * $it['qty'])); ?></td></tr>
            <?php endforeach; ?></tbody></table>
            <dl class="bg-gray-50 px-5 py-4 text-sm space-y-1.5">
                <div class="flex justify-between"><dt>Subtotal</dt><dd><?php echo e(rp($o['subtotal'])); ?></dd></div>
                <?php if ((float)$o['discount_amount'] > 0): ?><div class="flex justify-between text-brand"><dt>Voucher <?php echo e($o['voucher_code']); ?></dt><dd>-<?php echo e(rp($o['discount_amount'])); ?></dd></div><?php endif; ?>
                <div class="flex justify-between"><dt>Ongkir (<?php echo e($o['courier']); ?>)</dt><dd><?php echo e(rp($o['shipping_cost'])); ?></dd></div>
                <div class="flex justify-between"><dt>Biaya layanan</dt><dd><?php echo e(rp($o['service_fee'])); ?></dd></div>
                <div class="flex justify-between font-bold text-base border-t border-gray-200 pt-2"><dt>Total</dt><dd><?php echo e(rp($o['total_amount'])); ?></dd></div>
                <p class="text-xs text-gray-500 text-right">Termasuk PPN <?php echo PPN_RATE; ?>%: <?php echo e(rp($o['tax_amount'])); ?></p>
            </dl>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-bold mb-3">Riwayat Status</h2>
            <ul class="space-y-2 text-sm">
                <?php foreach (array_reverse($o['history']) as $h): ?>
                    <li class="flex gap-3"><span class="text-gray-500 w-40 shrink-0 text-xs pt-0.5"><?php echo e(date_short($h['created_at'])); ?> <?php echo date('H:i', strtotime($h['created_at'])); ?></span><span><?php echo order_status_badge($h['status']); ?> <span class="text-gray-600 ml-1"><?php echo e($h['note']); ?></span></span></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>

    <aside class="space-y-5">
        <section class="bg-white rounded-xl border border-gray-200 p-5 no-print">
            <h2 class="font-bold mb-3">Tindakan</h2>
            <div class="space-y-2.5">
                <?php if ($o['payment_status'] === 'pending' && $o['status'] !== 'cancelled'): ?>
                    <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>"><button name="action" value="mark_paid" class="w-full bg-green-600 text-white rounded-lg py-2.5 text-sm font-bold hover:bg-green-700">Tandai Sudah Dibayar</button></form>
                <?php endif; ?>
                <?php if ($nextLabel): ?>
                    <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>"><button name="action" value="advance" class="w-full bg-black text-white rounded-lg py-2.5 text-sm font-bold hover:bg-gray-800"><?php echo e($nextLabel); ?></button></form>
                <?php endif; ?>
                <?php if (!in_array($o['status'], ['cancelled', 'delivered'], true)): ?>
                    <form method="post" onsubmit="return confirm('Batalkan pesanan ini? Stok akan dikembalikan.')"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>"><button name="action" value="cancel" class="w-full border border-red-300 text-red-600 rounded-lg py-2.5 text-sm font-bold hover:bg-red-50">Batalkan Pesanan</button></form>
                <?php endif; ?>
                <form method="post" class="pt-2 border-t border-gray-100"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>">
                    <label class="text-xs font-bold text-gray-500 uppercase">No. Resi / AWB</label>
                    <div class="flex gap-2 mt-1"><input name="tracking_number" value="<?php echo e($o['tracking_number']); ?>" class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm"><button name="action" value="tracking" class="bg-gray-100 hover:bg-gray-200 rounded-lg px-3 text-sm font-semibold">Simpan</button></div></form>
                <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>">
                    <label class="text-xs font-bold text-gray-500 uppercase">Patokan Alamat</label>
                    <div class="flex gap-2 mt-1"><input name="address_note" value="<?php echo e($o['address_note']); ?>" class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm"><button name="action" value="note" class="bg-gray-100 hover:bg-gray-200 rounded-lg px-3 text-sm font-semibold">Simpan</button></div></form>
            </div>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-5 text-sm">
            <h2 class="font-bold mb-3">Penerima</h2>
            <p class="font-semibold"><?php echo e($o['shipping_name']); ?></p>
            <p class="text-gray-600"><?php echo e($o['shipping_phone']); ?> &bull; <?php echo e($o['shipping_email']); ?></p>
            <p class="text-gray-600 mt-2"><?php echo e($o['shipping_address']); ?>, <?php echo e($o['shipping_city']); ?> <?php echo e($o['shipping_postal']); ?></p>
            <?php if ($o['address_note']): ?><p class="text-gray-500 text-xs mt-1">Patokan: <?php echo e($o['address_note']); ?></p><?php endif; ?>
        </section>

        <?php if ($o['payment']): $pay = $o['payment']; ?>
        <section class="bg-white rounded-xl border border-gray-200 p-5 text-sm">
            <h2 class="font-bold mb-3">Pembayaran</h2>
            <p><?php echo e($pay['channel']); ?> <span class="text-gray-400">(<?php echo e($pay['channel_detail']); ?>)</span></p>
            <p class="text-gray-600">ID: <?php echo e($pay['transaction_id']); ?></p>
            <p class="mt-1 font-semibold"><?php echo e(['pending' => 'Menunggu', 'settled' => 'Settled / Lunas', 'failed' => 'Gagal', 'refunded' => 'Refund'][$pay['status']]); ?><?php echo $pay['paid_at'] ? ' &bull; ' . e(date_short($pay['paid_at'])) : ''; ?></p>
        </section>
        <?php endif; ?>
    </aside>
</div>
<?php admin_foot();
