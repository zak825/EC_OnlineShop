<?php
// ============================================================
// 管理画面：システム設定の変更（入力）
//   GET  system_setting.php                … DB の現在の設定値を表示する
//   GET  system_setting.php?action=back    … 確認画面のキャンセルから戻る（入力値を残す）
//   GET  system_setting.php?action=cancel  … 変更内容を破棄して管理メニューへ
//   POST system_setting.php                … 入力をチェックして確認画面へ
//
// セッション $_SESSION['setting_edit']
//   original … 画面を開いた時点の DB の値（確認画面の「変更前」と、実行時の同時編集チェックに使う）
//   values   … 入力値（確認画面へ渡す）
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';

require_admin('system_setting.php');

$action = $_GET['action'] ?? '';

// 【キャンセルボタンを押す】変更内容を破棄して管理メニューへ戻る
if ($action === 'cancel') {
    unset($_SESSION['setting_edit']);
    redirect('setting.php');
}

$pdo = db();
$errorMessage = '';
$fieldErrors  = [];

try {
    $rows = fetch_system_settings($pdo);
} catch (PDOException $e) {
    error_log('System setting fetch error: ' . $e->getMessage());
    $rows = [];
    $errorMessage = '設定の取得に失敗しました。時間をおいて再度お試しください。';
}

$current = array_column($rows, 'Value', 'Key');
$labels  = array_column($rows, 'Label', 'Key');
$edit    = $_SESSION['setting_edit'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 【変更内容を確認ボタンを押す】DB にある Key だけ受け取る
    $posted = $_POST['settings'] ?? [];
    $inputs = [];
    foreach ($current as $key => $value) {
        $inputs[$key] = trim_input(is_array($posted) ? ($posted[$key] ?? '') : '');
    }

    if (!check_admin_csrf_token()) {
        $errorMessage = '不正なリクエストです（CSRFトークンエラー）。画面を開き直して再度お試しください。';
    } elseif ($rows !== []) {
        $fieldErrors = collect_setting_errors($inputs, $labels);

        if ($fieldErrors !== []) {
            $errorMessage = '入力内容に誤りがあります。各項目のメッセージを確認してください。';
        } else {
            $inputs   = array_map('normalize_setting_value', $inputs);
            $original = $edit['original'] ?? $current;

            if (array_diff_assoc($inputs, $original) === []) {
                $errorMessage = '変更された項目がありません。';
            } else {
                $_SESSION['setting_edit'] = [
                    'original' => $original,
                    'values'   => $inputs,
                ];
                redirect('system_setting_confirm.php');
            }
        }
    }
} elseif ($action === 'back' && isset($edit['values'])) {
    // 【確認画面のキャンセルから戻る】入力値を残す
    $inputs = array_merge($current, array_intersect_key($edit['values'], $current));
    $_SESSION['setting_edit'] = [
        'original' => $edit['original'] ?? $current,
        'values'   => $edit['values'],
    ];
} else {
    // 【画面を開く】DB の現在の値を表示する
    $inputs = $current;
    $_SESSION['setting_edit'] = ['original' => $current];
}

// 入力欄ごとの表示データ（上限・下限と大小関係の相手は setting.js のチェックに使う）
$settings = [];
foreach ($rows as $row) {
    $key     = $row['Key'];
    $limit   = SETTING_LIMITS[$key] ?? null;
    $pairMax = SETTING_RANGE_PAIRS[$key] ?? '';
    $settings[] = [
        'key'     => $key,
        'label'   => $row['Label'],
        'initial' => $row['Value'],
        'input'   => $inputs[$key] ?? $row['Value'],
        'min'     => $limit[0] ?? null,
        'max'     => $limit[1] ?? null,
        'pairMax' => isset($current[$pairMax]) ? $pairMax : '',
        'error'   => $fieldErrors[$key] ?? '',
    ];
}

view('system_setting', [
    'flash'        => get_admin_flash(),
    'token'        => admin_csrf_token(),
    'errorMessage' => $errorMessage,
    'settings'     => $settings,
]);
