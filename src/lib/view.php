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

/** HTMLエスケープ（テンプレートで値を出すときは必ず通す） */
function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
