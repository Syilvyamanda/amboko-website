-- AMBOKO: rapikan pilihan di halaman produk (aman dijalankan berulang kali).
-- Jalankan di phpMyAdmin (tab SQL) pada database yang sudah berisi data.
-- Tidak menyentuh stok atau pesanan. Berlaku untuk database lama maupun baru (tidak bergantung ID grup).

-- 1. Hapus pilihan non-warna & non-size pada: Jacket Running (Finish), Kaos (Printing),
--    Celana Bahan (Material/Fit), Long Pants (Style), Short Pants (Type).
DELETE FROM product_option_groups
 WHERE product_id IN (2,3,4,5,6) AND type = 'pill' AND title NOT LIKE '%Size%';

-- 2. Pilihan warna dibangun ulang: satu warna = satu foto di folder asset (sudah dicek satu per satu).
DELETE v FROM product_option_values v JOIN product_option_groups g ON g.id = v.group_id
 WHERE g.type = 'color' AND g.product_id IN (1,2,3,4,5,6);

SET @g := (SELECT id FROM product_option_groups WHERE product_id = 1 AND type = 'color' ORDER BY id LIMIT 1);
INSERT INTO product_option_values (group_id, label, display_label, hex, hex2, image_url, sort_order, is_default) VALUES
(@g,'Hitam',NULL,'#1c1c1c',NULL,'Jacket2.jpg',1,1),
(@g,'Abu-abu Muda',NULL,'#afb0b3',NULL,'Jacket1.png',2,0),
(@g,'Abu-abu Tua',NULL,'#484648',NULL,'Jacket3.jpg',3,0);

SET @g := (SELECT id FROM product_option_groups WHERE product_id = 2 AND type = 'color' ORDER BY id LIMIT 1);
INSERT INTO product_option_values (group_id, label, display_label, hex, hex2, image_url, sort_order, is_default) VALUES
(@g,'All Black',NULL,'#1e1e1e',NULL,'JacketRun4.jpg',1,1),
(@g,'Black Washed','Black Washed (Acid Wash)','#353436',NULL,'JacketRun5.jpg',2,0),
(@g,'Navy',NULL,'#282e45',NULL,'JacketRun3.jpg',3,0),
(@g,'Olive Green',NULL,'#565440',NULL,'JacketRun6.jpg',4,0),
(@g,'Light Grey',NULL,'#babab5',NULL,'JacketRun2.jpg',5,0),
(@g,'Digital Camo','Digital Camo (Grey)','#757471',NULL,'JacketRun1.jpg',6,0);

SET @g := (SELECT id FROM product_option_groups WHERE product_id = 3 AND type = 'color' ORDER BY id LIMIT 1);
INSERT INTO product_option_values (group_id, label, display_label, hex, hex2, image_url, sort_order, is_default) VALUES
(@g,'Maroon',NULL,'#5f272f',NULL,'KAOS1.png',1,1),
(@g,'Olive Green',NULL,'#3e3f21',NULL,'KAOS2.jpg',2,0),
(@g,'White',NULL,'#d1cfd3',NULL,'KAOS3.jpg',3,0),
(@g,'Heather Grey',NULL,'#747579',NULL,'KAOS4.jpg',4,0),
(@g,'Charcoal',NULL,'#5c5657',NULL,'KAOS5.jpg',5,0);

SET @g := (SELECT id FROM product_option_groups WHERE product_id = 4 AND type = 'color' ORDER BY id LIMIT 1);
INSERT INTO product_option_values (group_id, label, display_label, hex, hex2, image_url, sort_order, is_default) VALUES
(@g,'Black',NULL,'#272729',NULL,'CelanaBahan3.jpg',1,1),
(@g,'Charcoal Grey',NULL,'#525257',NULL,'CelanaBahan1.jpg',2,0),
(@g,'Olive Green',NULL,'#6d6f63',NULL,'CelanaBahan2.jpg',3,0);

SET @g := (SELECT id FROM product_option_groups WHERE product_id = 5 AND type = 'color' ORDER BY id LIMIT 1);
INSERT INTO product_option_values (group_id, label, display_label, hex, hex2, image_url, sort_order, is_default) VALUES
(@g,'Charcoal Black',NULL,'#2b2a2b',NULL,'CelanaPanjang2.jpg',1,1),
(@g,'Heather Grey',NULL,'#cacbcd',NULL,'CELANAPANJANG1.png',2,0),
(@g,'Cream',NULL,'#efe5d3',NULL,'CelanaPanjang3.jpg',3,0);

SET @g := (SELECT id FROM product_option_groups WHERE product_id = 6 AND type = 'color' ORDER BY id LIMIT 1);
INSERT INTO product_option_values (group_id, label, display_label, hex, hex2, image_url, sort_order, is_default) VALUES
(@g,'Black & White','Black / White Two-Tone','#202022','#ffffff','CELANAPENDEK1.png',1,1),
(@g,'Solid Black',NULL,'#19191b',NULL,'CELANAPENDEK2.jpg',2,0),
(@g,'Heather Grey','Heather Grey (Side Tape)','#8f8f92',NULL,'CELANAPENDEK3.jpg',3,0),
(@g,'Navy','Navy (Black Waistband)','#22293f',NULL,'CELANAPENDEK4.jpg',4,0),
(@g,'Grey',NULL,'#908f95',NULL,'CELANAPENDEK5.png',5,0);

-- 3. Ulasan: samakan sebutan warna/varian dengan produk yang benar-benar ada.
UPDATE product_reviews SET comment = REPLACE(comment, '(ambil yang Navy & Olive)', '(ambil yang Olive Green)'), variant_info = 'Olive Green / M' WHERE product_id = 3 AND reviewer_name = 'Melani';
UPDATE product_reviews SET variant_info = 'Charcoal / L' WHERE product_id = 3 AND reviewer_name = 'Yoga';
UPDATE product_reviews SET variant_info = 'Maroon / XL' WHERE product_id = 3 AND reviewer_name = 'Bayu';
UPDATE product_reviews SET comment = REPLACE(comment, 'warna beige', 'warna olive green'), variant_info = 'All Size' WHERE product_id = 4 AND reviewer_name = 'Fadhil';
UPDATE product_reviews SET variant_info = 'All Size' WHERE product_id = 4 AND reviewer_name IN ('Rizky','Dimas');
UPDATE product_reviews SET comment = REPLACE(comment, 'warna solid mononya', 'warna solid black-nya') WHERE product_id = 6 AND reviewer_name = 'Aldo Gunawan';
