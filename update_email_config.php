<?php
/**
 * Update email configuration script
 * This script updates the SMTP settings in all PHPMailer-using files
 */

// Your SMTP configuration
$smtp_config = [
    'host' => 'mail.hosting.reg.ru',
    'port' => 465,
    'encryption' => 'STARTTLS',
    'username' => 'mailer@bzkprint.ru',
    'password' => '42Y-kPa-28M-sic',
    'from_email' => 'mailer@bzkprint.ru',
    'from_name' => 'BZK Print'
];

echo "Updating email configuration...\n";

// Files that use PHPMailer
$files = [
    'admin/messaging/send_process.php',
    'forgot-password.php',
    'register.php'
];

foreach ($files as $file) {
    $file_path = __DIR__ . '/' . $file;

    if (file_exists($file_path)) {
        echo "Updating $file...\n";

        $content = file_get_contents($file_path);

        // Update SMTP Host
        $content = preg_replace(
            '/(\$mail->Host\s*=\s*[^;]*;)/',
            '$mail->Host = \'' . $smtp_config['host'] . '\';',
            $content
        );

        // Update SMTP Port
        $content = preg_replace(
            '/(\$mail->Port\s*=\s*[^;]*;)/',
            '$mail->Port = ' . $smtp_config['port'] . ';',
            $content
        );

        // Update SMTP Username
        $content = preg_replace(
            '/(\$mail->Username\s*=\s*[^;]*;)/',
            '$mail->Username = \'' . $smtp_config['username'] . '\';',
            $content
        );

        // Update SMTP Password
        $content = preg_replace(
            '/(\$mail->Password\s*=\s*[^;]*;)/',
            '$mail->Password = \'' . $smtp_config['password'] . '\';',
            $content
        );

        // Update SMTP From Email (first pattern)
        $content = preg_replace(
            '/(\$mail->setFrom\s*\(\s*[^,]+,)/',
            '$mail->setFrom(\'' . $smtp_config['from_email'] . '\',',
            $content
        );

        // Update SMTP From Email (second pattern)
        $content = preg_replace(
            '/(setFrom\s*\(\s*["\'])([^"\']*)(["\'])/',
            '$1' . $smtp_config['from_email'] . '$3',
            $content
        );

        file_put_contents($file_path, $content);
    } else {
        echo "File $file not found!\n";
    }
}

// Update .env file
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    echo "Updating .env file...\n";

    $env_content = file_get_contents($env_file);

    // Add SMTP configuration to .env
    $smtp_env_config = "\n# --- SMTP Configuration ---\n";
    $smtp_env_config .= "SMTP_HOST=" . $smtp_config['host'] . "\n";
    $smtp_env_config .= "SMTP_PORT=" . $smtp_config['port'] . "\n";
    $smtp_env_config .= "SMTP_USERNAME=" . $smtp_config['username'] . "\n";
    $smtp_env_config .= "SMTP_PASSWORD=" . $smtp_config['password'] . "\n";
    $smtp_env_config .= "SMTP_FROM_EMAIL=" . $smtp_config['from_email'] . "\n";
    $smtp_env_config .= "SMTP_FROM_NAME=" . $smtp_config['from_name'] . "\n";

    // Check if SMTP config already exists
    if (strpos($env_content, 'SMTP_HOST') === false) {
        $env_content .= $smtp_env_config;
        file_put_contents($env_file, $env_content);
    }
}

echo "Email configuration updated successfully!\n";
echo "SMTP Host: " . $smtp_config['host'] . "\n";
echo "SMTP Port: " . $smtp_config['port'] . "\n";
echo "SMTP Username: " . $smtp_config['username'] . "\n";
echo "SMTP From Email: " . $smtp_config['from_email'] . "\n";
?>