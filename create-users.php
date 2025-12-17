<?php
// Create default users with correct password hashes

$superadminPassword = password_hash('thepurpose', PASSWORD_DEFAULT);
$adminPassword = password_hash('admin', PASSWORD_DEFAULT);

echo "-- Insert default users\n";
echo "INSERT INTO users (username, password, full_name, email, role, is_active) VALUES\n";
echo "('superadmin', '$superadminPassword', 'Super Administrator', 'superadmin@wapos.local', 'admin', 1),\n";
echo "('admin', '$adminPassword', 'Administrator', 'admin@wapos.local', 'admin', 1)\n";
echo "ON DUPLICATE KEY UPDATE username=username;\n";
