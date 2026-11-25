<?php
// Toggle wishlist (dipanggil via fetch dari katalog)
session_start();
include 'koneksi.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'method']); exit; }
$tok = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', (string)$tok)) { http_response_code(419); echo json_encode(['error' => 'csrf']); exit; }
if (!is_logged_in()) { echo json_encode(['login' => true]); exit; }

$uid = user_id();
$pid = (int)($_POST['product_id'] ?? 0);
$chk = $conn->prepare("SELECT 1 FROM products WHERE id = ? AND is_active = 1");
$chk->bind_param('i', $pid);
$chk->execute();
if (!$chk->get_result()->fetch_row()) { echo json_encode(['error' => 'product']); exit; }

$ex = $conn->prepare("SELECT 1 FROM wishlists WHERE user_id = ? AND product_id = ?");
$ex->bind_param('ii', $uid, $pid);
$ex->execute();
if ($ex->get_result()->fetch_row()) {
    $d = $conn->prepare("DELETE FROM wishlists WHERE user_id = ? AND product_id = ?");
    $d->bind_param('ii', $uid, $pid);
    $d->execute();
    echo json_encode(['active' => false]);
} else {
    $i = $conn->prepare("INSERT INTO wishlists (user_id, product_id) VALUES (?, ?)");
    $i->bind_param('ii', $uid, $pid);
    $i->execute();
    echo json_encode(['active' => true]);
}
