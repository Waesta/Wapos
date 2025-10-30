-- Make supplier_id nullable in products table
ALTER TABLE `products` MODIFY COLUMN `supplier_id` INT UNSIGNED NULL;
