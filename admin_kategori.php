<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';

const GROUPS = ['TOPS' => 'Atasan (Tops)', 'BOTTOMS' => 'Bawahan (Bottoms)', 'OUTERWEAR' => 'Jaket (Outerwear)'];

function cat_slug(mysqli $conn, string $name, int $selfId = 0): string
{
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'kategori';
    $slug = $base; $n = 1;
    while (true) {
        $st = $conn->prepare("SELECT id FROM categories WHERE slug = ? AND id <> ?");
        $st->bind_param('si', $slug, $selfId);
        $st->execute();
        if (!$st->get_result()->num_rows) return $slug;
        $slug = $base . '-' . (++$n);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name = trim($_POST['name'] ?? '');
        $group = array_key_exists($_POST['group_label'] ?? '', GROUPS) ? $_POST['group_label'] : 'TOPS';
        $upErr = null;
        $image = save_upload($_FILES['image'] ?? [], $upErr);
        if ($name === '') { flash('error', 'Nama kategori wajib diisi.'); }
        elseif ($upErr) { flash('error', $upErr); }
        else {
            $tag = strtoupper($name);
            if ($id) {
                $slug = cat_slug($conn, $name, $id);
                if ($image) {
                    $st = $conn->prepare("UPDATE categories SET name = ?, slug = ?, tag_label = ?, group_label = ?, image_url = ? WHERE id = ?");
                    $st->bind_param('sssssi', $name, $slug, $tag, $group, $image, $id);
                } else {
                    $st = $conn->prepare("UPDATE categories SET name = ?, slug = ?, tag_label = ?, group_label = ? WHERE id = ?");
                    $st->bind_param('ssssi', $name, $slug, $tag, $group, $id);
                }
                $st->execute();
                flash('success', 'Kategori diperbarui.');
            } else {
                $slug = cat_slug($conn, $name);
                $order = (int)$conn->query("SELECT COALESCE(MAX(sort_order),0) + 1 FROM categories")->fetch_row()[0];
                $st = $conn->prepare("INSERT INTO categories (name, slug, tag_label, group_label, sort_order, image_url) VALUES (?,?,?,?,?,?)");
                $st->bind_param('ssssis', $name, $slug, $tag, $group, $order, $image);
                $st->execute();
                flash('success', 'Kategori ditambahkan.');
            }
        }
    } elseif ($action === 'delete' && $id) {
        $used = (int)$conn->query("SELECT COUNT(*) FROM products WHERE category_id = $id")->fetch_row()[0];
        $kids = (int)$conn->query("SELECT COUNT(*) FROM categories WHERE parent_id = $id")->fetch_row()[0];
        if ($used) flash('error', 'Kategori masih dipakai ' . $used . ' produk. Pindahkan produknya dulu.');
        elseif ($kids) flash('error', 'Kategori ini punya sub-kategori. Hapus / pindahkan sub-kategorinya dulu.');
        else { $conn->query("DELETE FROM categories WHERE id = $id"); flash('success', 'Kategori dihapus.'); }
    }
    redirect('admin_kategori.php');
}

$edit = null;
if (!empty($_GET['edit'])) {
    $st = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $eid = (int)$_GET['edit'];
    $st->bind_param('i', $eid);
    $st->execute();
    $edit = $st->get_result()->fetch_assoc() ?: null;
}
$rows = $conn->query("SELECT c.*, p.name AS parent_name, (SELECT COUNT(*) FROM products x WHERE x.category_id = c.id) AS n_products
    FROM categories c LEFT JOIN categories p ON p.id = c.parent_id ORDER BY c.sort_order, c.id")->fetch_all(MYSQLI_ASSOC);

admin_head('Kategori', 'kategori');
$inp = ADMIN_INP;
?>
<div class="grid lg:grid-cols-3 gap-5">
    <section class="bg-white rounded-xl border border-gray-200 p-5 h-fit">
        <h2 class="font-bold mb-4"><?php echo $edit ? 'Edit Kategori' : 'Tambah Kategori'; ?></h2>
        <form method="post" enctype="multipart/form-data" class="space-y-3 text-sm">
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
            <div><label class="text-xs font-bold text-gray-500 uppercase">Nama</label><input name="name" value="<?php echo e($edit['name'] ?? ''); ?>" class="<?php echo $inp; ?> mt-1" required></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Kelompok</label>
                <select name="group_label" class="<?php echo $inp; ?> mt-1">
                    <?php foreach (GROUPS as $k => $label): ?><option value="<?php echo $k; ?>" <?php echo ($edit['group_label'] ?? 'TOPS') === $k ? 'selected' : ''; ?>><?php echo e($label); ?></option><?php endforeach; ?>
                </select></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Gambar</label>
                <?php if (!empty($edit['image_url'])): ?><img src="<?php echo e(img($edit['image_url'])); ?>" alt="" class="w-20 h-24 object-cover rounded-lg mt-1 mb-2 bg-gray-100"><?php endif; ?>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="<?php echo $inp; ?> mt-1"><p class="text-[11px] text-gray-400 mt-1">JPG / PNG / WEBP, maks. 4 MB<?php echo $edit ? '. Kosongkan jika tidak diganti.' : '.'; ?></p></div>
            <div class="flex gap-2">
                <button class="flex-1 bg-black text-white rounded-lg py-2.5 font-bold hover:bg-gray-800">Simpan</button>
                <?php if ($edit): ?><a href="admin_kategori.php" class="px-4 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 font-medium">Batal</a><?php endif; ?>
            </div>
        </form>
    </section>

    <section class="lg:col-span-2 bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table class="w-full text-left text-sm admin-table">
            <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase"><th class="px-5 py-3">Kategori</th><th class="px-5 py-3">Kelompok</th><th class="px-5 py-3 text-right">Produk</th><th class="px-5 py-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($rows as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3"><div class="flex items-center gap-3">
                        <?php if ($c['image_url']): ?><img src="<?php echo e(img($c['image_url'])); ?>" alt="" class="w-10 h-12 object-cover rounded-md bg-gray-100"><?php else: ?><span class="w-10 h-12 rounded-md bg-gray-100 text-gray-300 flex items-center justify-center"><i class="fa-regular fa-image"></i></span><?php endif; ?>
                        <div><b><?php echo e($c['name']); ?></b><?php if ($c['parent_name']): ?><div class="text-xs text-gray-400">Sub dari <?php echo e($c['parent_name']); ?></div><?php endif; ?></div></div></td>
                    <td class="px-5 py-3 text-gray-600"><?php echo e(GROUPS[$c['group_label']] ?? $c['group_label']); ?></td>
                    <td class="px-5 py-3 text-right font-semibold"><?php echo (int)$c['n_products']; ?></td>
                    <td class="px-5 py-3 whitespace-nowrap text-right">
                        <a href="?edit=<?php echo (int)$c['id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium text-xs bg-blue-50 px-2 py-1 rounded">Edit</a>
                        <form method="post" class="inline" onsubmit="return confirm('Hapus kategori ini?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>"><button class="text-red-500 hover:text-red-700 font-medium text-xs bg-red-50 px-2 py-1 rounded">Hapus</button></form></td>
                </tr>
            <?php endforeach; if (!$rows): ?><tr><td colspan="4" class="px-5 py-10 text-center text-gray-500">Belum ada kategori.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
</div>
<?php admin_foot();
