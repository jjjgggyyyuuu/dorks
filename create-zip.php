<?php
/**
 * Domain Value Predictor - ZIP Creation Script
 * 
 * This script creates a ZIP file of the plugin for distribution.
 * Run this file from the command line: php create-zip.php
 */

// Files and directories to include in the ZIP
$files_to_include = [
    'domain-value-predictor.php',
    'README.md',
    'INSTALL.md',
    'includes',
    'admin',
    'templates',
    'assets',
];

// Files and directories to exclude
$exclude = [
    '.git',
    '.github',
    'node_modules',
    'create-zip.php',
    '.cursorrules',
    'vendor/stripe-php.zip',
];

// Create ZIP file
$zip_filename = 'domain-value-predictor.zip';

// Create new ZIP archive
$zip = new ZipArchive();
if ($zip->open($zip_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    exit("Cannot open <$zip_filename>\n");
}

// Function to add files and directories to ZIP
function addFileToZip($path, $zip, $exclude, $base_path = '') {
    $realpath = $base_path . $path;
    
    // Skip excluded files and directories
    foreach ($exclude as $excluded) {
        if (strpos($realpath, $excluded) !== false) {
            return;
        }
    }
    
    if (is_dir($realpath)) {
        // Add directory to ZIP
        $zip->addEmptyDir($path);
        
        // Add all files and subdirectories
        $dir = new DirectoryIterator($realpath);
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot()) {
                $filepath = $path . '/' . $fileinfo->getFilename();
                addFileToZip($filepath, $zip, $exclude, $base_path);
            }
        }
    } else {
        // Add file to ZIP
        $zip->addFile($realpath, $path);
        echo "Added file: $path\n";
    }
}

// Add files and directories to ZIP
foreach ($files_to_include as $path) {
    addFileToZip($path, $zip, $exclude);
}

// Close ZIP file
$zip->close();

echo "ZIP file created: $zip_filename\n";
echo "Size: " . round(filesize($zip_filename) / 1024, 2) . " KB\n";
?> 