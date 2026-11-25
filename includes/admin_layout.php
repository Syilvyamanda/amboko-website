<?php
// =====================================================================
//  AMBOKO ERP - Layout & helper khusus admin
// =====================================================================
require_admin();

if (!function_exists('render_flash')) {
    include_once __DIR__ . '/layout.php';
}

/* ---------------------------------------------------------------------
   Menu admin (kunci = nama hak akses)
--------------------------------------------------------------------- */
const ADMIN_MENU = [
    'Katalog' => [
        'produk'     => ['admin_produk.php',     'fa-shirt',          'Produk'],
        'kategori'   => ['admin_kategori.php',   'fa-tags',           'Kategori'],
        'stok'       => ['admin_stok.php',       'fa-boxes-stacked',  'Stok'],
    ],
    'Penjualan' => [
        'pesanan'    => ['admin_pesanan.php',    'fa-receipt',        'Pesanan'],
        'pengiriman' => ['admin_pengiriman.php', 'fa-truck',          'Pengiriman'],
        'pembayaran' => ['admin_pembayaran.php', 'fa-credit-card',    'Pembayaran'],
        'retur'      => ['admin_retur.php',      'fa-rotate-left',    'Retur & Tukar'],
        'voucher'    => ['admin_voucher.php',    'fa-ticket',         'Voucher'],
    ],
    'Pelanggan' => [
        'pelanggan'  => ['admin_pelanggan.php',  'fa-users',          'Pelanggan'],
        'crm'        => ['admin_crm.php',        'fa-address-book',   'CRM'],
    ],
    'Laporan' => [
        'keuangan'   => ['admin_keuangan.php',   'fa-wallet',         'Keuangan'],
        'analitik'   => ['admin_analitik.php',   'fa-chart-line',     'Analitik'],
    ],
    'Sistem' => [
        'pengguna'   => ['admin_pengguna.php',   'fa-user-shield',    'Pengguna'],
        'pengaturan' => ['admin_pengaturan.php', 'fa-gear',           'Pengaturan'],
    ],
];

/** Nama file -> kunci hak akses. Dashboard selalu boleh dibuka. */
const ADMIN_PAGE_PERM = [
    'admin_produk.php' => 'produk', 'tambah_produk.php' => 'produk', 'edit_produk.php' => 'produk', 'hapus_produk.php' => 'produk',
    'admin_kategori.php' => 'kategori', 'admin_stok.php' => 'stok',
    'admin_pesanan.php' => 'pesanan', 'admin_pesanan_detail.php' => 'pesanan',
    'admin_pengiriman.php' => 'pengiriman', 'admin_pembayaran.php' => 'pembayaran',
    'admin_retur.php' => 'retur', 'admin_retur_detail.php' => 'retur', 'admin_voucher.php' => 'voucher',
    'admin_pelanggan.php' => 'pelanggan', 'admin_pelanggan_form.php' => 'pelanggan', 'admin_pelanggan_detail.php' => 'pelanggan',
    'admin_crm.php' => 'crm', 'admin_keuangan.php' => 'keuangan', 'admin_analitik.php' => 'analitik',
    'admin_pengguna.php' => 'pengguna', 'admin_pengaturan.php' => 'pengaturan',
];

const ADMIN_INP = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-black bg-white';

/* ---------------------------------------------------------------------
   Pastikan database admin sudah di-import (database_admin.sql)
--------------------------------------------------------------------- */
$__ok = $conn->query("SHOW TABLES LIKE 'shipping_methods'");
if (!$__ok || $__ok->num_rows === 0) {
    http_response_code(503);
    echo '<!DOCTYPE html><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><script src="https://cdn.tailwindcss.com"></script>'
       . '<body class="bg-gray-50 min-h-screen flex items-center justify-center p-6"><div class="bg-white border border-gray-200 rounded-2xl p-8 max-w-md text-center">'
       . '<h1 class="text-xl font-bold mb-2">Database admin belum lengkap</h1>'
       . '<p class="text-sm text-gray-600">Import file <b>database_admin.sql</b> sekali saja lewat phpMyAdmin (tab Import) pada database <b>amboko_erp</b>, lalu muat ulang halaman ini. Data lama tidak dihapus.</p></div></body>';
    exit;
}

/* ---------------------------------------------------------------------
   Hak akses admin: kolom users.permissions (NULL = akses penuh)
--------------------------------------------------------------------- */
function admin_perms(): ?array
{
    global $conn;
    static $perms = false;
    if ($perms !== false) return $perms;
    $uid = (int)user_id();
    $raw = $conn->query("SELECT permissions FROM users WHERE id = $uid")->fetch_row()[0] ?? null;
    if ($raw === null || trim((string)$raw) === '') return $perms = null;
    return $perms = array_filter(array_map('trim', explode(',', $raw)));
}

function admin_can(string $key): bool
{
    if ($key === 'dashboard') return true;
    $p = admin_perms();
    return $p === null || in_array($key, $p, true);
}

$__need = ADMIN_PAGE_PERM[basename($_SERVER['SCRIPT_NAME'] ?? '')] ?? null;
if ($__need && !admin_can($__need)) {
    http_response_code(403);
    echo '<!DOCTYPE html><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><script src="https://cdn.tailwindcss.com"></script>'
       . '<body class="bg-gray-50 min-h-screen flex items-center justify-center p-6"><div class="bg-white border border-gray-200 rounded-2xl p-8 max-w-md text-center">'
       . '<h1 class="text-xl font-bold mb-2">Akses ditolak</h1><p class="text-sm text-gray-600 mb-4">Akun Anda tidak punya hak akses ke halaman ini.</p>'
       . '<a href="dashboard.php" class="inline-block bg-black text-white rounded-lg px-4 py-2 text-sm font-bold">Ke Dashboard</a></div></body>';
    exit;
}

/** Pengaturan website (tabel site_settings), dibaca sekali per request. */
function setting(string $key, string $default = ''): string
{
    global $conn;
    static $all = null;
    if ($all === null) {
        $all = [];
        $r = $conn->query("SELECT k, v FROM site_settings");
        while ($r && ($row = $r->fetch_assoc())) $all[$row['k']] = (string)$row['v'];
    }
    return ($all[$key] ?? '') !== '' ? $all[$key] : $default;
}

/** Rentang tanggal untuk filter laporan: [dari, sampai, label] */
function admin_period(string $p): array
{
    $today = date('Y-m-d');
    switch ($p) {
        case 'bulan':  return [date('Y-m-01'), $today, 'Bulan ini'];
        case '3bulan': return [date('Y-m-01', strtotime('-2 month')), $today, '3 bulan terakhir'];
        case 'semua':  return ['2000-01-01', $today, 'Semua waktu'];
        default:       return [date('Y-01-01'), $today, 'Tahun ini'];
    }
}

/** Pilihan periode (tautan pill) */
function admin_period_pills(string $cur, array $keep = []): string
{
    $opts = ['bulan' => 'Bulan ini', '3bulan' => '3 bulan', 'tahun' => 'Tahun ini', 'semua' => 'Semua'];
    $out = '<div class="flex flex-wrap gap-2 no-print">';
    foreach ($opts as $k => $label) {
        $qs = http_build_query($keep + ['p' => $k]);
        $out .= '<a href="?' . $qs . '" class="px-3.5 py-1.5 rounded-full text-sm font-medium ' . ($cur === $k ? 'bg-black text-white' : 'bg-white border border-gray-200 hover:bg-gray-100') . '">' . e($label) . '</a>';
    }
    return $out . '</div>';
}

/** Tab sederhana (tautan) */
function admin_tabs(array $tabs, string $cur, array $keep = []): string
{
    $out = '<div class="flex flex-wrap gap-2 mb-5 border-b border-gray-200 no-print">';
    foreach ($tabs as $k => $label) {
        $qs = http_build_query($keep + ['tab' => $k]);
        $out .= '<a href="?' . $qs . '" class="px-4 py-2.5 text-sm font-semibold -mb-px border-b-2 ' . ($cur === $k ? 'border-black text-black' : 'border-transparent text-gray-500 hover:text-black') . '">' . e($label) . '</a>';
    }
    return $out . '</div>';
}

/** Diagram batang CSS. $data = [label => nilai]; $fmt = fn(nilai): string */
function admin_bars(array $data, callable $fmt, string $height = 'h-44'): string
{
    $max = max(1, $data ? max($data) : 1);
    $out = '<div class="flex items-end gap-2 ' . $height . '">';
    foreach ($data as $label => $v) {
        $h = $v > 0 ? max(3, (int)round($v / $max * 100)) : 1;
        $out .= '<div class="flex-1 min-w-0 flex flex-col items-center justify-end h-full gap-1.5" title="' . e($label . ': ' . $fmt($v)) . '">'
              . '<span class="text-[10px] text-gray-500 truncate max-w-full">' . ($v > 0 ? e($fmt($v)) : '') . '</span>'
              . '<div class="w-full rounded-t-md bg-black" style="height:' . $h . '%"></div>'
              . '<span class="text-[10px] text-gray-500">' . e($label) . '</span></div>';
    }
    return $out . '</div>';
}

/** Lencana kecil berwarna */
function admin_pill(string $text, string $cls = 'bg-gray-100 text-gray-700'): string
{
    return '<span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold ' . $cls . '">' . e($text) . '</span>';
}

function channel_badge(?string $ch): string
{
    return $ch === 'offline' ? admin_pill('Toko', 'bg-amber-50 text-amber-700') : admin_pill('Website', 'bg-blue-50 text-blue-700');
}

function admin_head(string $title, string $active): void
{
    $favicon = setting('favicon', 'logo.JPG');
    $siteName = setting('site_name', 'AMBOKO');
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title); ?> - AMBOKO ERP</title>
    <link rel="icon" href="<?php echo e(img($favicon)); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['"DM Sans"','system-ui','sans-serif'],head:['"Bricolage Grotesque"','"DM Sans"','sans-serif']},colors:{brand:'#0c82c5'}}}};</script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css/amboko.css" rel="stylesheet">
</head>
<body class="bg-gray-50 font-sans text-gray-900 md:flex min-h-screen">
    <aside class="md:w-64 bg-gray-900 text-white md:flex md:flex-col md:h-screen md:sticky md:top-0 shrink-0 no-print">
        <div class="h-16 flex items-center px-6 border-b border-gray-800">
            <span class="inline-block w-3 h-3 bg-brand mr-2"></span>
            <span class="font-head font-extrabold text-2xl tracking-tight"><?php echo e(strtoupper($siteName)); ?></span><span class="text-xs text-gray-400 ml-2">ERP</span>
        </div>
        <nav class="flex md:block overflow-x-auto md:overflow-y-auto md:overflow-x-hidden py-2 md:py-3 md:flex-1">
            <?php $link = function (string $href, string $icon, string $label, bool $on) { ?>
                <a href="<?php echo $href; ?>" class="flex items-center gap-3 px-6 py-2.5 text-sm whitespace-nowrap <?php echo $on ? 'bg-brand text-white font-semibold' : 'text-gray-400 hover:text-white hover:bg-gray-800'; ?>"><i class="fa-solid <?php echo $icon; ?> w-5"></i> <?php echo $label; ?></a>
            <?php };
            $link('dashboard.php', 'fa-table-columns', 'Dashboard', $active === 'dashboard');
            foreach (ADMIN_MENU as $group => $items):
                $visible = array_filter($items, fn($k) => admin_can($k), ARRAY_FILTER_USE_KEY);
                if (!$visible) continue; ?>
                <p class="hidden md:block px-6 pt-4 pb-1 text-[10px] font-bold tracking-[0.14em] uppercase text-gray-600"><?php echo e($group); ?></p>
                <?php foreach ($visible as $key => [$href, $icon, $label]) $link($href, $icon, $label, $active === $key); ?>
            <?php endforeach; ?>
            <a href="index.php" target="_blank" class="flex items-center gap-3 px-6 py-2.5 text-sm text-gray-400 hover:text-white hover:bg-gray-800 whitespace-nowrap md:mt-4"><i class="fa-solid fa-globe w-5"></i> Lihat Website</a>
            <a href="logout.php" class="flex items-center gap-3 px-6 py-2.5 text-sm text-red-400 hover:text-white hover:bg-red-600 whitespace-nowrap"><i class="fa-solid fa-right-from-bracket w-5"></i> Logout</a>
        </nav>
    </aside>

    <main class="flex-1 min-w-0">
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 no-print">
            <div class="font-bold text-gray-800"><?php echo e($title); ?></div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-600 font-medium hidden sm:inline">Halo, <?php echo e($_SESSION['name'] ?? 'Admin'); ?></span>
                <div class="h-8 w-8 bg-black rounded-full flex items-center justify-center text-white text-xs"><i class="fa-solid fa-user"></i></div>
            </div>
        </header>
        <?php render_flash(); ?>
        <div class="p-6">
<?php
}

function admin_foot(): void
{
    echo '</div></main><script src="js/amboko.js"></script></body></html>';
}

/* ------------------------- Upload gambar ------------------------- */
/** @return string|null path relatif ('uploads/xxx.jpg') atau null jika gagal */
function save_upload(array $file, ?string &$error = null): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) { $error = 'Upload gagal (kode ' . $file['error'] . ').'; return null; }
    if ($file['size'] > 4 * 1024 * 1024) { $error = 'Ukuran gambar maksimal 4 MB.'; return null; }
    $info = @getimagesize($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
    if (!$ext) { $error = 'Format gambar harus JPG, PNG, atau WEBP.'; return null; }
    $dir = __DIR__ . '/../uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = 'p_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) { $error = 'Tidak dapat menyimpan file upload (cek izin folder uploads/).'; return null; }
    return 'uploads/' . $name;
}

/** Susun ulang $_FILES['gallery'] (multiple) menjadi array file */
function files_array(string $field): array
{
    $out = [];
    if (empty($_FILES[$field]['name'])) return $out;
    foreach ((array)$_FILES[$field]['name'] as $i => $n) {
        $out[] = ['name' => $n, 'type' => $_FILES[$field]['type'][$i], 'tmp_name' => $_FILES[$field]['tmp_name'][$i], 'error' => $_FILES[$field]['error'][$i], 'size' => $_FILES[$field]['size'][$i]];
    }
    return $out;
}

/* ------------------------- Varian (teks <-> DB) ------------------------- */
/**
 * Format teks, satu grup per baris:
 *   Select Size: All Size, S, M, L, XL
 *   Color [warna]: Hitam=#0a0a0a, Abu-abu=#6b6b6b
 */
function options_to_text(array $groups): string
{
    $lines = [];
    foreach ($groups as $g) {
        $vals = [];
        foreach ($g['values'] as $v) $vals[] = $g['type'] === 'color' ? $v['label'] . '=' . $v['hex'] : $v['label'];
        $lines[] = $g['title'] . ($g['type'] === 'color' ? ' [warna]' : '') . ': ' . implode(', ', $vals);
    }
    return implode("\n", $lines);
}

function save_options(mysqli $conn, int $pid, string $text): void
{
    // simpan metadata lama agar foto warna/label tidak hilang saat varian disusun ulang
    $oldG = []; $oldV = [];
    foreach (product_options($conn, $pid) as $g) {
        $oldG[strtolower($g['title'])] = $g;
        foreach ($g['values'] as $v) $oldV[strtolower($g['title'] . '|' . $v['label'])] = $v;
    }
    $conn->query("DELETE FROM product_option_groups WHERE product_id = " . (int)$pid);

    $ig = $conn->prepare("INSERT INTO product_option_groups (product_id, title, cart_label, type, show_value, guide_label, sort_order) VALUES (?,?,?,?,?,?,?)");
    $iv = $conn->prepare("INSERT INTO product_option_values (group_id, label, display_label, hex, hex2, image_url, sort_order, is_default) VALUES (?,?,?,?,?,?,?,?)");
    $order = 0;
    foreach (preg_split('/\r?\n/', trim($text)) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, ':') === false) continue;
        [$title, $rest] = array_map('trim', explode(':', $line, 2));
        $type = 'pill';
        if (preg_match('/\s*\[warna\]\s*$/i', $title)) { $type = 'color'; $title = trim(preg_replace('/\s*\[warna\]\s*$/i', '', $title)); }
        if ($title === '') continue;
        $old = $oldG[strtolower($title)] ?? null;
        $cartLabel = $type === 'color' ? 'WARNA' : (preg_match('/size|ukuran/i', $title) ? 'SIZE' : strtoupper(strtok($title, ' /')));
        $showValue = $old ? (int)$old['show_value'] : ($type === 'color' ? 1 : 0);
        $guide = $old['guide_label'] ?? null;
        $order++;
        $ig->bind_param('isssisi', $pid, $title, $cartLabel, $type, $showValue, $guide, $order);
        $ig->execute();
        $gid = $conn->insert_id;

        $i = 0;
        foreach (array_filter(array_map('trim', explode(',', $rest))) as $val) {
            $hex = null;
            if ($type === 'color' && strpos($val, '=') !== false) [$val, $hex] = array_map('trim', explode('=', $val, 2));
            if ($type === 'color' && !$hex) $hex = '#999999';
            $m = $oldV[strtolower($title . '|' . $val)] ?? null;
            $disp = $m['display_label'] ?? null; $hex2 = $m['hex2'] ?? null; $imgv = $m['image_url'] ?? null;
            $i++; $def = $i === 1 ? 1 : 0;
            $iv->bind_param('isssssii', $gid, $val, $disp, $hex, $hex2, $imgv, $i, $def);
            $iv->execute();
        }
    }
}
