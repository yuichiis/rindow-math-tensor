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
 *   4. Checks the php.ini file and activates the `extension=` directive for the target extension if necessary.
 *   5. Checks for prerequisite extensions (openssl, zip) and offers to enable them in php.ini if missing.
 *
 * - Linux (Debian/Ubuntu based):
 *   1. Downloads the appropriate .deb package from GitHub Releases to a temporary location.
 *   2. Displays the 'sudo apt install' command to be executed.
 *   3. Asks for user confirmation [Y/n].
 *   4. If confirmed, executes the 'sudo apt install' command directly.
 *      => During execution, 'sudo' might ask for the user's password.
 *
 * Requirements:
 * - PHP CLI (Command Line Interface).
 * - PHP extensions: 'openssl' (all OS), 'zip' (Windows/macOS only).
 * - PHP configuration: 'allow_url_fopen = On' in php.ini.
 * - Internet connection to download files from GitHub.
 * - Permissions: Write access to temp dir, elevated privileges (sudo/admin) often needed for
 *   extension dir, php.ini modification, and 'apt install'.
 *
 * Usage:
 * 1. Configure the constants PLUGIN_NAME, PLUGIN_VERSION, and DOWNLOAD_URL_BASE below.
 * 2. Run the script from the command line: `php setup_extension.php`
 * 3. Use elevated privileges if needed:
 *    - Windows: Run Command Prompt/PowerShell as Administrator.
 *    - macOS/Linux: Run with `sudo php setup_extension.php`.
 * 4. Follow on-screen instructions (confirm prompts [Y/n]).
 * 5. After successful completion, restart your web server/PHP-FPM.
 */

// --- Configuration ---
define('PLUGIN_NAME', 'rindow_opoverride');
define('PLUGIN_VERSION', '0.1.0');
define('DOWNLOAD_URL_BASE', 'https://github.com/yuichiis/rindow-opoverride/releases/download/');
// --- End Configuration ---


/**
 * Enables missing prerequisite extensions (openssl, zip) in php.ini.
 * Attempts to uncomment existing lines or appends new lines if missing.
 * This is primarily for Windows/macOS environments.
 *
 * @param array $missingExtensions Array of missing extension names ('openssl', 'zip').
 * @param string $iniFilePath Full path to the php.ini file.
 * @return bool True if php.ini was successfully modified (or no changes needed), false otherwise.
 */
function enableMissingExtensions(array $missingExtensions, string $iniFilePath): bool
{
    echo "--- Attempting to enable missing extensions in php.ini ---\n";
    echo "Target file: {$iniFilePath}\n";

    $ini_content_raw = @file_get_contents($iniFilePath);
    if ($ini_content_raw === false) {
        echo "Error: Failed to read the php.ini file.\n";
        return false;
    }
    $ini_content = str_replace(["\r\n", "\r"], "\n", $ini_content_raw);
    $new_content = $ini_content;
    $modified = false;
    $osFamily = PHP_OS_FAMILY;
    $isWindows = ($osFamily === 'Windows');

    foreach ($missingExtensions as $extName) {
        echo "Processing missing extension: {$extName}\n";

        $extFileNameBase = $extName;
        $extDirectiveWin = "extension=php_{$extFileNameBase}.dll";
        $extDirectiveNix = "extension={$extFileNameBase}.so";
        $extDirectiveGeneric = "extension={$extFileNameBase}";

        $anyExtDirective = "(?:{$extDirectiveWin}|{$extDirectiveNix}|{$extDirectiveGeneric})";
        $pattern_active = "@^\s*{$anyExtDirective}\s*(;.*)?$@im";
        $pattern_commented = "@^(\s*;+\s*)({$anyExtDirective})(\s*(;.*)?)?$@im";

        $foundActive = false;
        $foundCommentedAndReplaced = false;

        // 1. Check if already active
        if (preg_match($pattern_active, $new_content)) {
            echo "Status: An active 'extension={$extName}' directive seems to exist. Skipping.\n";
            $foundActive = true;
        }

        // 2. If not active, try to uncomment
        if (!$foundActive) {
            $count = 0;
            $temp_content = preg_replace_callback(
                $pattern_commented,
                function ($matches) { return trim($matches[2]); }, // Return the directive part
                $new_content, 1, $count
            );
            if ($count > 0) {
                echo "Status: Found and uncommented a directive for 'extension={$extName}'.\n";
                $new_content = $temp_content;
                $modified = true;
                $foundCommentedAndReplaced = true;
            }
        }

        // 3. If not found active or uncommented, append
        if (!$foundActive && !$foundCommentedAndReplaced) {
            $lineToAdd = $isWindows ? $extDirectiveWin : $extDirectiveNix;
            echo "Status: Directive for 'extension={$extName}' not found or couldn't be uncommented. Appending '{$lineToAdd}'...\n";
            if (substr($new_content, -1) !== "\n") $new_content .= "\n";
            $new_content .= $lineToAdd . "\n";
            $modified = true;
        }
    } // end foreach

    // --- Write changes back ---
    if ($modified) {
        echo "\nWriting changes to enable prerequisite extensions in php.ini...\n";
        if (!is_writable($iniFilePath)) {
            echo "Warning: No write permission for php.ini ({$iniFilePath}). Cannot enable extensions.\n";
            if (!$isWindows) echo "   Try running with 'sudo'.\n"; else echo "   Try running 'As Administrator'.\n";
            return false;
        }
        $output_content = str_replace("\n", PHP_EOL, $new_content);
        if (@file_put_contents($iniFilePath, $output_content) !== false) {
            echo "Success: php.ini file updated.\n";
        } else {
            echo "Error: Failed to write changes to php.ini.\n"; return false;
        }
    } else {
        echo "No changes needed in php.ini for prerequisites.\n";
    }
    echo "--- Finished attempting to enable extensions ---\n";
    return true;
}


/**
 * Manages the specific target extension setting (e.g., rindow_opoverride) in php.ini.
 * (This function handles the main plugin, not prerequisites)
 *
 * @param string $pluginName The name of the target extension.
 * @param string $iniFilePath Full path to the php.ini file.
 * @return bool True on success or if no changes needed, false on failure.
 */
function manageIniSetting(string $pluginName, string $iniFilePath): bool
{
    echo "--- Managing php.ini setting for main extension: extension={$pluginName} ---\n";
    echo "Target file: {$iniFilePath}\n";

    $ini_content_raw = @file_get_contents($iniFilePath);
    if ($ini_content_raw === false) {
        echo "Error: Failed to read php.ini file.\n"; return false;
    }
    $ini_content = str_replace(["\r\n", "\r"], "\n", $ini_content_raw);
    $new_content = $ini_content;
    $modified = false;
    $osFamily = PHP_OS_FAMILY;
    $isWindows = ($osFamily === 'Windows');

    // Determine the exact directive format for the target plugin
    $pluginDirective = $isWindows ? "extension=php_{$pluginName}.dll" : "extension={$pluginName}.so";
    $pluginDirectiveQuoted = preg_quote($pluginDirective, '@'); // Escape for regex

    // Patterns specific to the target plugin's directive
    $pattern_active = "@^\s*{$pluginDirectiveQuoted}\s*(;.*)?$@im";
    // Ensure the comment pattern matches the specific directive format
    $pattern_commented = "@^(\s*;+\s*)({$pluginDirectiveQuoted})(\s*(;.*)?)?$@im";

    $foundActive = false;
    $foundCommentedAndReplaced = false;

    // 1. Check if already active
    if (preg_match($pattern_active, $new_content)) {
        echo "Status: Target setting '{$pluginDirective}' is already active. No changes needed.\n";
        $foundActive = true;
    }

    // 2. If not active, try to uncomment
    if (!$foundActive) {
        $count = 0;
        $temp_content = preg_replace_callback(
            $pattern_commented,
            function ($matches) { return trim($matches[2]); }, // Return the directive part
            $new_content, 1, $count
        );
        if ($count > 0) {
            echo "Status: Found and uncommented the target directive '{$pluginDirective}'.\n";
            $new_content = $temp_content;
            $modified = true;
            $foundCommentedAndReplaced = true;
        }
    }

    // 3. If not found active or uncommented, append
    if (!$foundActive && !$foundCommentedAndReplaced) {
        $lineToAdd = $pluginDirective; // Use the specific directive determined earlier
        echo "Status: Target directive '{$lineToAdd}' not found. Appending...\n";
        if (substr($new_content, -1) !== "\n") $new_content .= "\n";
        $new_content .= $lineToAdd . "\n";
        $modified = true;
    }

    // --- Write changes back ---
    if ($modified) {
        echo "\nWriting changes for target extension to php.ini...\n";
        if (!is_writable($iniFilePath)) {
            echo "Warning: No write permission for php.ini ({$iniFilePath}). Cannot update setting.\n";
            if (!$isWindows) echo "   Try running with 'sudo'.\n"; else echo "   Try running 'As Administrator'.\n";
            return false;
        }
        $output_content = str_replace("\n", PHP_EOL, $new_content);
        if (@file_put_contents($iniFilePath, $output_content) !== false) {
            echo "Success: php.ini file updated for {$pluginName}.\n";
        } else {
            echo "Error: Failed to write changes to php.ini.\n"; return false;
        }
    } else {
        echo "No changes needed in php.ini for the target extension.\n";
    }
    echo "--- Finished managing php.ini setting for main extension ---\n";
    return true;
}


/**
 * Downloads the extension binary/package and prepares or executes installation.
 * (Function implementation remains the same)
 */
function installExtensionBinary(string $pluginName, string $pluginVersion): bool|string
{
    echo "--- Checking/Installing extension binary/package: {$pluginName} ---\n";
    $osFamily = PHP_OS_FAMILY; $isWindows = ($osFamily === 'Windows'); $isMac = ($osFamily === 'Darwin'); $isLinux = ($osFamily === 'Linux');
    if (!$isWindows && !$isMac && !$isLinux) { echo "Error: Unsupported OS: {$osFamily}\n"; return false; }
    $machineArch = php_uname('m'); $arch = ''; $phpArch = (PHP_INT_SIZE === 8) ? '64' : '32'; $extensionFileName = ''; $targetFileExt = '';
    if ($isWindows) { $arch = ($phpArch === '64') ? 'x64' : 'x86'; $extensionFileName = 'php_' . $pluginName . '.dll'; $targetFileExt = '.zip'; }
    elseif ($isMac) { if ($machineArch === 'arm64') $arch = 'arm64'; elseif ($machineArch === 'x86_64') $arch = 'x64'; else { echo "Error: Unsupported macOS arch: {$machineArch}\n"; return false; } $extensionFileName = $pluginName . '.so'; $targetFileExt = '.zip'; }
    elseif ($isLinux) { if ($machineArch === 'x86_64') $arch = 'amd64'; elseif ($machineArch === 'aarch64') $arch = 'arm64'; else { echo "Error: Unsupported Linux arch: {$machineArch}\n"; return false; } $extensionFileName = null; $targetFileExt = '.deb'; echo "Linux system detected.\n"; }
    else { return false; }
    echo "OS Family: {$osFamily}, Arch: {$arch} (Machine: {$machineArch}), PHP Bitness: {$phpArch}-bit\n";
    if ($extensionFileName) echo "Target Extension Filename: {$extensionFileName}\n";

    if ($isLinux) {
        // --- Linux (.deb) Workflow ---
        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) { echo "Error: allow_url_fopen=On required.\n"; return false; } echo "Prerequisite check OK.\n";
        $phpMajor = PHP_MAJOR_VERSION; $phpMinor = PHP_MINOR_VERSION; $phpVersionShort = "{$phpMajor}.{$phpMinor}";
        $tmpPluginName = str_replace('_', '-', $pluginName);
        $downloadFilename = "{$tmpPluginName}-php{$phpVersionShort}_{$pluginVersion}_{$arch}{$targetFileExt}";
        //$downloadUrl = DOWNLOAD_URL_BASE . $pluginVersion . '/' . $downloadFilename; echo "Download URL: {$downloadUrl}\n";
        $downloadUrl = DOWNLOAD_URL_BASE . '0.0.10' . '/' . $downloadFilename; echo "Download URL: {$downloadUrl}\n";
        $tempDir = sys_get_temp_dir(); $tempDebFile = rtrim($tempDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $downloadFilename; echo "Temp download path: {$tempDebFile}\n";
        echo "Downloading .deb package...\n"; $downloadSuccess = false; $copyError = null; $context = stream_context_create(['http' => ['timeout' => 60, 'follow_location' => 1, 'max_redirects' => 5]]);
        set_error_handler(function($errno, $errstr) use (&$copyError) { $copyError = $errstr; return true; });
        if (@copy($downloadUrl, $tempDebFile, $context)) { if (file_exists($tempDebFile) && filesize($tempDebFile) > 1024) $downloadSuccess = true; else echo "Error: Downloaded file empty/small.\n"; } else { echo "Error: Download failed.\n"; $lastError=error_get_last(); $errorMessage=$copyError?:($lastError['message']??'Unknown'); echo " Details: {$errorMessage}\n"; }
        restore_error_handler();
        if (!$downloadSuccess) { if (file_exists($tempDebFile)) @unlink($tempDebFile); return false; } echo "Download successful: {$tempDebFile}\n";
        echo "\n--- Ready to Install .deb Package ---\n";
        $escapedDebFile = escapeshellarg($tempDebFile); $installCommand = "sudo apt install -y {$escapedDebFile}";
        echo "Command to execute: {$installCommand}\n"; echo "Requires admin privileges (sudo might ask for password).\n";
        echo "Execute now? [Y/n]: "; $handle = fopen("php://stdin", "r"); $line = trim(fgets($handle)); fclose($handle);
        if (strtolower($line) === 'y' || $line === '') {
            echo "Executing command...\n\n"; $return_var = -1; passthru($installCommand, $return_var);
            if ($return_var === 0) { echo "\nCommand successful.\n"; echo "--- Finished ---\n"; return true; } // Return true on successful execution for Linux too
            else { echo "\nError: Command failed (Code: {$return_var}). File: {$tempDebFile}\n"; return false; }
        } else { echo "Installation skipped. File: {$tempDebFile}\n"; echo "--- Finished (skipped execution) ---\n"; return false; } // Treat skipping as non-success for workflow
    } else {
        // --- Windows/macOS (.zip) Workflow ---
        $extensionDir = ini_get('extension_dir'); $realExtensionDir = realpath($extensionDir); if ($realExtensionDir === false || !is_dir($realExtensionDir)) { echo "Error: Invalid extension_dir: {$extensionDir}\n"; return false; } $extensionDir = $realExtensionDir; echo "Extension Dir: {$extensionDir}\n";
        $targetExtensionPath = rtrim($extensionDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $extensionFileName; echo "Target Path: {$targetExtensionPath}\n";
        if (file_exists($targetExtensionPath)) { echo "Status: Already exists.\n"; echo "--- Finished ---\n"; return true; } echo "Status: Not found. Downloading...\n";
        if (!extension_loaded('zip')) { echo "Error: zip extension required.\n"; return false; } if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) { echo "Error: allow_url_fopen=On required.\n"; return false; } echo "Prerequisite check OK.\n";
        $phpMajor = PHP_MAJOR_VERSION; $phpMinor = PHP_MINOR_VERSION; $phpVersionShort = "{$phpMajor}.{$phpMinor}"; $threadSafety = PHP_ZTS ? 'ts' : 'nts'; $compiler = 'vs17'; $downloadFilename = '';
        if ($isWindows) { $downloadFilename = "{$pluginName}-php{$phpVersionShort}-{$pluginVersion}-win-{$threadSafety}-{$compiler}-{$arch}{$targetFileExt}"; } elseif ($isMac) { $downloadFilename = "{$pluginName}-php{$phpVersionShort}-{$pluginVersion}-macos-{$arch}{$targetFileExt}"; }
        //$downloadUrl = DOWNLOAD_URL_BASE . $pluginVersion . '/' . $downloadFilename; echo "Download URL: {$downloadUrl}\n";
        $downloadUrl = DOWNLOAD_URL_BASE . '0.0.10' . '/' . $downloadFilename; echo "Download URL: {$downloadUrl}\n";
        $tempDir = sys_get_temp_dir(); $tempZipFile = @tempnam($tempDir, 'php_ext_zip_'); if ($tempZipFile === false) { echo "Error: Failed to create temp file.\n"; return false; } @unlink($tempZipFile); echo "Temp download path: {$tempZipFile}\n";
        echo "Downloading .zip archive...\n"; $downloadSuccess = false; $copyError = null; $context = stream_context_create(['http' => ['timeout' => 60, 'follow_location' => 1, 'max_redirects' => 5]]);
        set_error_handler(function($errno, $errstr) use (&$copyError) { $copyError = $errstr; return true; });
        if (@copy($downloadUrl, $tempZipFile, $context)) { if (file_exists($tempZipFile) && filesize($tempZipFile) > 1024) $downloadSuccess = true; else echo "Error: Downloaded file empty/small.\n"; } else { echo "Error: Download failed.\n"; $lastError=error_get_last(); $errorMessage=$copyError?:($lastError['message']??'Unknown'); echo " Details: {$errorMessage}\n"; }
        restore_error_handler();
        if (!$downloadSuccess) { if (file_exists($tempZipFile)) @unlink($tempZipFile); return false; } echo "Download successful.\n";
        echo "Extracting zip archive...\n"; $zip = new ZipArchive(); $res = $zip->open($tempZipFile); if ($res !== TRUE) { echo "Error: Failed to open zip. Code: {$res}\n"; @unlink($tempZipFile); return false; }
        $extractPath = rtrim($tempDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . uniqid('php_ext_extract_'); if (!@mkdir($extractPath, 0777, true) && !is_dir($extractPath)) { echo "Error: Failed create extract dir.\n"; $zip->close(); @unlink($tempZipFile); return false; }
        echo "Extracting to: {$extractPath}\n"; if (!$zip->extractTo($extractPath)) { echo "Error: Failed to extract zip.\n"; $zip->close(); @unlink($tempZipFile); cleanupDirectory($extractPath); return false; } $zip->close(); echo "Extraction successful.\n";
        $foundExtensionPath = findFileRecursive($extractPath, $extensionFileName); if ($foundExtensionPath === null) { echo "Error: Did not find {$extensionFileName} in zip.\n"; @unlink($tempZipFile); cleanupDirectory($extractPath); return false; } echo "Found extension file: {$foundExtensionPath}\n";
        echo "Copying extension file to {$extensionDir}...\n"; if (!is_writable($extensionDir)) { echo "Warning: No write permission for {$extensionDir}. Try elevated privileges.\n"; }
        if (@copy($foundExtensionPath, $targetExtensionPath)) { echo "Copy successful: {$targetExtensionPath}\n"; } else { $lastError=error_get_last(); echo "Error: Failed to copy file.\n"; if($lastError) echo " PHP Error: {$lastError['message']}\n"; if(!$isWindows && stripos($lastError['message'],'Permission denied')!==false) echo " Hint: Try 'sudo'.\n"; @unlink($tempZipFile); cleanupDirectory($extractPath); return false; }
        echo "Cleaning up temporary files...\n"; @unlink($tempZipFile); cleanupDirectory($extractPath); echo "Cleanup complete.\n";
        echo "--- Finished checking/installing extension binary/package ---\n";
        return true;
    }
}


/**
 * Recursively deletes a directory and its contents.
 * (Function implementation remains the same)
 */
function cleanupDirectory(string $dirPath): void
{
    if (!is_dir($dirPath)) return;
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
            RecursiveIteratorIterator::CHILD_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($iterator as $file) {
            try { if ($file->isDir()) @rmdir($file->getRealPath()); else @unlink($file->getRealPath()); }
            catch (Exception $e) { echo "Notice: Failed removing temp item: " . $file->getRealPath() . " Error: " . $e->getMessage() . "\n"; }
        }
         @rmdir($dirPath);
    } catch (Exception $e) { @rmdir($dirPath); echo "Notice: Error during cleanup: " . $e->getMessage() . "\n"; }
}

/**
 * Recursively finds a file within a directory.
 * (Function implementation includes previous fix)
 */
function findFileRecursive(string $dirPath, string $filename): ?string
{
    if (!is_dir($dirPath)) { echo "Notice: Search directory invalid: {$dirPath}\n"; return null; }
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
            RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isDir() && $file->getFilename() === $filename) return $file->getPathname();
        }
        return null; // Not found after loop
    } catch (Exception $e) {
        echo "Notice: Error searching '{$dirPath}': " . $e->getMessage() . "\n"; return null;
    }
}


// --- Main Script Execution ---
echo "PHP Extension Setup Script Initializing...\n";
echo "=========================================\n";
echo "Plugin to manage: " . PLUGIN_NAME . " (Target version: " . PLUGIN_VERSION . ")\n";

// --- Prerequisite Checks ---
$osFamily = PHP_OS_FAMILY; $isLinux = ($osFamily === 'Linux'); $isWindows = ($osFamily === 'Windows');
$requiredExtensions = ['openssl']; if (!$isLinux) $requiredExtensions[] = 'zip';
$missingExtensions = []; foreach ($requiredExtensions as $ext) { if (!extension_loaded($ext)) $missingExtensions[] = $ext; }
$iniFilePath = php_ini_loaded_file();
if ($iniFilePath === false && !empty($missingExtensions) && !$isLinux) { echo "Fatal Error: Missing extensions and cannot find php.ini.\n"; exit(1); }
elseif ($iniFilePath === false) { echo "Warning: Could not locate loaded php.ini file.\n"; }
else { echo "Loaded php.ini file: {$iniFilePath}\n"; }

// --- Handle Missing Prerequisites Interactively ---
if (!empty($missingExtensions) && !$isLinux && $iniFilePath) {
    echo "\nWarning: Missing PHP extension(s): " . implode(', ', $missingExtensions) . ".\n";
    echo "Attempt to enable them in php.ini? [Y/n]: "; $handle = fopen("php://stdin", "r"); $line = trim(fgets($handle)); fclose($handle);
    if (strtolower($line) === 'y' || $line === '') {
        if (enableMissingExtensions($missingExtensions, $iniFilePath)) { echo "\nIMPORTANT: php.ini modified. Restart PHP and run this script again.\n"; }
        else { echo "\nError: Failed to modify php.ini. Enable manually and run again.\n"; }
        exit(1);
    } else { echo "Skipping. Fatal Error: Prerequisites not met.\n"; exit(1); }
} elseif (!empty($missingExtensions) && $isLinux) { echo "Fatal Error: Missing extensions: " . implode(', ', $missingExtensions) . "\n"; exit(1); }
echo "Prerequisite checks passed.\n\n";

// --- Step 1: Install/Check Extension Binary/Package ---
// installExtensionBinary now returns bool for success/failure on all platforms
$installSuccess = installExtensionBinary(PLUGIN_NAME, PLUGIN_VERSION);

if ($installSuccess === false) { // Check specifically for false, as Linux might return string on previous attempts
    echo "\nError: The extension setup process failed or was cancelled.\n";
    exit(1);
}

// --- Step 2: Post-Installation Actions ---
if ($isLinux) {
    // Linux success means apt command was run (or user confirmed). Messages shown inside function.
    echo "\n=========================================\n";
    echo "Linux package processing sequence completed.\n";
    echo "Review messages above regarding 'apt install' status.\n";
} else {
    // Windows/macOS success means file copied. Manage php.ini for the target plugin.
    echo "\n";
    if (!$iniFilePath) {
        echo "Warning: Cannot manage php.ini setting (path unknown). Manually ensure extension=" . PLUGIN_NAME . " is active.\n";
    } elseif (!manageIniSetting(PLUGIN_NAME, $iniFilePath)) { // Call the specific function
         echo "\nError: Failed to update php.ini for the main extension (" . PLUGIN_NAME . ").\n";
         exit(1);
    }
    // Final success message
    echo "\n=========================================\n";
    echo "Setup process completed successfully.\n";
    echo "\n★★★ IMPORTANT ★★★\n";
    echo "Restart PHP (Web Server / PHP-FPM / CLI) to activate changes.\n";
}

echo "\n";
exit(0);
