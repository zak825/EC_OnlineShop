// ============================================================
// 全画面共通のJavaScript（footer で毎回読み込まれる）
// ============================================================

/**
 * 数値を「¥1,234」の形にする
 * @param {number} value
 * @returns {string}
 */
function formatYen(value) {
  return '¥' + Number(value).toLocaleString('ja-JP');
}
