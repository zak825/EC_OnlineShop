<?php
// ============================================================
// 管理画面：システム設定の変更（確認・実行）
//   GET  system_setting_confirm.php  … system_setting.php で入力した内容を、変更前と並べて表示する
//   POST system_setting_confirm.php  … 再チェックしてから UPDATE する（完了後は system_setting.php へ）
//   キャンセルは system_setting.php?action=back（入力値を残して入力画面へ戻る）
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';

require_admin('system_setting.php');

// 変更したときに確認画面で出す注意書き（Key => 文言）
const SETTING_CHANGE_NOTES = [
    'PasswordMinLength'    => '登録済みの利用者は、次にログインしたときパスワードの再設定を求められます。再設定が終わるまでショップには入れません。',
    'PasswordMaxLength'    => '登録済みのパスワードには影響しません。新規登録とパスワード再設定から適用されます。',
    'SessionTimeout'       => '短くした場合、ログイン中の利用者（あなたを含む）が次の操作でログアウトされることがあります。',
    'PriceMin'             => '出品中の商品の価格は変わりません。これから出品する商品から適用されます。',
    'PriceMax'             => '出品中の商品の価格は変わりません。これから出品する商品から適用されます。',
    'ProductNameMaxLength' => '出品中の商品名は変わりません。これから出品する商品から適用されます。',
    'DescriptionMaxLength' => '短くした場合、それより長い説明文の商品は、編集時に説明文を短くする必要があります。',
];

$edit = $_SESSION['setting_edit'] ?? [];
if (empty($edit['values']) || !isset($edit['original'])) {
    redirect('system_setting.php');
}

$pdo = db();
$errorMessage = '';

try {
    $rows = fetch_system_settings($pdo);
} catch (PDOException $e) {
    error_log('System setting fetch error: ' . $e->getMessage());
    $rows = [];
    $errorMessage = '設定の取得に失敗しました。時間をおいて再度お試しください。';
}

$labels = array_column($rows, 'Label', 'Key');
$values = array_intersect_key($edit['values'], $labels);

// 【実行ボタンを押す】
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rows !== []) {
    if (!check_admin_csrf_token()) {
        $errorMessage = '不正なリクエストです（CSRFトークンエラー）。画面を開き直して再度お試しください。';
    } elseif (collect_setting_errors($values, $labels) !== []) {
        set_admin_flash('入力内容に誤りがあります。各項目のメッセージを確認してください。', 'error');
        redirect('system_setting.php?action=back');
    } else {
        $result = '';
        try {
            $pdo->beginTransaction();

            // 同時編集チェック：画面を開いた後に他の管理者が変更していないか、行をロックして確認する
            $stmt = $pdo->prepare(
                'SELECT id, [Key], [Value]
                 FROM dbo.SystemConfiguration WITH (UPDLOCK, HOLDLOCK)'
            );
            $stmt->execute();
            $lockedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $updates  = [];
            $minLengthChanged = false;
            $conflict = count($lockedRows) !== count($edit['original']);
            foreach ($lockedRows as $row) {
                $key = (string)$row['Key'];
                if (!array_key_exists($key, $edit['original'])
                    || (string)$row['Value'] !== (string)$edit['original'][$key]) {
                    $conflict = true;
                    break;
                }
                if (isset($values[$key]) && $values[$key] !== (string)$row['Value']) {
                    $updates[(int)$row['id']] = $values[$key];
                    if ($key === 'PasswordMinLength') {
                        $minLengthChanged = true;
                    }
                }
            }

            if ($conflict) {
                $pdo->rollBack();
                $result = 'conflict';
            } else {
                // SQLインジェクション対策（プレースホルダ）
                $stmt = $pdo->prepare(
                    'UPDATE dbo.SystemConfiguration
                     SET [Value] = :value
                     WHERE id = :id'
                );
                foreach ($updates as $id => $value) {
                    $stmt->bindValue(':value', $value, PDO::PARAM_STR);
                    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                    $stmt->execute();
                }
                // 最小文字数を変えたら、登録済みの利用者は次のログインで再設定するまで入れない
                if ($minLengthChanged) {
                    $pdo->exec('UPDATE dbo.UserData SET PasswordResetRequired = 1');
                }
                $pdo->commit();
                $result = 'done';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('System setting update error: ' . $e->getMessage());
            $errorMessage = '設定の更新に失敗しました。時間をおいて再度お試しください。';
        }

        if ($result === 'conflict') {
            // 入力値は残し、変更前の値は入力画面で取り直す
            unset($_SESSION['setting_edit']['original']);
            set_admin_flash('他の管理者が設定を変更したため、実行を中止しました。最新の設定値を確認してから、もう一度お試しください。', 'error');
            redirect('system_setting.php?action=back');
        }
        if ($result === 'done') {
            unset($_SESSION['setting_edit']);
            set_admin_flash('設定を変更しました。', 'success');
            redirect('system_setting.php');
        }
    }
}

// 変更前・変更後の一覧と、変更した項目の注意書き
$items = [];
$notes = [];
foreach ($rows as $row) {
    $key    = $row['Key'];
    $before = (string)($edit['original'][$key] ?? $row['Value']);
    $after  = (string)($values[$key] ?? $before);
    $changed = $before !== $after;
    $items[] = [
        'label'   => $row['Label'],
        'before'  => $before,
        'after'   => $after,
        'changed' => $changed,
    ];
    if ($changed && isset(SETTING_CHANGE_NOTES[$key])) {
        $notes[] = $row['Label'] . '：' . SETTING_CHANGE_NOTES[$key];
    }
}

view('system_setting_confirm', [
    'token'        => admin_csrf_token(),
    'errorMessage' => $errorMessage,
    'items'        => $items,
    'notes'        => $notes,
]);
