<?php
// Generate password hashes for the schema
$admin_hash = password_hash('admin123', PASSWORD_DEFAULT);
$user_hash = password_hash('user123', PASSWORD_DEFAULT);

echo "Admin hash (admin123):\n";
echo $admin_hash . "\n\n";

echo "User hash (user123):\n";
echo $user_hash . "\n\n";

echo "SQL for schema_users.sql:\n";
echo "-- Insert default administrator account (password: admin123)\n";
echo "-- Note: In production, change this password immediately!\n";
echo "INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `user_type`, `is_active`, `created_by`) \n";
echo "VALUES (\n";
echo "  'admin',\n";
echo "  'admin@boekhouden.nl',\n";
echo "  '" . $admin_hash . "', -- password: admin123\n";
echo "  'System Administrator',\n";
echo "  'administrator',\n";
echo "  1,\n";
echo "  NULL\n";
echo ");\n\n";

echo "-- Insert a sample administratie_houder account (password: user123)\n";
echo "INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `user_type`, `is_active`, `created_by`) \n";
echo "VALUES (\n";
echo "  'gebruiker1',\n";
echo "  'gebruiker1@voorbeeld.nl',\n";
echo "  '" . $user_hash . "', -- password: user123\n";
echo "  'Jan Jansen',\n";
echo "  'administratie_houder',\n";
echo "  1,\n";
echo "  1\n";
echo ");\n";
?>