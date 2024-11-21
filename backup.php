<?php
require 'db_config.php'; // Include your database configuration file

// Create a backup directory if it doesn't exist
$backupDir = 'backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

// Create a backup file name
$backupFile = $backupDir . '/' . $db . '_' . date('Y-m-d_H-i-s') . '.sql';

// Execute the mysqldump command
$command = "mysqldump --opt -h $host -u $user -p$pass $db > $backupFile";

system($command, $output);

// Check if the backup was successful
if ($output === 0) {
    echo "Backup of the database '$db' was successful. Backup file: $backupFile";
} else {
    echo "Error occurred during the backup process.";
}

/* Important Considerations
1. Permissions: Ensure that the PHP script has permission to execute the mysqldump command and write files to the backups directory.
2. Security: Be cautious about storing database credentials directly in the script. Consider using environment variables or a configuration file.
3. Database Size: For very large databases, you may want to implement additional logic to handle timeouts or memory limits.
4. Automate Backups: You can set up a cron job to run this script at regular intervals for automated backups.
*/
?>