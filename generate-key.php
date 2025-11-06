#!/usr/bin/env php
<?php
/**
 * Encryption Key Generator
 *
 * Generates a secure 32-byte encryption key for token encryption
 *
 * Usage: php generate-key.php
 */

echo "=================================\n";
echo "Encryption Key Generator\n";
echo "=================================\n\n";

// Generate a random 32-byte key
$key = random_bytes(32);

// Encode to base64 for storage
$encodedKey = base64_encode($key);

echo "Your encryption key (base64 encoded):\n";
echo $encodedKey . "\n\n";

echo "Add this to your configuration:\n";
echo "--------------------------------\n";
echo "'encryption_key' => base64_decode('" . $encodedKey . "'),\n\n";

echo "OR set as environment variable:\n";
echo "--------------------------------\n";
echo "ENCRYPTION_KEY=" . $encodedKey . "\n\n";

echo "IMPORTANT:\n";
echo "- Keep this key secret and secure\n";
echo "- Do NOT commit it to version control\n";
echo "- Store it in environment variables or secure key management\n";
echo "- Losing this key will make existing encrypted tokens unrecoverable\n";
echo "- Backup this key securely\n";
echo "=================================\n";
