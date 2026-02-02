<?php
/**
 * Script to compile PO files to MO files
 * 
 * Usage:
 *   php compile-translations.php
 * 
 * Requirements:
 *   - PHP with gettext extension (usually available)
 *   - Or use msgfmt command line tool
 */

// Prevent direct web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line.');
}

$languages_dir = __DIR__ . '/languages';
$po_files = glob($languages_dir . '/*.po');

if (empty($po_files)) {
    echo "No PO files found in {$languages_dir}\n";
    exit(1);
}

echo "Compiling translation files...\n\n";

foreach ($po_files as $po_file) {
    $mo_file = str_replace('.po', '.mo', $po_file);
    $basename = basename($po_file);
    
    echo "Processing: {$basename}\n";
    
    // Try using msgfmt command if available
    $msgfmt_cmd = 'msgfmt';
    $command = escapeshellcmd($msgfmt_cmd) . ' -o ' . escapeshellarg($mo_file) . ' ' . escapeshellarg($po_file) . ' 2>&1';
    
    $output = array();
    $return_var = 0;
    exec($command, $output, $return_var);
    
    if ($return_var === 0) {
        echo "  ✓ Successfully compiled to " . basename($mo_file) . "\n";
    } else {
        echo "  ✗ Failed to compile using msgfmt command\n";
        echo "  Error: " . implode("\n", $output) . "\n";
        echo "  \n";
        echo "  Please install gettext tools:\n";
        echo "  - Windows: Install MinGW with mingw32-gettext\n";
        echo "  - Mac: brew install gettext\n";
        echo "  - Linux: sudo apt-get install gettext (or equivalent)\n";
        echo "  \n";
        echo "  Or use WP-CLI: wp i18n make-mo {$po_file} {$languages_dir}\n";
    }
    
    echo "\n";
}

echo "Done!\n";
echo "\n";
echo "Note: If compilation failed, you can also use:\n";
echo "  wp i18n make-mo languages/\n";
echo "  (if WP-CLI is installed)\n";
