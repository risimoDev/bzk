<?php
/**
 * Email Configuration Update Script
 * Updates all PHPMailer configurations across the project to use your new mail server
 */

$old_configs = [
    'mail.bzkprint.ru',
    'mailuser',
    'risimo1517'
];

$new_configs = [
    'mail.bzkprint.ru', // Your new mail server hostname
    'info@bzkprint.ru', // New username
    'pimzpDtsUn9GzTM!' // New password - update this!
];

$files_to_update = [
    'forgot-password.php',
    // Add other files that use PHPMailer here
];

echo "🔧 Email Configuration Update Script\n";
echo "=====================================\n\n";

foreach ($files_to_update as $file) {
    if (!file_exists($file)) {
        echo "❌ File not found: $file\n";
        continue;
    }

    $content = file_get_contents($file);
    $original_content = $content;

    // Update SMTP settings
    $content = str_replace(
        "\$mail->Host = 'mail.bzkprint.ru';",
        "\$mail->Host = '{$new_configs[0]}';",
        $content
    );

    $content = str_replace(
        "\$mail->Username = 'mailuser';",
        "\$mail->Username = '{$new_configs[1]}';",
        $content
    );

    $content = str_replace(
        "\$mail->Password = 'risimo1517';",
        "\$mail->Password = '{$new_configs[2]}';",
        $content
    );

    if ($content !== $original_content) {
        // Create backup
        copy($file, $file . '.backup_' . date('Y-m-d_H-i-s'));

        // Write updated content
        file_put_contents($file, $content);
        echo "✅ Updated: $file\n";
    } else {
        echo "ℹ️  No changes needed: $file\n";
    }
}

echo "\n📧 Email Configuration Summary:\n";
echo "==============================\n";
echo "SMTP Server: {$new_configs[0]}\n";
echo "Username: {$new_configs[1]}\n";
echo "Password: [HIDDEN]\n";
echo "Port: 587 (STARTTLS)\n";
echo "Security: TLS/STARTTLS\n";

echo "\n⚠️  Remember to:\n";
echo "1. Update the password in this script before running\n";
echo "2. Test email sending after configuration\n";
echo "3. Set up proper DNS records (SPF, DKIM, DMARC)\n";
echo "4. Configure reverse DNS (PTR record)\n";

echo "\n✅ Configuration update completed!\n";
?>