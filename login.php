<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '', '');
if (is_logged_in()) redirect(is_admin() ? 'dashboard.php' : ($next ?: 'katalog.php'));

$error = null;
if (isset($_POST['login'])) {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    // pembatasan percobaan sederhana (5x / 10 menit per sesi)
    $_SESSION['login_try'] = array_filter($_SESSION['login_try'] ?? [], fn($t) => $t > time() - 600);
    if (count($_SESSION['login_try']) >= 5) {
        $error = 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.';
    } else {
        $st = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $st->bind_param('s', $email);
        $st->execute();
        $data = $st->get_result()->fetch_assoc();

        $ok = false;
        if ($data) {
            if (password_verify($password, $data['password'])) {
                $ok = true;
                if (password_needs_rehash($data['password'], PASSWORD_DEFAULT)) {
                    $nh = password_hash($password, PASSWORD_DEFAULT);
                    $up = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $up->bind_param('si', $nh, $data['id']);
                    $up->execute();
                }
            } elseif (!str_starts_with($data['password'], '$2y$') && hash_equals($data['password'], $password)) {
                // akun lama dengan password plaintext -> otomatis di-upgrade ke hash
                $ok = true;
                $nh = password_hash($password, PASSWORD_DEFAULT);
                $up = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $up->bind_param('si', $nh, $data['id']);
                $up->execute();
            }
        }

        if ($ok) {
            $cart = $_SESSION['cart'] ?? [];
            session_regenerate_id(true);
            $_SESSION['cart'] = $cart;
            $_SESSION['user_id'] = (int)$data['id'];
            $_SESSION['role'] = $data['role'];
            $_SESSION['name'] = $data['name'];
            unset($_SESSION['login_try']);
            redirect($data['role'] === 'admin' ? 'dashboard.php' : ($next ?: 'katalog.php'));
        }
        $_SESSION['login_try'][] = time();
        $error = 'Email atau password salah!';
    }
}

page_head('AMBOKO - Login', ['bodyClass' => 'bg-paper min-h-screen']);
render_flash();
?>
<div class="min-h-screen flex flex-col items-center justify-center px-4 py-10">
    <a href="index.php" class="mb-6 text-sm text-gray-500 hover:text-black"><i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Website</a>
    <div class="bg-white p-8 rounded-2xl shadow-sm w-full max-w-sm border border-gray-200">
        <div class="flex items-center justify-center gap-2 mb-1">
            <span class="inline-block w-3.5 h-3.5 bg-brand"></span>
            <h1 class="font-head font-extrabold text-3xl tracking-tight">AMBOKO</h1>
        </div>
        <p class="text-center text-gray-500 text-sm mb-6">Masuk ke akun Anda</p>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-sm text-center"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="next" value="<?php echo e($next); ?>">
            <label class="block text-[11px] font-bold tracking-wider text-gray-600 mb-1.5">EMAIL</label>
            <input type="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" class="w-full px-4 py-3 mb-4 bg-paper rounded-xl outline-none focus:ring-2 focus:ring-black text-sm" required autofocus>
            <label class="block text-[11px] font-bold tracking-wider text-gray-600 mb-1.5">PASSWORD</label>
            <input type="password" name="password" class="w-full px-4 py-3 mb-6 bg-paper rounded-xl outline-none focus:ring-2 focus:ring-black text-sm" required>
            <button type="submit" name="login" class="w-full bg-black text-white font-bold py-3.5 rounded-xl hover:bg-gray-800 transition text-sm tracking-wide">MASUK</button>
        </form>
        <p class="text-center text-sm text-gray-600 mt-5">
            Belum punya akun? <a href="register.php<?php echo $next ? '?next=' . urlencode($next) : ''; ?>" class="text-black font-bold hover:underline">Daftar di sini</a>
        </p>
    </div>
</div>
<?php page_end();
