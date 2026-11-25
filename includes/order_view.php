<?php
// Komponen tampilan pesanan yang dipakai konfirmasi.php, lacak.php, pesanan.php

function order_load(mysqli $conn, string $code): ?array
{
    $st = $conn->prepare("SELECT * FROM orders WHERE order_code = ?");
    $st->bind_param('s', $code);
    $st->execute();
    $o = $st->get_result()->fetch_assoc();
    if (!$o) return null;
    $oid = (int)$o['id'];
    $o['items'] = $conn->query("SELECT * FROM order_items WHERE order_id = $oid ORDER BY id")->fetch_all(MYSQLI_ASSOC);
    $o['history'] = $conn->query("SELECT * FROM order_status_history WHERE order_id = $oid ORDER BY id")->fetch_all(MYSQLI_ASSOC);
    $o['payment'] = $conn->query("SELECT * FROM payments WHERE order_id = $oid ORDER BY id DESC LIMIT 1")->fetch_assoc() ?: null;
    return $o;
}

/** Timeline 4 langkah: Order Masuk -> Packing -> Pick-up Kurir -> Diterima */
function order_timeline_html(array $o): string
{
    $steps = [
        ['placed',    'Order Masuk',  'fa-check'],
        ['packing',   'Packing',      'fa-box-archive'],
        ['shipped',   'Pick-up Kurir','fa-dolly'],
        ['delivered', 'Diterima',     'fa-house'],
    ];
    $rank = ['pending_payment' => 0, 'placed' => 1, 'packing' => 2, 'shipped' => 3, 'delivered' => 4, 'cancelled' => 0];
    $cur = $rank[$o['status']] ?? 0;               // jumlah langkah yang sudah selesai (+1 = sedang berjalan)
    $times = [];
    foreach ($o['history'] as $h) if (!isset($times[$h['status']])) $times[$h['status']] = $h['created_at'];

    $html = '<ol class="grid grid-cols-4 relative">';
    foreach ($steps as $i => [$key, $label, $icon]) {
        // aturan sederhana: langkah i selesai jika i < cur-1; sedang berjalan jika i == cur-1 (kecuali delivered)
        $isDone = $i < $cur - 1 || $o['status'] === 'delivered';
        $isNow  = !$isDone && $i === max(0, $cur - 1) && $o['status'] !== 'cancelled' && $o['status'] !== 'pending_payment';
        if ($o['status'] === 'pending_payment' && $i === 0) $isNow = true;
        $line = $i < 3 ? '<span class="absolute top-[18px] left-1/2 w-full h-[2px] ' . (($isDone) ? 'bg-black' : 'bg-gray-300') . '"></span>' : '';

        if ($isDone) {
            $t = isset($times[$key]) ? date('H:i', strtotime($times[$key])) . ' WIB' : 'Selesai';
            $sub = $t; $circle = 'bg-black text-white'; $ico = 'fa-check'; $lab = 'text-black';
        } elseif ($isNow) {
            $sub = $o['status'] === 'pending_payment' ? 'Menunggu bayar' : 'Sedang Berjalan';
            $circle = 'bg-black text-white'; $ico = $icon; $lab = 'text-black';
        } else {
            $sub = $key === 'shipped' ? 'Estimasi 17:00' : ($key === 'delivered' ? ($o['courier'] === 'express' ? '1 Hari' : '2-3 Hari') : '-');
            $circle = 'bg-gray-200 text-gray-400'; $ico = $icon; $lab = 'text-gray-400';
        }
        $html .= '<li class="relative flex flex-col items-center text-center">' . $line .
            '<span class="relative z-10 w-9 h-9 rounded-full flex items-center justify-center text-[13px] ' . $circle . '"><i class="fa-solid ' . $ico . '"></i></span>' .
            '<span class="mt-2 text-[11px] font-bold ' . $lab . '">' . e($label) . '</span>' .
            '<span class="text-[10px] ' . ($isDone || $isNow ? 'text-gray-500' : 'text-gray-400') . '">' . e($sub) . '</span></li>';
    }
    return $html . '</ol>';
}

/** Teks varian: "Hitam • Size XL" */
function order_item_variant(array $it): string
{
    $opts = json_decode($it['options'] ?? '[]', true) ?: [];
    $map = [];
    foreach ($opts as $o) $map[$o['label']] = $o['value'];
    $parts = [];
    if (isset($map['WARNA'])) $parts[] = 'Warna: ' . $map['WARNA'];
    if (isset($map['SIZE'])) $parts[] = 'Size: ' . $map['SIZE'];
    foreach ($map as $k => $v) if (!in_array($k, ['WARNA', 'SIZE'], true)) $parts[] = ucfirst(strtolower($k)) . ': ' . $v;
    return implode(' • ', $parts);
}
