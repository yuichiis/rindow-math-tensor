<?php

/**
 * PHP Extension Setup Script
 *
 * (Script description remains the same as previous version)
 *
 * Changes for Linux:
 * - Downloads the .deb package.
 * - Displays the 'sudo apt install' command to be executed.
 * - Asks for user confirmation [Y/n].
 * - If confirmed, executes the 'sudo apt install' command directly.
 *   (Requires the script itself to be run with 'sudo').
 *
 * Requirements:
 * (Requirements remain the same, but emphasize running with sudo on Linux)
 * - Permissions: Requires administrator/root privileges (run with 'sudo' on Linux/macOS,
 *   'Run as Administrator' on Windows) for most operations.
 *
 * Usage:
 * (Usage instructions updated to emphasize sudo for Linux command execution)
 * 1. Configure constants.
 * 2. Run with administrator privileges:
 *    - Windows: Run Command Prompt/PowerShell as Administrator, then `php setup_extension.php`.
 *    - macOS/Linux: Run with `sudo php setup_extension.php`.
 * 3. Follow on-screen instructions. Confirm the command execution prompt [Y/n] on Linux.
 * 4. Restart web server/PHP-FPM after completion.
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
    if ($ini_content_raw === false) {
        echo "Error: Failed to read the php.ini file.\n";
        return false;
    }
    $ini_content = str_replace(["\r\n", "\r"], "\n", $ini_content_raw);
    $pluginNameQuoted = preg_quote($pluginName, '@');
    $pattern_active = "@^\s*extension\s*=\s*{$pluginNameQuoted}\s*(;.*)?$@im";
    $pattern_commented = "@^(\s*;+\s*)(extension\s*=\s*{$pluginNameQuoted}\s*(;.*)?)$@im";

    $modified = false;
    $new_content = $ini_content;

    if (preg_match($pattern_active, $ini_content)) {
        echo "Status: Setting 'extension={$pluginName}' is already active. No changes needed.\n";
    } elseif (preg_match($pattern_commented, $ini_content, $matches, PREG_OFFSET_CAPTURE)) {
        echo "Status: Found commented-out setting 'extension={$pluginName}'. Uncommenting...\n";
        $new_content = preg_replace($pattern_commented, '$2', $ini_content, 1);
        $modified = true;
        echo "Preview of the change (around the modified line):\n";
        $match_offset = $matches[0][1];
        $line_num = substr_count(substr($ini_content, 0, $match_offset), "\n") + 1;
        $preview_lines = array_slice(explode("\n", $new_content), max(0, $line_num - 3), 5);
        echo "...\n" . implode("\n", $preview_lines) . "\n...\n";
    } else {
        echo "Status: Setting 'extension={$pluginName}' not found. Appending to the end of the file.\n";
        if (substr($new_content, -1) !== "\n") {
            $new_content .= "\n";
        }
        $new_content .= "extension={$pluginName}\n";
        $modified = true;
        echo "Line to be appended: extension={$pluginName}\n";
    }

    if ($modified) {
        echo "Writing changes to php.ini file...\n";
        if (!is_writable($iniFilePath)) {
            $osFamily = PHP_OS_FAMILY;
            $isWindows = ($osFamily === 'Windows');
            echo "Warning: No write permission for the php.ini file.\n";
            if (!$isWindows) {
                 echo "   Try running the script with 'sudo': sudo php " . basename(__FILE__) . "\n";
            } else {
                 echo "   Try running the script from a Command Prompt or PowerShell opened 'As Administrator'.\n";
            }
            return false;
        }

        echo "Converting line endings to PHP_EOL ('" . str_replace(["\r", "\n"], ['\\r', '\\n'], PHP_EOL) . "') for writing.\n";
        $output_content = str_replace("\n", PHP_EOL, $new_content);
        if (@file_put_contents($iniFilePath, $output_content) !== false) {
            echo "Success: php.ini file updated successfully.\n";
        } else {
            echo "Error: Failed to write changes to the php.ini file.\n";
            return false;
        }
    } else {
        echo "No changes were made to the php.ini file.\n";
    }

    echo "--- Finished managing php.ini setting ---\n";
    return true;
}


/**
 * Downloads the extension binary/package and prepares or executes installation.
 * - Windows/macOS: Downloads ZIP, extracts, copies .dll/.so to extension_dir.
 * - Linux (Debian/Ubuntu): Downloads .deb, asks for confirmation, then runs 'sudo apt install'.
 *
 * @param string $pluginName The name of the extension.
 * @param string $pluginVersion The version of the extension to download.
 * @return bool True on success, false on failure or if user declines execution.
 */
function installExtensionBinary(
    string $pluginName,
    string $pluginVersion
): bool {
    echo "--- Checking/Installing extension binary/package: {$pluginName} ---\n";

    // --- Determine OS and Architecture ---
    $osFamily = PHP_OS_FAMILY;
    $isWindows = ($osFamily === 'Windows');
    $isMac = ($osFamily === 'Darwin');
    $isLinux = ($osFamily === 'Linux');

    // ... (OS/Arch detection logic remains the same) ...
    if (!$isWindows && !$isMac && !$isLinux) { /* ... error ... */ return false; }
    $machineArch = php_uname('m');
    $arch = '';
    $phpArch = (PHP_INT_SIZE === 8) ? '64' : '32';
    $extensionFileName = '';
    $targetFileExt = '';
    if ($isWindows) { /* ... */ $extensionFileName = 'php_' . $pluginName . '.dll'; $targetFileExt = '.zip'; }
    elseif ($isMac) { /* ... */ $extensionFileName = $pluginName . '.so'; $targetFileExt = '.zip'; }
    elseif ($isLinux) { /* ... */ $extensionFileName = null; $targetFileExt = '.deb'; }
    else { /* ... error ... */ return false; }
    // ... (echo OS info) ...


    // --- Linux (.deb package) Specific Workflow ---
    if ($isLinux) {
        // 4. Check prerequisites (allow_url_fopen)
        // ... (check remains the same) ...
        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) { /* ... error ... */ return false; }
        echo "Prerequisite check OK: 'allow_url_fopen' is enabled.\n";

        // 5. Construct download URL
        // ... (URL construction remains the same) ...
        $phpMajor = PHP_MAJOR_VERSION; $phpMinor = PHP_MINOR_VERSION; $phpVersionShort = "{$phpMajor}.{$phpMinor}";
        if ($machineArch === 'x86_64') $arch = 'amd64'; elseif ($machineArch === 'aarch64') $arch = 'arm64'; else { /* ... error ... */ return false; }
        $tmpPluginName = str_replace('_', '-', $pluginName);
        $downloadFilename = "{$tmpPluginName}-php{$phpVersionShort}_{$pluginVersion}_{$arch}{$targetFileExt}";
        //$downloadUrl = DOWNLOAD_URL_BASE . $pluginVersion . '/' . $downloadFilename;
        $downloadUrl = DOWNLOAD_URL_BASE . '0.0.10' . '/' . $downloadFilename;
        echo "Download URL determined: {$downloadUrl}\n";


        // 6. Prepare temporary file path
        // ... (temp path preparation remains the same) ...
        $tempDir = sys_get_temp_dir();
        $tempDebFile = rtrim($tempDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $downloadFilename;
        echo "Temporary download path: {$tempDebFile}\n";


        // 7. Download the .deb file
        // ... (download logic using copy() remains the same) ...
        echo "Downloading .deb package using copy()...\n";
        $downloadSuccess = false; $copyError = null;
        $context = stream_context_create(['http' => ['timeout' => 60, 'follow_location' => 1, 'max_redirects' => 5]]);
        set_error_handler(function($errno, $errstr) use (&$copyError) { $copyError = $errstr; return true; });
        if (@copy($downloadUrl, $tempDebFile, $context)) { /* ... size check ... */ $downloadSuccess = true; }
        else { /* ... error handling ... */ }
        restore_error_handler();
        if (!$downloadSuccess) { if (file_exists($tempDebFile)) @unlink($tempDebFile); return false; }
        echo "Download successful: {$tempDebFile}\n";


        // 8. *** NEW: Ask for confirmation and execute apt install ***
        echo "\n--- Ready to Install .deb Package ---\n";
        // Escape the filename for safe use in the command
        $escapedDebFile = escapeshellarg($tempDebFile);
        // Construct the command string
        $installCommand = "sudo apt install -y {$escapedDebFile}"; // Include sudo

        echo "The script needs to execute the following command to install the package:\n";
        echo "\n  {$installCommand}\n\n";
        echo "This requires administrator privileges and will modify your system.\n";
        echo "IMPORTANT: Ensure you are running this script using 'sudo' (e.g., 'sudo php " . basename(__FILE__) . "').\n";

        // Ask for user confirmation
        echo "Do you want to execute this command now? [Y/n]: ";
        // Read user input from standard input
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);

        if (strtolower($line) === 'y') {
            echo "Executing command...\n\n";
            // Use passthru() to execute the command and display its output directly
            // passthru() also returns the last line of output, but we primarily care about the return code.
            $return_var = -1; // Initialize return variable
            passthru($installCommand, $return_var);

            // Check the return status of the command
            if ($return_var === 0) {
                echo "\nCommand executed successfully.\n";
                echo "You may need to restart your web server or PHP-FPM service.\n";
                // Optionally, offer to delete the temp file (or just leave it)
                echo "The temporary file {$tempDebFile} can be manually deleted if desired.\n";
                echo "--- Finished checking/installing extension binary/package ---\n";
                return true; // Installation successful
            } else {
                echo "\nError: The command failed to execute correctly (Return code: {$return_var}).\n";
                echo "Please check the output above for specific error messages from 'apt'.\n";
                echo "Common issues include missing dependencies or conflicts.\n";
                echo "You might need to run 'sudo apt update' or resolve issues manually.\n";
                echo "The downloaded file remains at: {$tempDebFile}\n";
                return false; // Installation failed
            }
        } else {
            echo "Installation command was not executed.\n";
            echo "You can manually install the package later using the command:\n";
            echo "  {$installCommand}\n";
            echo "The downloaded file is located at: {$tempDebFile}\n";
            echo "--- Finished checking/installing extension binary/package (skipped execution) ---\n";
            return false; // User chose not to execute, treat as non-success for workflow
        }

    // --- Windows / macOS (.zip archive) Specific Workflow ---
    } else {
        // ... (Windows/macOS logic remains the same as the previous version) ...
        // 1. Get/validate extension_dir
        $extensionDir = ini_get('extension_dir'); $realExtensionDir = realpath($extensionDir);
        if ($realExtensionDir === false || !is_dir($realExtensionDir)) { /* ... error ... */ return false; }
        $extensionDir = $realExtensionDir; echo "PHP Extension Directory: {$extensionDir}\n";
        // 2. Construct target path
        $targetExtensionPath = rtrim($extensionDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $extensionFileName; echo "Target Extension File Path: {$targetExtensionPath}\n";
        // 3. Check if exists
        if (file_exists($targetExtensionPath)) { echo "Status: Extension file already exists.\n"; return true; }
        echo "Status: Extension file not found. Attempting download...\n";
        // 4. Check prerequisites (zip, allow_url_fopen)
        if (!extension_loaded('zip')) { /* ... error ... */ return false; }
        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) { /* ... error ... */ return false; }
        echo "Prerequisite check OK.\n";
        // 5. Construct download URL
        $phpMajor = PHP_MAJOR_VERSION; $phpMinor = PHP_MINOR_VERSION; $phpVersionShort = "{$phpMajor}.{$phpMinor}";
        $threadSafety = PHP_ZTS ? 'ts' : 'nts'; $compiler = 'vs17';
        $downloadFilename = '';
        if ($isWindows) { $downloadFilename = "{$pluginName}-php{$phpVersionShort}-{$pluginVersion}-win-{$threadSafety}-{$compiler}-{$arch}{$targetFileExt}"; }
        elseif ($isMac) { $downloadFilename = "{$pluginName}-php{$phpVersionShort}-{$pluginVersion}-macos-{$arch}{$targetFileExt}"; }
        $downloadUrl = DOWNLOAD_URL_BASE . $pluginVersion . '/' . $downloadFilename; echo "Download URL determined: {$downloadUrl}\n";
        // 6. Prepare temp zip file path
        $tempDir = sys_get_temp_dir(); $tempZipFile = @tempnam($tempDir, 'php_ext_zip_'); if ($tempZipFile === false) { /* ... error ... */ return false; }
        @unlink($tempZipFile); echo "Temporary download path: {$tempZipFile}\n";
        // 7. Download zip
        echo "Downloading .zip archive...\n"; $downloadSuccess = false; $copyError = null;
        $context = stream_context_create(['http' => ['timeout' => 60, 'follow_location' => 1, 'max_redirects' => 5]]);
        set_error_handler(function($errno, $errstr) use (&$copyError) { $copyError = $errstr; return true; });
        if (@copy($downloadUrl, $tempZipFile, $context)) { /* ... size check ... */ $downloadSuccess = true; } else { /* ... error handling ... */ }
        restore_error_handler();
        if (!$downloadSuccess) { if (file_exists($tempZipFile)) @unlink($tempZipFile); return false; } echo "Download successful.\n";
        // 8. Extract zip
        echo "Extracting zip archive...\n"; $zip = new ZipArchive(); $res = $zip->open($tempZipFile); if ($res !== TRUE) { /* ... error ... */ @unlink($tempZipFile); return false; }
        $extractPath = rtrim($tempDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . uniqid('php_ext_extract_'); if (!@mkdir($extractPath, 0777, true) && !is_dir($extractPath)) { /* ... error ... */ $zip->close(); @unlink($tempZipFile); return false; }
        echo "Extracting to: {$extractPath}\n"; if (!$zip->extractTo($extractPath)) { /* ... error ... */ $zip->close(); @unlink($tempZipFile); cleanupDirectory($extractPath); return false; }
        $zip->close(); echo "Extraction successful.\n";
        // 9. Find extension file
        $foundExtensionPath = findFileRecursive($extractPath, $extensionFileName); if ($foundExtensionPath === null) { /* ... error ... */ @unlink($tempZipFile); cleanupDirectory($extractPath); return false; }
        echo "Found extension file: {$foundExtensionPath}\n";
        // 10. Copy to extension dir
        echo "Copying extension file to {$extensionDir}...\n"; if (!is_writable($extensionDir)) { /* ... warning ... */ }
        if (@copy($foundExtensionPath, $targetExtensionPath)) { echo "Copy successful: {$targetExtensionPath}\n"; } else { /* ... error handling ... */ @unlink($tempZipFile); cleanupDirectory($extractPath); return false; }
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
// Now returns bool for all OS on success/failure
$installSuccess = installExtensionBinary(PLUGIN_NAME, PLUGIN_VERSION);

if (!$installSuccess) {
    // Specific error messages should have been printed inside installExtensionBinary
    echo "\nError: The extension setup process failed or was cancelled by the user.\n";
    exit(1); // Exit indicating failure
}

// --- Step 2: Post-Installation Actions (OS Dependent) ---
// If install was successful (returned true)

if ($isLinux) {
    // On Linux, success means either the command was executed well, or potentially skipped.
    // The specific messages are handled inside installExtensionBinary. Just give a generic success message here.
    echo "\n=========================================\n";
    echo "Linux package processing completed.\n";
    echo "Please review the messages above for installation status and next steps.\n";

} else {
    // On Windows/macOS, success means the file was copied. Proceed to manage php.ini.
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
    echo " - For command-line (CLI) usage, the change might apply to new terminal sessions.\n";
    echo "   Verify with `php -m` or check PHP info.\n";
}

echo "\n";
exit(0); // Exit with success code
