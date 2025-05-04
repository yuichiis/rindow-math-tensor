<?php

/**
 * PHP Extension Setup Script
 *
 * This script automates the setup process for a specific PHP extension.
 * It performs the following actions based on the operating system:
 *
 * - Windows & macOS:
 *   1. Checks if the extension binary (.dll or .so) exists in the PHP extension directory.
 *   2. If not found, downloads the appropriate pre-compiled ZIP archive from GitHub Releases.
 *   3. Extracts the archive and copies the extension binary to the PHP extension directory.
 *   4. Checks the php.ini file and activates the `extension=` directive if necessary.
 *
 * - Linux (Debian/Ubuntu based):
 *   1. Downloads the appropriate .deb package from GitHub Releases to a temporary location.
 *   2. Displays the 'sudo apt install' command to be executed.
 *   3. Asks for user confirmation [Y/n].
 *   4. If confirmed, executes the 'sudo apt install' command directly.
 *      => During execution, 'sudo' might ask for the user's password to gain administrator privileges,
 *         even if the script itself wasn't started with 'sudo'.
 *
 * Requirements:
 * - PHP CLI (Command Line Interface).
 * - PHP extensions: 'openssl' (all OS), 'zip' (Windows/macOS only).
 * - PHP configuration: 'allow_url_fopen = On' in php.ini.
 * - Internet connection to download files from GitHub.
 * - Permissions:
 *   - Write access to the temporary directory.
 *   - (Win/Mac) Write access to the PHP extension directory (often requires elevated privileges).
 *   - (Win/Mac) Write access to the php.ini file (often requires elevated privileges).
 *   - (Linux) Ability to run 'sudo apt install' (user needs sudo privileges, password might be required).
 *
 * Usage:
 * 1. Configure the constants PLUGIN_NAME, PLUGIN_VERSION, and DOWNLOAD_URL_BASE below.
 * 2. Run the script from the command line: `php setup_extension.php`
 * 3. The script might require elevated privileges for certain operations:
 *    - Windows: If prompted or errors occur, run Command Prompt or PowerShell 'As Administrator'.
 *    - macOS: If prompted or errors occur (e.g., copying files), run with `sudo php setup_extension.php`.
 *    - Linux: When executing 'sudo apt install', 'sudo' will likely prompt for your password.
 *             Alternatively, starting the script with `sudo php setup_extension.php` ensures privileges upfront,
 *             though 'sudo' might still request a password depending on its configuration.
 * 4. Follow any on-screen instructions (e.g., confirm command execution [Y/n] on Linux).
 * 5. After the script finishes successfully, restart your web server (Apache, Nginx) or PHP-FPM service
 *    to load the new extension. For CLI usage, changes might apply to new terminal sessions.
 */

// --- Configuration ---
define('PLUGIN_NAME', 'rindow_opoverride');
define('PLUGIN_VERSION', '0.1.0');
define('DOWNLOAD_URL_BASE', 'https://github.com/yuichiis/rindow-opoverride/releases/download/');
// --- End Configuration ---


/**
 * Manages the extension setting in the php.ini file.
 * (Function implementation remains the same)
 */
function manageIniSetting(string $pluginName, string $iniFilePath): bool
{
    // ... (Function content is the same as previous version - omitted for brevity) ...
    echo "--- Managing php.ini setting: extension={$pluginName} ---\n";
    echo "Target file: {$iniFilePath}\n";

    $ini_content_raw = @file_get_contents($iniFilePath);
    if ($ini_content_raw === false) { /* ... error ... */ return false; }
    $ini_content = str_replace(["\r\n", "\r"], "\n", $ini_content_raw);
    $pluginNameQuoted = preg_quote($pluginName, '@');
    $pattern_active = "@^\s*extension\s*=\s*{$pluginNameQuoted}\s*(;.*)?$@im";
    $pattern_commented = "@^(\s*;+\s*)(extension\s*=\s*{$pluginNameQuoted}\s*(;.*)?)$@im";
    $modified = false; $new_content = $ini_content;
    if (preg_match($pattern_active, $ini_content)) { /* ... already active ... */ }
    elseif (preg_match($pattern_commented, $ini_content, $matches, PREG_OFFSET_CAPTURE)) { /* ... uncomment ... */ $modified = true; }
    else { /* ... append ... */ $modified = true; }
    if ($modified) {
        echo "Writing changes to php.ini file...\n";
        if (!is_writable($iniFilePath)) { /* ... permission warning ... */ return false; }
        echo "Converting line endings...\n";
        $output_content = str_replace("\n", PHP_EOL, $new_content);
        if (@file_put_contents($iniFilePath, $output_content) !== false) { echo "Success: php.ini updated.\n"; }
        else { echo "Error: Failed to write php.ini.\n"; return false; }
    } else { echo "No changes needed for php.ini.\n"; }
    echo "--- Finished managing php.ini setting ---\n";
    return true;
}


/**
 * Downloads the extension binary/package and prepares or executes installation.
 * (Function description updated slightly for clarity on sudo password prompt)
 */
function installExtensionBinary(
    string $pluginName,
    string $pluginVersion
): bool {
    echo "--- Checking/Installing extension binary/package: {$pluginName} ---\n";

    // --- Determine OS and Architecture ---
    // ... (OS/Arch detection logic remains the same) ...
    $osFamily = PHP_OS_FAMILY; $isWindows = ($osFamily === 'Windows'); $isMac = ($osFamily === 'Darwin'); $isLinux = ($osFamily === 'Linux');
    if (!$isWindows && !$isMac && !$isLinux) { /* ... error ... */ return false; }
    $machineArch = php_uname('m'); $arch = ''; $phpArch = (PHP_INT_SIZE === 8) ? '64' : '32'; $extensionFileName = ''; $targetFileExt = '';
    if ($isWindows) { $arch = ($phpArch === '64') ? 'x64' : 'x86'; $extensionFileName = 'php_' . $pluginName . '.dll'; $targetFileExt = '.zip'; }
    elseif ($isMac) { if ($machineArch === 'arm64') $arch = 'arm64'; elseif ($machineArch === 'x86_64') $arch = 'x64'; else { /* ... error ... */ return false; } $extensionFileName = $pluginName . '.so'; $targetFileExt = '.zip'; }
    elseif ($isLinux) { if ($machineArch === 'x86_64') $arch = 'amd64'; elseif ($machineArch === 'aarch64') $arch = 'arm64'; else { /* ... error ... */ return false; } $extensionFileName = null; $targetFileExt = '.deb'; echo "Linux system detected.\n"; }
    else { /* ... error ... */ return false; }
    echo "OS Family: {$osFamily}, Architecture: {$arch} (Machine: {$machineArch}), PHP Bitness: {$phpArch}-bit\n";
    if ($extensionFileName) echo "Target Extension Filename: {$extensionFileName}\n";

    // --- Linux (.deb package) Specific Workflow ---
    if ($isLinux) {
        // 4. Check prerequisites (allow_url_fopen)
        // ... (check remains the same) ...
        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) { /* ... error ... */ return false; } echo "Prerequisite check OK.\n";

        // 5. Construct download URL
        // ... (URL construction remains the same) ...
        $phpMajor = PHP_MAJOR_VERSION; $phpMinor = PHP_MINOR_VERSION; $phpVersionShort = "{$phpMajor}.{$phpMinor}";
        $tmpPluginName = str_replace('_', '-', $pluginName);
        $downloadFilename = "{$tmpPluginName}-php{$phpVersionShort}_{$pluginVersion}_{$arch}{$targetFileExt}";
        //$downloadUrl = DOWNLOAD_URL_BASE . $pluginVersion . '/' . $downloadFilename; echo "Download URL determined: {$downloadUrl}\n";
        $downloadUrl = DOWNLOAD_URL_BASE . '0.0.10' . '/' . $downloadFilename; echo "Download URL determined: {$downloadUrl}\n";

        // 6. Prepare temporary file path
        // ... (temp path preparation remains the same) ...
        $tempDir = sys_get_temp_dir(); $tempDebFile = rtrim($tempDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $downloadFilename; echo "Temporary download path: {$tempDebFile}\n";

        // 7. Download the .deb file
        // ... (download logic using copy() remains the same) ...
        echo "Downloading .deb package...\n"; $downloadSuccess = false; $copyError = null;
        $context = stream_context_create(['http' => ['timeout' => 60, 'follow_location' => 1, 'max_redirects' => 5]]);
        set_error_handler(function($errno, $errstr) use (&$copyError) { $copyError = $errstr; return true; });
        if (@copy($downloadUrl, $tempDebFile, $context)) { /* ... size check ... */ $downloadSuccess = true; } else { /* ... error handling ... */ }
        restore_error_handler();
        if (!$downloadSuccess) { if (file_exists($tempDebFile)) @unlink($tempDebFile); return false; } echo "Download successful: {$tempDebFile}\n";

        // 8. Ask for confirmation and execute apt install
        echo "\n--- Ready to Install .deb Package ---\n";
        $escapedDebFile = escapeshellarg($tempDebFile);
        // Command includes 'sudo' - it will handle privilege escalation.
        $installCommand = "sudo apt install -y {$escapedDebFile}";

        echo "The script needs to execute the following command to install the package:\n";
        echo "\n  {$installCommand}\n\n";
        echo "This requires administrator privileges and will modify your system.\n";
        echo "IMPORTANT: The 'sudo' command will likely prompt you for your password.\n";
        echo "           Enter your password when prompted to allow the installation.\n";
        echo "(Alternatively, you could run this script with 'sudo php " . basename(__FILE__) . "' to grant privileges upfront).\n";

        echo "Do you want to execute this command now? [Y/n]: ";
        $handle = fopen("php://stdin", "r"); $line = trim(fgets($handle)); fclose($handle);

        if (strtolower($line) === 'y' || $line === '') { // Default to Yes if user just presses Enter
            echo "Executing command (you might be prompted for your sudo password)...\n\n";
            $return_var = -1;
            // passthru() allows the command to interact with the terminal (for password input)
            // and prints its output directly.
            passthru($installCommand, $return_var);

            if ($return_var === 0) {
                echo "\nCommand executed successfully.\n";
                echo "You may need to restart your web server or PHP-FPM service.\n";
                echo "Temporary file {$tempDebFile} can be manually deleted.\n";
                echo "--- Finished checking/installing extension binary/package ---\n";
                return true;
            } else {
                echo "\nError: Command failed (Return code: {$return_var}). Check output for details.\n";
                echo "Downloaded file remains at: {$tempDebFile}\n";
                return false;
            }
        } else {
            echo "Installation command skipped.\n";
            echo "Manually install later using: {$installCommand}\n";
            echo "Downloaded file is at: {$tempDebFile}\n";
            echo "--- Finished checking/installing extension binary/package (skipped execution) ---\n";
            return false;
        }

    // --- Windows / macOS (.zip archive) Specific Workflow ---
    } else {
        // ... (Windows/macOS logic remains the same as the previous version) ...
        // 1. Get/validate extension_dir
        $extensionDir = ini_get('extension_dir'); $realExtensionDir = realpath($extensionDir); if ($realExtensionDir === false || !is_dir($realExtensionDir)) { /* ... error ... */ return false; } $extensionDir = $realExtensionDir; echo "PHP Extension Directory: {$extensionDir}\n";
        // 2. Construct target path
        $targetExtensionPath = rtrim($extensionDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $extensionFileName; echo "Target Extension File Path: {$targetExtensionPath}\n";
        // 3. Check if exists
        if (file_exists($targetExtensionPath)) { echo "Status: Extension file already exists.\n"; return true; } echo "Status: Extension file not found...\n";
        // 4. Check prerequisites
        if (!extension_loaded('zip')) { /* ... error ... */ return false; } if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) { /* ... error ... */ return false; } echo "Prerequisite check OK.\n";
        // 5. Construct download URL
        $phpMajor = PHP_MAJOR_VERSION; $phpMinor = PHP_MINOR_VERSION; $phpVersionShort = "{$phpMajor}.{$phpMinor}"; $threadSafety = PHP_ZTS ? 'ts' : 'nts'; $compiler = 'vs17'; $downloadFilename = '';
        if ($isWindows) { $downloadFilename = "{$pluginName}-php{$phpVersionShort}-{$pluginVersion}-win-{$threadSafety}-{$compiler}-{$arch}{$targetFileExt}"; } elseif ($isMac) { $downloadFilename = "{$pluginName}-php{$phpVersionShort}-{$pluginVersion}-macos-{$arch}{$targetFileExt}"; }
        //$downloadUrl = DOWNLOAD_URL_BASE . $pluginVersion . '/' . $downloadFilename; echo "Download URL determined: {$downloadUrl}\n";
        $downloadUrl = DOWNLOAD_URL_BASE . '0.0.10' . '/' . $downloadFilename; echo "Download URL determined: {$downloadUrl}\n";
        // 6. Prepare temp zip path
        $tempDir = sys_get_temp_dir(); $tempZipFile = @tempnam($tempDir, 'php_ext_zip_'); if ($tempZipFile === false) { /* ... error ... */ return false; } @unlink($tempZipFile); echo "Temporary download path: {$tempZipFile}\n";
        // 7. Download zip
        echo "Downloading .zip archive...\n"; $downloadSuccess = false; $copyError = null; $context = stream_context_create(['http' => ['timeout' => 60, 'follow_location' => 1, 'max_redirects' => 5]]);
        set_error_handler(function($errno, $errstr) use (&$copyError) { $copyError = $errstr; return true; }); if (@copy($downloadUrl, $tempZipFile, $context)) { /* ... size check ... */ $downloadSuccess = true; } else { /* ... error handling ... */ } restore_error_handler();
        if (!$downloadSuccess) { if (file_exists($tempZipFile)) @unlink($tempZipFile); return false; } echo "Download successful.\n";
        // 8. Extract zip
        echo "Extracting zip archive...\n"; $zip = new ZipArchive(); $res = $zip->open($tempZipFile); if ($res !== TRUE) { /* ... error ... */ @unlink($tempZipFile); return false; }
        $extractPath = rtrim($tempDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . uniqid('php_ext_extract_'); if (!@mkdir($extractPath, 0777, true) && !is_dir($extractPath)) { /* ... error ... */ $zip->close(); @unlink($tempZipFile); return false; }
        echo "Extracting to: {$extractPath}\n"; if (!$zip->extractTo($extractPath)) { /* ... error ... */ $zip->close(); @unlink($tempZipFile); cleanupDirectory($extractPath); return false; } $zip->close(); echo "Extraction successful.\n";
        // 9. Find extension file
        $foundExtensionPath = findFileRecursive($extractPath, $extensionFileName); if ($foundExtensionPath === null) { /* ... error ... */ @unlink($tempZipFile); cleanupDirectory($extractPath); return false; } echo "Found extension file: {$foundExtensionPath}\n";
        // 10. Copy to extension dir
        echo "Copying extension file to {$extensionDir}...\n"; if (!is_writable($extensionDir)) { /* ... warning ... */ } if (@copy($foundExtensionPath, $targetExtensionPath)) { echo "Copy successful: {$targetExtensionPath}\n"; } else { /* ... error handling ... */ @unlink($tempZipFile); cleanupDirectory($extractPath); return false; }
        // 11. Cleanup
        echo "Cleaning up temporary files...\n"; @unlink($tempZipFile); cleanupDirectory($extractPath); echo "Cleanup complete.\n";

        echo "--- Finished checking/installing extension binary/package ---\n";
        return true; // Indicate success for Windows/macOS
    }
}


/**
 * Recursively deletes a directory and its contents.
 * (Function implementation remains the same)
 */
function cleanupDirectory(string $dirPath): void { /* ... (Same as previous version) ... */ }

/**
 * Recursively finds a file within a directory.
 * (Function implementation remains the same)
 */
function findFileRecursive(string $dirPath, string $filename): ?string { /* ... (Same as previous version) ... */ }


// --- Main Script Execution ---
echo "PHP Extension Setup Script Initializing...\n";
echo "=========================================\n";
echo "Plugin to manage: " . PLUGIN_NAME . " (Target version: " . PLUGIN_VERSION . ")\n";

// --- Prerequisite Checks ---
// ... (Prerequisite check logic remains the same) ...
$osFamily = PHP_OS_FAMILY; $isLinux = ($osFamily === 'Linux'); $requiredExtensions = ['openssl']; if (!$isLinux) { $requiredExtensions[] = 'zip'; }
$missingExtensions = []; foreach ($requiredExtensions as $ext) { if (!extension_loaded($ext)) $missingExtensions[] = $ext; }
if (!empty($missingExtensions)) { echo "Fatal Error: Missing extensions: " . implode(', ', $missingExtensions) . "\n"; exit(1); }
if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) { echo "Fatal Error: 'allow_url_fopen=On' required.\n"; exit(1); }
echo "Prerequisite checks passed.\n";

// --- Get php.ini Path ---
$iniFilePath = php_ini_loaded_file(); if ($iniFilePath === false) { echo "Fatal Error: Cannot locate php.ini.\n"; exit(1); }
echo "Loaded php.ini file: {$iniFilePath}\n\n";


// --- Step 1: Install/Check Extension Binary/Package ---
$installSuccess = installExtensionBinary(PLUGIN_NAME, PLUGIN_VERSION);

if (!$installSuccess) {
    echo "\nError: The extension setup process failed or was cancelled by the user.\n";
    exit(1); // Exit indicating failure
}

// --- Step 2: Post-Installation Actions (OS Dependent) ---
if ($isLinux) {
    // On Linux, success means the apt command was executed (or attempted).
    // Specific messages were handled inside the function.
    echo "\n=========================================\n";
    echo "Linux package processing sequence completed.\n";
    echo "Please review the messages above regarding the 'apt install' command execution.\n";
} else {
    // On Windows/macOS, success means file copy was done. Proceed to manage php.ini.
    echo "\n"; // Separator
    // Step 2.1: Manage php.ini settings
    if (!manageIniSetting(PLUGIN_NAME, $iniFilePath)) {
         echo "\nError: Failed to update the php.ini file.\n";
         exit(1);
    }
    // Final success message for Windows/macOS
    echo "\n=========================================\n";
    echo "Setup process completed successfully.\n";
    echo "\n";
    echo "★★★ IMPORTANT ★★★\n";
    echo "To activate the changes, you need to:\n";
    echo " - Restart your web server (e.g., Apache, Nginx).\n";
    echo " - OR - Restart your PHP-FPM service.\n";
    echo " - For command-line (CLI) usage, changes might apply to new terminal sessions.\n";
    echo "   Verify with `php -m` or check PHP info.\n";
}

echo "\n";
exit(0); // Exit with success code
?>