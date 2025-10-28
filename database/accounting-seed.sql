-- Seed standard Chart of Accounts for WAPOS
INSERT INTO accounts (code, name, type, is_active) VALUES
('1000','Cash on Hand','ASSET',1),
('1100','Bank','ASSET',1),
('1200','Accounts Receivable','ASSET',1),
('1300','Inventory','ASSET',1),
('2000','Accounts Payable','LIABILITY',1),
('2100','Sales Tax Payable','LIABILITY',1),
('3000','Owner\'s Equity','EQUITY',1),
('4000','Sales Revenue','REVENUE',1),
('4100','Sales Discounts','CONTRA_REVENUE',1),
('5000','Cost of Goods Sold','EXPENSE',1),
('6000','Operating Expenses','EXPENSE',1)
ON DUPLICATE KEY UPDATE name=VALUES(name), type=VALUES(type), is_active=VALUES(is_active);
