<?php
/**
 * テンプレートを表示する
 *
 *   view('purchase', ['product' => $product]);
 *   → templates/purchase.html を表示し、中で $product が使える
 */
function view(string $name, array $vars = []): void
{
    extract($vars);
    include TEMPLATE_DIR . '/' . $name . '.html';
}

/** 評価値（1〜5）を ★★★☆☆ の形にする */
function stars($score): string
{
    $score = max(0, min(5, (int)$score));
    return str_repeat('★', $score) . str_repeat('☆', 5 - $score);
}

/** 日時を「2026/10/15 13:30」の形にする（NULL なら空文字） */
function format_datetime($value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $time = strtotime((string)$value);
    return $time === false ? (string)$value : date('Y/m/d H:i', $time);
}

/** HTMLエスケープ（テンプレートで値を出すときは必ず通す） */
function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


/**
 * 名前を表示用に省略する
 * $limit 文字を超えたら「…」で切り、ホバー（title）で全文を出す
 */
function name_label(string $name, int $limit = 10): string
{
    if (mb_strlen($name, 'UTF-8') <= $limit) {
        return h($name);
    }
    $short = mb_substr($name, 0, $limit, 'UTF-8') . '…';
    return '<span class="name-ellipsis" title="' . h($name) . '">' . h($short) . '</span>';
}