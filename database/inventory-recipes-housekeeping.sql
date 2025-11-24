-- Inventory & Housekeeping Enhancements
-- Run these statements to enable recipe-based consumption and housekeeping consumables.

-- Product Recipes (Bill of Materials for menu items / kits)
CREATE TABLE IF NOT EXISTS product_recipes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    ingredient_product_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(18,4) NOT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_recipe_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_recipe_ingredient FOREIGN KEY (ingredient_product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_recipe_product (product_id),
    INDEX idx_recipe_ingredient (ingredient_product_id)
) ENGINE=InnoDB;

-- Housekeeping Task Consumables
CREATE TABLE IF NOT EXISTS housekeeping_task_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(18,4) NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
    consumed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_task_item_task FOREIGN KEY (task_id) REFERENCES housekeeping_tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_task_item_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_task_item_task (task_id),
    INDEX idx_task_item_product (product_id)
) ENGINE=InnoDB;
