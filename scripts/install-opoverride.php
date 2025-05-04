<?php

// --- 設定 ---
define('PLUGIN_NAME', 'rindow_opoverride');
define('PLUGIN_VERSION', '0.1.0');
define('DOWNLOAD_URL_BASE', 'https://github.com/yuichiis/rindow-opoverride/releases/download/');
// ファイル名/URLパターンは installExtensionBinary 内でOSに応じて決定
// ----------------

/**
 * php.ini ファイル内の指定された拡張機能設定を管理する関数
 * (変更なし - 前のコードと同じ)
 * Linuxでは通常呼び出されない想定
 */
function manageIniSetting(string $pluginName, string $iniFilePath): bool
{
    // ... (関数の中身は前のコードと同じなので省略) ...
    echo "--- php.ini設定管理を開始: extension={$pluginName} ---\n";
    echo "対象ファイル: {$iniFilePath}\n";

    $ini_content_raw = @file_get_contents($iniFilePath);
    if ($ini_content_raw === false) {
        echo "エラー: php.iniファイルの読み込みに失敗しました。\n";
        return false;
    }
    $ini_content = str_replace(["\r\n", "\r"], "\n", $ini_content_raw);
    $pluginNameQuoted = preg_quote($pluginName, '@');
    $pattern_active = "@^\s*extension\s*=\s*{$pluginNameQuoted}\s*(;.*)?$@im";
    $pattern_commented = "@^(\s*;+\s*)(extension\s*=\s*{$pluginNameQuoted}\s*(;.*)?)$@im";

    $modified = false;
    $new_content = $ini_content;

    if (preg_match($pattern_active, $ini_content)) {
        echo "状態: 有効な 'extension={$pluginName}' 設定が既に存在します。変更は不要です。\n";
    } elseif (preg_match($pattern_commented, $ini_content, $matches, PREG_OFFSET_CAPTURE)) {
        echo "状態: コメントアウトされた 'extension={$pluginName}' 設定が見つかりました。コメントを解除します。\n";
        $new_content = preg_replace($pattern_commented, '$2', $ini_content, 1);
        $modified = true;
        echo "変更後プレビュー (置換部分周辺):\n";
        $match_offset = $matches[0][1];
        $line_num = substr_count(substr($ini_content, 0, $match_offset), "\n") + 1;
        $preview_lines = array_slice(explode("\n", $new_content), max(0, $line_num - 3), 5);
        echo "...\n" . implode("\n", $preview_lines) . "\n...\n";
    } else {
        echo "状態: 'extension={$pluginName}' の設定が見つかりません。ファイルの末尾に追加します。\n";
        if (substr($new_content, -1) !== "\n") {
            $new_content .= "\n";
        }
        $new_content .= "extension={$pluginName}\n";
        $modified = true;
        echo "ファイル末尾に追加される行: extension={$pluginName}\n";
    }

    if ($modified) {
        echo "php.iniファイルに変更を書き込みます...\n";
        if (!is_writable($iniFilePath)) {
            $osFamily = PHP_OS_FAMILY;
            $isWindows = ($osFamily === 'Windows');
            echo "警告: php.iniファイルへの書き込み権限がありません。\n";
            if (!$isWindows) {
                 echo "   コマンドの先頭に 'sudo ' を付けて管理者権限でスクリプトを実行してみてください。\n";
                 echo "   例: sudo php " . basename(__FILE__) . "\n";
            } else {
                 echo "   管理者としてコマンドプロンプトやPowerShellを実行して、再度スクリプトを実行してください。\n";
            }
            return false;
        }

        echo "書き込み用に改行コードを PHP_EOL ('" . str_replace(["\r", "\n"], ['\\r', '\\n'], PHP_EOL) . "') に変換します。\n";
        $output_content = str_replace("\n", PHP_EOL, $new_content);
        if (@file_put_contents($iniFilePath, $output_content) !== false) {
            echo "成功: php.iniファイルが更新されました。\n";
        } else {
            echo "エラー: php.iniファイルへの書き込みに失敗しました。\n";
            return false;
        }
    } else {
        echo "php.iniファイルに変更はありませんでした。\n";
    }

    echo "--- php.ini設定管理を終了 ---\n";
    return true;
}


/**
 * 拡張機能バイナリ/パッケージをダウンロードし、インストール準備または実行する関数 (OS対応版)
 * Linuxの場合は .deb をダウンロードし、apt コマンドの実行を促す。
 *
 * @param string $pluginName 拡張機能名
 * @param string $pluginVersion ダウンロードする拡張機能のバージョン
 * @return bool|string Linuxの場合はダウンロードしたdebファイルのパス、それ以外は成功/失敗(bool)、エラー時はfalse
 */
function installExtensionBinary(
    string $pluginName,
    string $pluginVersion
)/*: bool|string*/ { // 戻り値の型宣言はPHP 8.0以降推奨
    echo "--- 拡張機能バイナリ/パッケージのインストールチェックを開始: {$pluginName} ---\n";

    // --- OSとアーキテクチャの判定 ---
    $osFamily = PHP_OS_FAMILY;
    $isWindows = ($osFamily === 'Windows');
    $isMac = ($osFamily === 'Darwin');
    $isLinux = ($osFamily === 'Linux');

    if (!$isWindows && !$isMac && !$isLinux) {
        echo "エラー: このOSファミリー ({$osFamily}) は現在サポートされていません。\n";
        return false;
    }

    $machineArch = php_uname('m');
    $arch = ''; // ダウンロードURL/ファイル名用
    $phpArch = (PHP_INT_SIZE === 8) ? '64' : '32'; // PHP自体のビット数
    $extensionFileName = ''; // .dll, .so
    $targetFileExt = ''; // .zip, .deb

    if ($isWindows) {
        $arch = ($phpArch === '64') ? 'x64' : 'x86';
        $extensionFileName = 'php_' . $pluginName . '.dll';
        $targetFileExt = '.zip';
    } elseif ($isMac) {
        if ($machineArch === 'arm64') $arch = 'arm64';
        elseif ($machineArch === 'x86_64') $arch = 'x64';
        else {
             echo "エラー: 未サポートのmacOSアーキテクチャです: {$machineArch}\n";
             return false;
        }
        $extensionFileName = $pluginName . '.so';
        $targetFileExt = '.zip';
    } elseif ($isLinux) {
        // Debian/Ubuntu系を想定し、アーキテクチャ名をdebパッケージ形式に合わせる
        if ($machineArch === 'x86_64') $arch = 'amd64';
        elseif ($machineArch === 'aarch64') $arch = 'arm64';
        else {
            echo "エラー: このLinuxアーキテクチャ ({$machineArch}) は現在サポートされていません (amd64, arm64のみ)。\n";
            return false;
        }
        // Linuxでは拡張ファイル名の直接チェックはしない (debパッケージ管理)
        $extensionFileName = null; // 使用しない
        $targetFileExt = '.deb';
        echo "Linux環境 (Debian/Ubuntu系を想定) を検出しました。\n";
    }
    echo "OSファミリー: {$osFamily}, アーキテクチャ: {$arch} ({$machineArch}), PHPビット数: {$phpArch}bit\n";
    if ($extensionFileName) echo "拡張ファイル名: {$extensionFileName}\n";

    // --- Linux (.deb) の場合の処理 ---
    if ($isLinux) {
        // Linuxでは、パッケージがインストール済みかどうかのチェックは複雑なため、
        // ここでは常にダウンロードを試み、ユーザーにインストールコマンドを提示する方針とします。

        // 4. 必要なPHP設定をチェック (allow_url_fopenのみ)
        $allowUrlFopen = filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN);
        if (!$allowUrlFopen) {
            echo "エラー: ファイルをダウンロードできません。php.iniで 'allow_url_fopen = On' に設定してください。\n";
            return false;
        }
        echo "前提条件チェックOK: 'allow_url_fopen' 有効\n";

        // 5. ダウンロードURLを構築 (Linux .deb 用)
        $phpMajor = PHP_MAJOR_VERSION;
        $phpMinor = PHP_MINOR_VERSION;
        $phpVersionShort = "{$phpMajor}.{$phpMinor}"; // 例: 8.4

        // ファイル名の形式: rindow-opoverride-php8.4_0.1.0_amd64.deb
        $downloadFilename = "{$pluginName}-php{$phpVersionShort}_{$pluginVersion}_{$arch}{$targetFileExt}";
        $downloadUrl = DOWNLOAD_URL_BASE . $pluginVersion . '/' . $downloadFilename;
        echo "決定されたダウンロードURL: {$downloadUrl}\n";

        // 6. 一時ファイルパスを準備
        $tempDir = sys_get_temp_dir();
        $tempDebFile = rtrim($tempDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $downloadFilename; // 拡張子付きで一時ファイル名を作成
        echo "一時DEBファイルパス: {$tempDebFile}\n";
        // 既存の一時ファイルがあれば上書きされる

        // 7. DEBファイルをダウンロード (copy()を使用)
        echo "DEBファイルを copy() を使ってダウンロード中...\n";
        $downloadSuccess = false;
        $copyError = null;
        $contextOptions = ['http' => ['timeout' => 60, 'follow_location' => 1, 'max_redirects' => 5]]; // タイムアウトを少し長めに
        $context = stream_context_create($contextOptions);

        set_error_handler(function($errno, $errstr) use (&$copyError) { $copyError = $errstr; return true; });
        if (@copy($downloadUrl, $tempDebFile, $context)) {
            restore_error_handler();
            if (file_exists($tempDebFile) && filesize($tempDebFile) > 1024) { // debファイルなのである程度のサイズを期待
                $downloadSuccess = true;
            } else {
                echo "エラー: copy() は成功しましたが、ダウンロードされたファイルが空か小さすぎます。\n";
                if (file_exists($tempDebFile)) @unlink($tempDebFile);
            }
        } else {
            restore_error_handler();
            $lastError = error_get_last();
            echo "エラー: copy() を使ったダウンロードに失敗しました。\n";
            $errorMessage = $copyError ?: ($lastError['message'] ?? '不明なエラー');
            echo "   エラー情報: " . $errorMessage . "\n";
             if (stripos($errorMessage, 'SSL') !== false || stripos($errorMessage, 'certificate') !== false) echo "   ヒント: SSL証明書の問題かもしれません。\n";
             elseif (stripos($errorMessage, 'timed out') !== false || stripos($errorMessage, 'timeout') !== false) echo "   ヒント: 接続がタイムアウトした可能性があります。\n";
             elseif (stripos($errorMessage, '404 Not Found') !== false) echo "   ヒント: ダウンロードURLが見つかりません (404)。URL、バージョン、OS、アーキテクチャを確認してください: {$downloadUrl}\n";
             elseif (stripos($errorMessage, 'Permission denied') !== false) echo "   ヒント: 一時ファイル ({$tempDebFile}) への書き込み権限がない可能性があります。\n";
        }

        if (!$downloadSuccess) {
            if (file_exists($tempDebFile)) @unlink($tempDebFile); // 失敗したら一時ファイルを削除
            return false;
        }
        echo "ダウンロード成功: {$tempDebFile}\n";

        // 8. ユーザーにインストールコマンドを提示
        echo "\n--- DEBパッケージのインストール手順 ---\n";
        echo "以下のコマンドをターミナルで実行して、ダウンロードしたパッケージをインストールしてください。\n";
        echo "注意: このコマンドの実行には管理者権限 (sudo) が必要です。\n\n";
        // update は必須ではないかもしれないが、依存関係解決のために推奨される場合がある
        echo "sudo apt update\n";
        echo "sudo apt install -y {$tempDebFile}\n\n";
        echo "インストール後、WebサーバーまたはPHP-FPMを再起動する必要がある場合があります。\n";
        echo "一時ファイル {$tempDebFile} はインストール後に手動で削除しても構いません。\n";
        echo "--------------------------------------\n";

        echo "--- 拡張機能バイナリ/パッケージのインストールチェックを終了 ---\n";
        // Linuxの場合は、ダウンロードしたdebファイルのパスを返す（成功の印として）
        return $tempDebFile;

    // --- Windows/macOS (.zip) の場合の処理 ---
    } else {
        // 1. extension_dir を取得 & チェック
        $extensionDir = ini_get('extension_dir');
        $realExtensionDir = realpath($extensionDir);
        if ($realExtensionDir === false || !is_dir($realExtensionDir)) {
            echo "エラー: php.iniで指定された extension_dir が見つからないか、ディレクトリではありません: {$extensionDir}\n";
            return false;
        }
        $extensionDir = $realExtensionDir;
        echo "Extensionディレクトリ: {$extensionDir}\n";

        // 2. ターゲットファイルパスを作成
        $targetExtensionPath = rtrim($extensionDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $extensionFileName;
        echo "ターゲットファイルパス: {$targetExtensionPath}\n";

        // 3. ファイルが存在するかチェック
        if (file_exists($targetExtensionPath)) {
            echo "状態: 拡張機能ファイル ({$extensionFileName}) は既に {$extensionDir} に存在します。\n";
            echo "--- 拡張機能バイナリ/パッケージのインストールチェックを終了 ---\n";
            return true; // 既に存在する場合は成功
        }
        echo "状態: 拡張機能ファイル ({$extensionFileName}) が見つかりません。ダウンロードとインストールを試みます。\n";

        // 4. 必要なPHP拡張/設定をチェック (zipとallow_url_fopen)
        if (!extension_loaded('zip')) { echo "エラー: 'zip' 拡張が必要です。\n"; return false; }
        $allowUrlFopen = filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN);
        if (!$allowUrlFopen) { echo "エラー: php.iniで 'allow_url_fopen = On' に設定してください。\n"; return false; }
        echo "前提条件チェックOK: 'zip' 拡張有効, 'allow_url_fopen' 有効\n";

        // 5. ダウンロードURLを構築 (Windows/macOS .zip 用)
        $phpMajor = PHP_MAJOR_VERSION;
        $phpMinor = PHP_MINOR_VERSION;
        $phpVersionShort = "{$phpMajor}.{$phpMinor}";
        $threadSafety = PHP_ZTS ? 'ts' : 'nts';
        $compiler = 'vs17'; // Windowsのみで使用

        $downloadFilename = '';
        if ($isWindows) {
            $downloadFilename = "{$pluginName}-php{$phpVersionShort}-{$pluginVersion}-win-{$threadSafety}-{$compiler}-{$arch}{$targetFileExt}";
        } elseif ($isMac) {
            $downloadFilename = "{$pluginName}-php{$phpVersionShort}-{$pluginVersion}-macos-{$arch}{$targetFileExt}";
        }
        $downloadUrl = DOWNLOAD_URL_BASE . $pluginVersion . '/' . $downloadFilename;
        echo "決定されたダウンロードURL: {$downloadUrl}\n";

        // 6. 一時ファイルパスを準備
        $tempDir = sys_get_temp_dir();
        $tempZipFile = @tempnam($tempDir, 'php_ext_zip_');
        if ($tempZipFile === false) { echo "エラー: 一時ファイルの作成に失敗。\n"; return false; }
        @unlink($tempZipFile);
        echo "一時ZIPファイルパス: {$tempZipFile}\n";

        // 7. ZIPファイルをダウンロード (copy()を使用)
        echo "ZIPファイルを copy() を使ってダウンロード中...\n";
        $downloadSuccess = false;
        $copyError = null;
        $contextOptions = ['http' => ['timeout' => 60, 'follow_location' => 1, 'max_redirects' => 5]];
        $context = stream_context_create($contextOptions);

        set_error_handler(function($errno, $errstr) use (&$copyError) { $copyError = $errstr; return true; });
        if (@copy($downloadUrl, $tempZipFile, $context)) {
            restore_error_handler();
            if (file_exists($tempZipFile) && filesize($tempZipFile) > 1024) { // Zipもある程度のサイズを期待
                $downloadSuccess = true;
            } else {
                echo "エラー: copy() は成功しましたが、ダウンロードされたファイルが空か小さすぎます。\n";
                if (file_exists($tempZipFile)) @unlink($tempZipFile);
            }
        } else {
            restore_error_handler();
            $lastError = error_get_last();
            echo "エラー: copy() を使ったダウンロードに失敗しました。\n";
            $errorMessage = $copyError ?: ($lastError['message'] ?? '不明なエラー');
            echo "   エラー情報: " . $errorMessage . "\n";
            if (stripos($errorMessage, 'SSL') !== false) echo "   ヒント: SSL証明書の問題かもしれません。\n";
            elseif (stripos($errorMessage, 'timed out') !== false) echo "   ヒント: 接続がタイムアウトした可能性があります。\n";
            elseif (stripos($errorMessage, '404 Not Found') !== false) echo "   ヒント: ダウンロードURLが見つかりません (404)。URL、バージョン、OS、アーキテクチャを確認してください: {$downloadUrl}\n";
            elseif (stripos($errorMessage, 'Permission denied') !== false) echo "   ヒント: 一時ファイル ({$tempZipFile}) への書き込み権限がない可能性があります。\n";
        }

        if (!$downloadSuccess) {
            if (file_exists($tempZipFile)) @unlink($tempZipFile);
            return false;
        }
        echo "ダウンロード成功。\n";

        // 8. ZIPファイルを解凍
        echo "ZIPファイルを解凍中...\n";
        $zip = new ZipArchive();
        $res = $zip->open($tempZipFile);
        if ($res !== TRUE) { echo "エラー: ZIPファイルを開けません。\n"; @unlink($tempZipFile); return false; }
        $extractPath = rtrim($tempDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . uniqid('php_ext_extract_');
        if (!@mkdir($extractPath, 0777, true) && !is_dir($extractPath)) { echo "エラー: 解凍用一時ディレクトリ作成失敗。\n"; $zip->close(); @unlink($tempZipFile); return false; }
        echo "解凍先一時ディレクトリ: {$extractPath}\n";
        if (!$zip->extractTo($extractPath)) { echo "エラー: ZIPファイルの解凍失敗。\n"; $zip->close(); @unlink($tempZipFile); cleanupDirectory($extractPath); return false; }
        $zip->close();
        echo "解凍成功。\n";

        // 9. 解凍されたファイルの中から目的の拡張ファイルを探す
        $foundExtensionPath = findFileRecursive($extractPath, $extensionFileName);
        if ($foundExtensionPath === null) { echo "エラー: 解凍後、目的のファイル ({$extensionFileName}) 発見失敗。\n"; @unlink($tempZipFile); cleanupDirectory($extractPath); return false; }
        echo "目的のファイルを発見: {$foundExtensionPath}\n";

        // 10. 拡張ファイルを extension_dir にコピー
        echo "拡張ファイルを {$extensionDir} にコピー中...\n";
        if (!is_writable($extensionDir)) {
            echo "警告: Extensionディレクトリ ({$extensionDir}) への書き込み権限がありません。\n";
            if (!$isWindows) echo "   コマンドの先頭に 'sudo ' を付けて実行してください。\n";
            else echo "   管理者としてコマンドプロンプト等を実行してください。\n";
        }
        if (@copy($foundExtensionPath, $targetExtensionPath)) {
            echo "コピー成功: {$targetExtensionPath}\n";
        } else {
            $lastError = error_get_last();
            echo "エラー: 拡張機能ファイルのコピーに失敗しました。\n";
            if ($lastError) echo "   PHPエラー: " . $lastError['message'] . "\n";
            if (!$isWindows && (stripos($lastError['message'], 'Permission denied') !== false || stripos($lastError['message'], 'Operation not permitted') !== false)) {
                 echo "   ヒント: 書き込み権限不足の可能性があります。'sudo' を付けて実行してください。\n";
            }
            @unlink($tempZipFile); cleanupDirectory($extractPath); return false;
        }

        // 11. 一時ファイルとディレクトリを削除
        echo "一時ファイルをクリーンアップ中...\n";
        @unlink($tempZipFile);
        cleanupDirectory($extractPath);
        echo "クリーンアップ完了。\n";

        echo "--- 拡張機能バイナリ/パッケージのインストールチェックを終了 ---\n";
        return true; // Windows/macOS は成功したら true を返す
    }
}

/**
 * 指定されたディレクトリを再帰的に削除するヘルパー関数
 * (変更なし - 前のコードと同じ)
 */
function cleanupDirectory(string $dirPath): void { /* ... 省略 ... */ }

/**
 * 指定されたディレクトリ内でファイルを再帰的に検索するヘルパー関数
 * (変更なし - 前のコードと同じ)
 */
function findFileRecursive(string $dirPath, string $filename): ?string { /* ... 省略 ... */ }


// --- メインスクリプト実行 ---
echo "PHP拡張機能セットアップスクリプト開始\n";
echo "=====================================\n";
echo "管理対象プラグイン: " . PLUGIN_NAME . " (バージョン: " . PLUGIN_VERSION . ")\n";

// 前提条件チェック (zip, openssl, allow_url_fopen)
// Linuxの場合は zip は不要だが、共通チェックとして残しておく
$osFamily = PHP_OS_FAMILY;
$isLinux = ($osFamily === 'Linux');
$requiredExtensions = ['openssl'];
if (!$isLinux) { // Windows/Mac のみ zip が必要
    $requiredExtensions[] = 'zip';
}
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        echo "致命的エラー: 必要なPHP拡張 '{$ext}' が有効になっていません。\n";
        echo "php.ini で有効にしてください。\n";
        exit(1);
    }
}
if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
    echo "致命的エラー: ファイルダウンロードに必要な 'allow_url_fopen' が有効になっていません。\n";
    echo "php.ini で 'allow_url_fopen = On' に設定してください。\n";
    exit(1);
}
echo "前提条件チェックOK: " . implode(', ', $requiredExtensions) . ", allow_url_fopen\n";


// php.iniファイルのパスを取得 (Linuxでも参照する可能性はある)
$iniFilePath = php_ini_loaded_file();
if ($iniFilePath === false) {
    // iniファイルが見つからなくてもLinuxなら続行可能かもしれないが、一旦エラーとする
    echo "致命的エラー: php.iniファイルが見つかりません。処理を続行できません。\n";
    exit(1);
}
echo "使用するphp.ini: {$iniFilePath}\n\n";


// ステップ1: 拡張機能バイナリ/パッケージのインストール/確認
$installResult = installExtensionBinary(PLUGIN_NAME, PLUGIN_VERSION);

if ($installResult === false) {
    echo "\nエラー: 拡張機能バイナリ/パッケージの処理に失敗しました。\n";
    exit(1);
}

// ステップ2: OSに応じた後処理
if ($isLinux) {
    // Linuxの場合、installExtensionBinaryは成功時にdebファイルのパスを返す
    // ユーザーにapt installを促すメッセージは installExtensionBinary 内で表示済み
    echo "\n=====================================\n";
    echo "Linux用のDEBパッケージのダウンロードが完了しました。\n";
    echo "表示された 'sudo apt install' コマンドを実行してインストールを完了してください。\n";

} else {
    // Windows / macOS の場合
    echo "\n"; // 区切り

    // ステップ2.1: php.ini設定の管理
    if (!manageIniSetting(PLUGIN_NAME, $iniFilePath)) {
         echo "\nエラー: php.iniの更新に失敗しました。\n";
         exit(1);
    }

    echo "\n=====================================\n";
    echo "セットアップ処理が正常に完了しました。\n";
    echo "\n";
    echo "★★★ 重要 ★★★\n";
    echo "変更を有効にするには、以下のいずれかの操作が必要です:\n";
    echo " - Webサーバー (Apache, Nginx など) を再起動する\n";
    echo " - PHP-FPM サービスを再起動する\n";
    echo " - コマンドライン (CLI) で使用する場合は、このターミナルを閉じて新しいターミナルを開くか、`php -v` や `php -m` で拡張機能がロードされているか確認する\n";
}

echo "\n";
exit(0); // 正常終了
