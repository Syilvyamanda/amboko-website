<?php
session_start();
include 'koneksi.php';
include 'includes/layout.php';
include 'includes/admin_layout.php';
$editId = (int)($_GET['id'] ?? 0);
if ($editId < 1) redirect('admin_produk.php');
include 'includes/admin_product_form.php';
