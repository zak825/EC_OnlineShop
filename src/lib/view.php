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
