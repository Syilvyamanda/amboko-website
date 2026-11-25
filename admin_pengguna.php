<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';

const SUPER_EMAIL = 'admin@amboko.com';
$menuLabels = ['dashboard' => null];
foreach (ADMIN_MENU as $items) foreach ($items as $key => [, , $label]) $menuLabels[$key] = $label;
unset($menuLabels['dashboard']);
$me = (int)user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass = (string)($_POST['password'] ?? '');
        $full = !empty($_POST['full_access']);
        $sel = array_values(array_intersect(array_keys($menuLabels), (array)($_POST['perm'] ?? [])));
        $target = $id ? $conn->query("SELECT * FROM users WHERE id = $id AND role = 'admin'")->fetch_assoc() : null;
        $locked = $target && ($id === $me || $target['email'] === SUPER_EMAIL);   // hak akses akun sendiri / super admin tidak diubah

        $err = null;
        if ($id && !$target) $err = 'Pengguna tidak ditemukan.';
        elseif ($name === '') $err = 'Nama wajib diisi.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Email tidak valid.';
        elseif (!$id && strlen($pass) < 6) $err = 'Password minimal 6 karakter.';
        elseif ($id && $pass !== '' && strlen($pass) < 6) $err = 'Password minimal 6 karakter.';
        elseif (!$locked && !$full && !$sel) $err = 'Pilih akses penuh atau minimal satu menu.';
        if (!$err) {
            $c = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
            $c->bind_param('si', $email, $id); $c->execute();
            if ($c->get_result()->num_rows) $err = 'Email sudah dipakai akun lain.';
        }
        if ($err) { flash('error', $err); redirect('admin_pengguna.php' . ($id ? '?edit=' . $id : '')); }

        $perms = $full ? null : implode(',', $sel);
        if ($id) {
            if ($locked) {
                $st = $conn->prepare("UPDATE users SET name=?, email=? WHERE id=?");
                $st->bind_param('ssi', $name, $email, $id);
            } else {
                $st = $conn->prepare("UPDATE users SET name=?, email=?, permissions=? WHERE id=?");
                $st->bind_param('sssi', $name, $email, $perms, $id);
            }
            $st->execute();
            if ($pass !== '') { $h = password_hash($pass, PASSWORD_DEFAULT); $pw = $conn->prepare("UPDATE users SET password=? WHERE id=?"); $pw->bind_param('si', $h, $id); $pw->execute(); }
            if ($id === $me) $_SESSION['name'] = $name;
            flash('success', 'Pengguna diperbarui.');
        } else {
            $h = password_hash($pass, PASSWORD_DEFAULT);
            $st = $conn->prepare("INSERT INTO users (name, email, password, role, permissions) VALUES (?,?,?,'admin',?)");
            $st->bind_param('ssss', $name, $email, $h, $perms);
            $st->execute();
            flash('success', 'Pengguna ditambahkan.');
        }
    } elseif ($action === 'delete' && $id) {
        $t = $conn->query("SELECT email, name FROM users WHERE id = $id AND role = 'admin'")->fetch_assoc();
        $admins = (int)$conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetch_row()[0];
        if (!$t) flash('error', 'Pengguna tidak ditemukan.');
        elseif ($id === $me) flash('error', 'Anda tidak bisa menghapus akun yang sedang dipakai.');
        elseif ($t['email'] === SUPER_EMAIL) flash('error', 'Akun Super Admin tidak bisa dihapus.');
        elseif ($admins <= 1) flash('error', 'Minimal harus ada satu administrator.');
        else { $conn->query("DELETE FROM users WHERE id = $id"); flash('success', 'Pengguna ' . $t['name'] . ' dihapus.'); }
    }
    redirect('admin_pengguna.php');
}

$edit = null;
if (!empty($_GET['edit'])) { $eid = (int)$_GET['edit']; $edit = $conn->query("SELECT * FROM users WHERE id = $eid AND role = 'admin'")->fetch_assoc() ?: null; }
$editLocked = $edit && ((int)$edit['id'] === $me || $edit['email'] === SUPER_EMAIL);
$editPerms = $edit && $edit['permissions'] !== null && $edit['permissions'] !== '' ? explode(',', $edit['permissions']) : [];
$editFull = !$edit || $edit['permissions'] === null || $edit['permissions'] === '';
$admins = $conn->query("SELECT * FROM users WHERE role = 'admin' ORDER BY id")->fetch_all(MYSQLI_ASSOC);

admin_head('Pengguna', 'pengguna');
$inp = ADMIN_INP;
?>
<div class="grid lg:grid-cols-3 gap-5">
    <section class="bg-white rounded-xl border border-gray-200 p-5 h-fit">
        <h2 class="font-bold mb-4"><?php echo $edit ? 'Edit Pengguna' : 'Tambah Pengguna'; ?></h2>
        <form method="post" class="space-y-3 text-sm" autocomplete="off"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
            <div><label class="text-xs font-bold text-gray-500 uppercase">Nama</label><input name="name" value="<?php echo e($edit['name'] ?? ''); ?>" class="<?php echo $inp; ?> mt-1" required></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Email</label><input type="email" name="email" value="<?php echo e($edit['email'] ?? ''); ?>" class="<?php echo $inp; ?> mt-1" required></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Password <?php echo $edit ? '<span class="font-normal normal-case text-gray-400">(kosongkan jika tidak diganti)</span>' : ''; ?></label><input type="password" name="password" class="<?php echo $inp; ?> mt-1" autocomplete="new-password" <?php echo $edit ? '' : 'required'; ?>></div>
            <div class="pt-1">
                <p class="text-xs font-bold text-gray-500 uppercase mb-2">Hak Akses</p>
                <?php if ($editLocked): ?>
                    <p class="text-xs text-gray-500 bg-gray-50 rounded-lg p-3">Hak akses akun ini (Super Admin / akun yang sedang Anda pakai) tidak bisa diubah dari sini.</p>
                <?php else: ?>
                    <label class="flex items-center gap-2 font-semibold mb-2"><input type="checkbox" name="full_access" value="1" id="full" <?php echo $editFull ? 'checked' : ''; ?> onchange="document.getElementById('perm-box').classList.toggle('opacity-40', this.checked)"> Akses penuh</label>
                    <div id="perm-box" class="grid grid-cols-2 gap-1.5 <?php echo $editFull ? 'opacity-40' : ''; ?>">
                        <?php foreach ($menuLabels as $k => $label): ?><label class="flex items-center gap-2"><input type="checkbox" name="perm[]" value="<?php echo e($k); ?>" <?php echo in_array($k, $editPerms, true) ? 'checked' : ''; ?> onchange="document.getElementById('full').checked=false;document.getElementById('perm-box').classList.remove('opacity-40')"> <?php echo e($label); ?></label><?php endforeach; ?>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-2">Dashboard selalu bisa dibuka.</p>
                <?php endif; ?>
            </div>
            <div class="flex gap-2"><button class="flex-1 bg-black text-white rounded-lg py-2.5 font-bold hover:bg-gray-800">Simpan</button><?php if ($edit): ?><a href="admin_pengguna.php" class="px-4 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 font-medium">Batal</a><?php endif; ?></div>
        </form>
    </section>

    <section class="lg:col-span-2 bg-white rounded-xl border border-gray-200 overflow-x-auto h-fit">
        <table class="w-full text-left text-sm admin-table">
            <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase"><th class="px-5 py-3">Pengguna</th><th class="px-5 py-3">Hak Akses</th><th class="px-5 py-3">Dibuat</th><th class="px-5 py-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($admins as $a):
                $full = $a['permissions'] === null || $a['permissions'] === '';
                $list = $full ? [] : array_filter(array_map(fn($k) => $menuLabels[$k] ?? null, explode(',', $a['permissions'])));
                $isSuper = $a['email'] === SUPER_EMAIL; ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3"><b><?php echo e($a['name']); ?></b> <?php echo $isSuper ? admin_pill('Super Admin', 'bg-black text-white') : ''; ?> <?php echo (int)$a['id'] === $me ? admin_pill('Anda', 'bg-blue-50 text-blue-700') : ''; ?><div class="text-xs text-gray-400"><?php echo e($a['email']); ?></div></td>
                    <td class="px-5 py-3 text-gray-600 max-w-[16rem]"><?php echo $full ? admin_pill('Akses penuh', 'bg-green-50 text-green-700') : '<span class="text-xs">' . e(implode(', ', $list)) . '</span>'; ?></td>
                    <td class="px-5 py-3 text-gray-500"><?php echo e(date_short($a['created_at'])); ?></td>
                    <td class="px-5 py-3 whitespace-nowrap text-right"><a href="?edit=<?php echo (int)$a['id']; ?>" class="text-blue-600 font-medium text-xs bg-blue-50 px-2 py-1 rounded">Edit</a>
                        <?php if (!$isSuper && (int)$a['id'] !== $me): ?><form method="post" class="inline" onsubmit="return confirm('Hapus pengguna ini?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>"><button class="text-red-500 font-medium text-xs bg-red-50 px-2 py-1 rounded">Hapus</button></form><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
<p class="text-xs text-gray-400 mt-3">Akun pelanggan dikelola di menu Pelanggan. Halaman ini khusus administrator.</p>
<?php admin_foot();
