<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';

const SETTING_TEXT = ['site_name', 'site_desc', 'hours_weekday', 'hours_weekend', 'address', 'whatsapp', 'email_info', 'email_custom', 'instagram', 'tiktok', 'maps_url'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $v = [];
    foreach (SETTING_TEXT as $k) $v[$k] = trim($_POST[$k] ?? '');
    $err = null;
    if ($v['site_name'] === '') $err = 'Nama toko wajib diisi.';
    foreach (['email_info', 'email_custom'] as $k) if ($v[$k] !== '' && !filter_var($v[$k], FILTER_VALIDATE_EMAIL)) $err = 'Format email tidak valid.';
    foreach (['instagram', 'tiktok', 'maps_url'] as $k) if ($v[$k] !== '' && !preg_match('#^https?://#i', $v[$k])) $err = 'Link Instagram / TikTok / Maps harus diawali http:// atau https://';
    foreach (['logo', 'favicon'] as $k) {
        $upErr = null;
        $path = save_upload($_FILES[$k] ?? [], $upErr);
        if ($upErr) $err = $upErr; elseif ($path) $v[$k] = $path;
    }
    if ($err) { flash('error', $err); redirect('admin_pengaturan.php'); }
    $st = $conn->prepare("INSERT INTO site_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)");
    foreach ($v as $k => $val) { $st->bind_param('ss', $k, $val); $st->execute(); }
    flash('success', 'Pengaturan disimpan.');
    redirect('admin_pengaturan.php');
}

admin_head('Pengaturan', 'pengaturan');
$inp = ADMIN_INP;
$lab = 'text-xs font-bold text-gray-500 uppercase';
?>
<form method="post" enctype="multipart/form-data" class="space-y-5 max-w-4xl">
    <?php echo csrf_field(); ?>
    <section class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-bold mb-4">Pengaturan Umum</h2>
        <div class="grid sm:grid-cols-2 gap-4 text-sm">
            <div><label class="<?php echo $lab; ?>">Nama toko</label><input name="site_name" value="<?php echo e(setting('site_name')); ?>" class="<?php echo $inp; ?> mt-1" required></div>
            <div><label class="<?php echo $lab; ?>">Deskripsi singkat</label><input name="site_desc" value="<?php echo e(setting('site_desc')); ?>" class="<?php echo $inp; ?> mt-1"></div>
            <div><label class="<?php echo $lab; ?>">Jam buka Senin - Jumat</label><input name="hours_weekday" value="<?php echo e(setting('hours_weekday')); ?>" class="<?php echo $inp; ?> mt-1"></div>
            <div><label class="<?php echo $lab; ?>">Jam buka Sabtu - Minggu</label><input name="hours_weekend" value="<?php echo e(setting('hours_weekend')); ?>" class="<?php echo $inp; ?> mt-1"></div>
        </div>
    </section>

    <section class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-bold mb-4">Logo &amp; Favicon</h2>
        <div class="grid sm:grid-cols-2 gap-6 text-sm">
            <?php foreach (['logo' => 'Logo', 'favicon' => 'Favicon'] as $k => $label): ?>
            <div><label class="<?php echo $lab; ?>"><?php echo $label; ?></label>
                <div class="flex items-center gap-4 mt-2"><img src="<?php echo e(img(setting($k, 'logo.JPG'))); ?>" alt="" class="<?php echo $k === 'logo' ? 'h-16 w-auto max-w-[8rem]' : 'h-10 w-10'; ?> object-contain rounded bg-gray-100 border border-gray-200">
                    <input type="file" name="<?php echo $k; ?>" accept="image/jpeg,image/png,image/webp" class="<?php echo $inp; ?>"></div>
                <p class="text-[11px] text-gray-400 mt-1">JPG / PNG / WEBP, maks. 4 MB. Kosongkan jika tidak diganti.</p></div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-bold mb-4">Informasi Kontak</h2>
        <div class="grid sm:grid-cols-2 gap-4 text-sm">
            <div class="sm:col-span-2"><label class="<?php echo $lab; ?>">Alamat</label><textarea name="address" rows="2" class="<?php echo $inp; ?> mt-1"><?php echo e(setting('address')); ?></textarea></div>
            <div><label class="<?php echo $lab; ?>">WhatsApp</label><input name="whatsapp" value="<?php echo e(setting('whatsapp')); ?>" class="<?php echo $inp; ?> mt-1"></div>
            <div><label class="<?php echo $lab; ?>">Email utama</label><input type="email" name="email_info" value="<?php echo e(setting('email_info')); ?>" class="<?php echo $inp; ?> mt-1"></div>
            <div><label class="<?php echo $lab; ?>">Email pesanan khusus</label><input type="email" name="email_custom" value="<?php echo e(setting('email_custom')); ?>" class="<?php echo $inp; ?> mt-1"></div>
            <div><label class="<?php echo $lab; ?>">Link Google Maps (opsional)</label><input name="maps_url" value="<?php echo e(setting('maps_url')); ?>" class="<?php echo $inp; ?> mt-1" placeholder="https://maps.app.goo.gl/..."></div>
            <div><label class="<?php echo $lab; ?>">Instagram (link)</label><input name="instagram" value="<?php echo e(setting('instagram')); ?>" class="<?php echo $inp; ?> mt-1" placeholder="https://instagram.com/..."></div>
            <div><label class="<?php echo $lab; ?>">TikTok (link)</label><input name="tiktok" value="<?php echo e(setting('tiktok')); ?>" class="<?php echo $inp; ?> mt-1" placeholder="https://tiktok.com/@..."></div>
        </div>
    </section>

    <div class="flex items-center gap-4"><button class="bg-black text-white rounded-lg px-8 py-2.5 font-bold hover:bg-gray-800">Simpan Pengaturan</button>
        <span class="text-xs text-gray-400">Alamat &amp; link Maps dipakai di halaman Contact Us website.</span></div>
</form>
<?php admin_foot();
