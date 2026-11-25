<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '', '');
if (is_logged_in()) redirect('katalog.php');

$error = null;
if (isset($_POST['register'])) {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($name === '' || mb_strlen($name) > 100) {
        $error = 'Nama lengkap wajib diisi (maks. 100 karakter).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } else {
        $cek = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $cek->bind_param('s', $email);
        $cek->execute();
        if ($cek->get_result()->num_rows > 0) {
            $error = 'Email sudah terdaftar! Gunakan email lain.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'customer')");
            $ins->bind_param('sss', $name, $email, $hash);
            if ($ins->execute()) {
                flash('success', 'Pendaftaran berhasil! Silakan login.');
                redirect('login.php' . ($next ? '?next=' . urlencode($next) : ''));
            }
            $error = 'Terjadi kesalahan saat menyimpan data. Coba lagi.';
        }
    }
}

page_head('AMBOKO - Daftar', ['bodyClass' => 'bg-paper min-h-screen']);
?>
<div class="min-h-screen flex flex-col items-center justify-center px-4 py-10">
    <a href="index.php" class="mb-6 text-sm text-gray-500 hover:text-black"><i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Website</a>
    <div class="bg-white p-8 rounded-2xl shadow-sm w-full max-w-sm border border-gray-200">
        <div class="flex items-center justify-center gap-2 mb-1">
            <span class="inline-block w-3.5 h-3.5 bg-brand"></span>
            <h1 class="font-head font-extrabold text-3xl tracking-tight">AMBOKO</h1>
        </div>
        <p class="text-center text-gray-500 text-sm mb-6">Buat akun pelanggan baru</p>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-sm text-center"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="next" value="<?php echo e($next); ?>">
            <label class="block text-[11px] font-bold tracking-wider text-gray-600 mb-1.5">NAMA LENGKAP</label>
            <input type="text" name="name" value="<?php echo e($_POST['name'] ?? ''); ?>" class="w-full px-4 py-3 mb-4 bg-paper rounded-xl outline-none focus:ring-2 focus:ring-black text-sm" required maxlength="100">
            <label class="block text-[11px] font-bold tracking-wider text-gray-600 mb-1.5">EMAIL</label>
            <input type="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" class="w-full px-4 py-3 mb-4 bg-paper rounded-xl outline-none focus:ring-2 focus:ring-black text-sm" required>
            <label class="block text-[11px] font-bold tracking-wider text-gray-600 mb-1.5">PASSWORD</label>
            <input type="password" name="password" minlength="6" class="w-full px-4 py-3 mb-6 bg-paper rounded-xl outline-none focus:ring-2 focus:ring-black text-sm" required>
            <button type="submit" name="register" class="w-full bg-black text-white font-bold py-3.5 rounded-xl hover:bg-gray-800 transition text-sm tracking-wide">DAFTAR SEKARANG</button>
        </form>
        <p class="text-center text-sm text-gray-600 mt-5">
            Sudah punya akun? <a href="login.php<?php echo $next ? '?next=' . urlencode($next) : ''; ?>" class="text-black font-bold hover:underline">Login di sini</a>
        </p>
    </div>
</div>
<?php page_end();
