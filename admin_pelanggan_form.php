<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$u = ['name' => '', 'email' => '', 'phone' => '', 'address' => '', 'city' => '', 'postal_code' => '', 'channel' => 'offline'];
if ($id) {
    $found = $conn->query("SELECT * FROM users WHERE id = $id AND role = 'customer'")->fetch_assoc();
    if (!$found) { flash('error', 'Pelanggan tidak ditemukan.'); redirect('admin_pelanggan.php'); }
    $u = $found;
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $in = [
        'name' => trim($_POST['name'] ?? ''), 'email' => trim($_POST['email'] ?? ''), 'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''), 'city' => trim($_POST['city'] ?? ''), 'postal_code' => trim($_POST['postal_code'] ?? ''),
        'channel' => ($_POST['channel'] ?? '') === 'online' ? 'online' : 'offline',
    ];
    $pass = (string)($_POST['password'] ?? '');
    if ($in['name'] === '') $errors[] = 'Nama wajib diisi.';
    if ($in['email'] !== '' && !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    if ($in['channel'] === 'online' && $in['email'] === '') $errors[] = 'Pelanggan website wajib punya email.';
    if ($pass !== '' && strlen($pass) < 6) $errors[] = 'Password minimal 6 karakter.';
    if ($in['email'] !== '') {
        $c = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
        $c->bind_param('si', $in['email'], $id); $c->execute();
        if ($c->get_result()->num_rows) $errors[] = 'Email sudah dipakai akun lain.';
    }
    if (!$errors) {
        $email = $in['email'] !== '' ? $in['email'] : null;
        $phone = $in['phone'] ?: null; $addr = $in['address'] ?: null; $city = $in['city'] ?: null; $post = $in['postal_code'] ?: null;
        if ($id) {
            $st = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, address=?, city=?, postal_code=?, channel=? WHERE id=?");
            $st->bind_param('sssssssi', $in['name'], $email, $phone, $addr, $city, $post, $in['channel'], $id);
            $st->execute();
            if ($pass !== '') {
                $h = password_hash($pass, PASSWORD_DEFAULT);
                $pw = $conn->prepare("UPDATE users SET password = ? WHERE id = ?"); $pw->bind_param('si', $h, $id); $pw->execute();
            }
            flash('success', 'Data pelanggan diperbarui.');
        } else {
            $h = $pass !== '' ? password_hash($pass, PASSWORD_DEFAULT) : '!';   // '!' = belum punya akses login
            $st = $conn->prepare("INSERT INTO users (name, email, password, role, phone, address, city, postal_code, channel) VALUES (?,?,?,'customer',?,?,?,?,?)");
            $st->bind_param('ssssssss', $in['name'], $email, $h, $phone, $addr, $city, $post, $in['channel']);
            $st->execute();
            $id = (int)$conn->insert_id;
            flash('success', 'Pelanggan ditambahkan.');
        }
        redirect('admin_pelanggan_detail.php?id=' . $id);
    }
    $u = array_merge($u, $in);
}

admin_head($id ? 'Edit Pelanggan' : 'Tambah Pelanggan', 'pelanggan');
$inp = ADMIN_INP;
?>
<a href="admin_pelanggan.php" class="text-sm text-gray-500 hover:text-black"><i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke pelanggan</a>
<?php if ($errors): ?><div class="mt-3 bg-red-50 text-red-600 text-sm rounded-lg p-3"><?php foreach ($errors as $er) echo '<div>' . e($er) . '</div>'; ?></div><?php endif; ?>
<form method="post" class="bg-white rounded-xl border border-gray-200 p-6 mt-3 max-w-3xl grid sm:grid-cols-2 gap-4 text-sm">
    <?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>">
    <div><label class="text-xs font-bold text-gray-500 uppercase">Nama</label><input name="name" value="<?php echo e($u['name']); ?>" class="<?php echo $inp; ?> mt-1" required></div>
    <div><label class="text-xs font-bold text-gray-500 uppercase">Asal pelanggan</label>
        <select name="channel" class="<?php echo $inp; ?> mt-1"><option value="offline" <?php echo $u['channel'] === 'offline' ? 'selected' : ''; ?>>Toko offline</option><option value="online" <?php echo $u['channel'] === 'online' ? 'selected' : ''; ?>>Website</option></select></div>
    <div><label class="text-xs font-bold text-gray-500 uppercase">Email <span class="font-normal normal-case text-gray-400">(opsional untuk pelanggan toko)</span></label><input type="email" name="email" value="<?php echo e($u['email']); ?>" class="<?php echo $inp; ?> mt-1"></div>
    <div><label class="text-xs font-bold text-gray-500 uppercase">Telepon / WhatsApp</label><input name="phone" value="<?php echo e($u['phone']); ?>" class="<?php echo $inp; ?> mt-1"></div>
    <div class="sm:col-span-2"><label class="text-xs font-bold text-gray-500 uppercase">Alamat</label><textarea name="address" rows="2" class="<?php echo $inp; ?> mt-1"><?php echo e($u['address']); ?></textarea></div>
    <div><label class="text-xs font-bold text-gray-500 uppercase">Kota</label><input name="city" value="<?php echo e($u['city']); ?>" class="<?php echo $inp; ?> mt-1"></div>
    <div><label class="text-xs font-bold text-gray-500 uppercase">Kode pos</label><input name="postal_code" value="<?php echo e($u['postal_code']); ?>" class="<?php echo $inp; ?> mt-1"></div>
    <div class="sm:col-span-2"><label class="text-xs font-bold text-gray-500 uppercase">Password <span class="font-normal normal-case text-gray-400">(<?php echo $id ? 'kosongkan jika tidak diganti' : 'kosongkan jika pelanggan tidak perlu login'; ?>)</span></label><input type="password" name="password" class="<?php echo $inp; ?> mt-1" autocomplete="new-password"></div>
    <div class="sm:col-span-2 flex gap-2"><button class="bg-black text-white rounded-lg px-6 py-2.5 font-bold hover:bg-gray-800">Simpan</button><a href="admin_pelanggan.php" class="px-5 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 font-medium">Batal</a></div>
</form>
<?php admin_foot();
