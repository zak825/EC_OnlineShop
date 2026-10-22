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
    /** 商品名などから丸アイコンのHTMLを作る
 * 背景色は名前ごとに決まり、文字色は背景の明るさで白/黒に振る（必ず読める）
 */
function initial_icon(string $name): string
{
    $char = mb_substr($name, 0, 1, 'UTF-8');
    $hash = md5($name);
    $r = hexdec(substr($hash, 0, 2));
    $g = hexdec(substr($hash, 2, 2));
    $b = hexdec(substr($hash, 4, 2));
    $bg  = sprintf('#%02x%02x%02x', $r, $g, $b);
    $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    $fg  = $lum < 0.6 ? '#ffffff' : '#222222';
    return '<span class="initial-icon" aria-hidden="true" style="background:' . $bg . ';color:' . $fg . '">' . h($char) . '</span>';
}

/**
 * 商品の丸アイコンを作る
 * 画像があれば丸い写真、無ければ頭文字の丸アイコンを返す
 */
function product_icon(string $name, ?string $imageUrl = null): string
{
    if ($imageUrl !== null && $imageUrl !== '') {
        return '<img src="' . h($imageUrl) . '" alt="" class="initial-icon initial-icon--photo"'
            . ' style="width:28px;height:28px;border-radius:50%;object-fit:cover">';
    }
    return initial_icon($name);
}