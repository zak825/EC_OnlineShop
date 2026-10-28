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

// 説明欄は縦方向だけリサイズでき、広げたときは入力欄を強調する。
document.querySelectorAll('textarea[data-resizable-description]').forEach(function(textarea) {
  var initialHeight = textarea.getBoundingClientRect().height;
  var updateResizeStyle = function() {
    textarea.classList.toggle(
      'is-expanded',
      textarea.getBoundingClientRect().height > initialHeight + 4
    );
  };
  if (typeof ResizeObserver === 'function') {
    new ResizeObserver(updateResizeStyle).observe(textarea);
  }
  updateResizeStyle();
});
