// ============================================================
// メイン画面
//   ① ログアウトボタンを押したときに確認ポップアップを出す
//   ② 検索窓に入力している間、リアルタイムで検索結果を入れ替える
//   ③ ページを開いたとき、検索窓に自動でカーソルを置く
// ============================================================

// ---------- ① ログアウトの確認 ----------
const logoutForm = document.getElementById('logout-form');

if (logoutForm) {
  logoutForm.addEventListener('submit', (event) => {
    // キャンセルを押したら送信しない（メイン画面のまま）
    if (!window.confirm('ログアウトしますか？')) {
      event.preventDefault();
    }
  });
}

// ---------- ② リアルタイム検索 ----------
(function () {
  const form    = document.getElementById('search-form');
  const results = document.getElementById('search-results');
  const keyword = form ? form.querySelector('[name="q"]') : null;

  if (!form || !results || !keyword) {
    return;
  }

  const DELAY_MS = 200;   // 入力が止まってから検索するまでの待ち時間

  let timer      = null;
  let controller = null;    // 前の検索を取り消すためのもの

  // フォームにつながっている入力欄すべて（左の絞り込みパネルの欄も含む）
  const fields  = Array.from(form.elements);
  const inputs  = fields.filter((el) => el.tagName === 'INPUT' && el.type === 'text');
  const selects = fields.filter((el) => el.tagName === 'SELECT');

  // 今のフォームの内容から URL を作る（空の項目は付けない）
  function buildUrl() {
    const params = new URLSearchParams();
    new FormData(form).forEach((value, key) => {
      if (typeof value === 'string' && value.trim() !== '') {
        params.append(key, value.trim());
      }
    });
    const query = params.toString();
    return form.getAttribute('action') + (query ? '?' + query : '');
  }

  // 検索して、結果の部分だけ入れ替える
  function search() {
    clearTimeout(timer);
    if (controller) {
      controller.abort();   // 古い検索の結果で上書きされないようにする
    }
    controller = new AbortController();

    const url = buildUrl();
    results.classList.add('is-loading');

    fetch(url, { credentials: 'same-origin', signal: controller.signal })
      .then((res) => {
        if (!res.ok) {
          throw new Error('HTTP ' + res.status);
        }
        return res.text();
      })
      .then((html) => {
        const doc  = new DOMParser().parseFromString(html, 'text/html');
        const next = doc.getElementById('search-results');
        if (!next) {
          throw new Error('search-results not found');
        }
        results.innerHTML = next.innerHTML;
        history.replaceState(null, '', url);   // URL も今の条件にする（戻る履歴は増やさない）
        results.classList.remove('is-loading');
      })
      .catch((error) => {
        if (error.name === 'AbortError') {
          return;   // 新しい検索が始まっただけ
        }
        window.location.href = url;   // うまくいかないときは、普通のページ移動で検索する
      });
  }

  // 入力が止まってから検索する
  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(search, DELAY_MS);
  }

  // 商品名・出品者名：入力のたびに検索する（日本語の変換中も含む）
  inputs.forEach((input) => {
    input.addEventListener('input', schedule);
    input.addEventListener('compositionend', schedule);   // 変換を確定したときにも念のため
  });

  // ジャンル・出品者評価・商品状態・並び順：選んだらすぐ
  selects.forEach((select) => {
    select.addEventListener('change', search);
  });

  // Enter・検索ボタン：ページを再読み込みせずに、すぐ検索する
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    search();
  });

  // ---------- ③ 自動フォーカス ----------
  // タッチ操作の端末では、キーボードが勝手に出てしまうので行わない
  if (!window.matchMedia('(hover: none)').matches) {
    keyword.focus();
    const end = keyword.value.length;
    keyword.setSelectionRange(end, end);   // すでに文字があるときは末尾にカーソルを置く
  }
})();



// ---------- ④ ヘッダーの高さを CSS に伝える ----------
// 検索窓・絞り込みパネルが「ヘッダーのすぐ下」に貼り付くように、ヘッダーの実際の高さを測る
(function () {
  const header = document.querySelector('header');
  if (!header) {
    return;
  }

  function updateHeaderHeight() {
    document.documentElement.style.setProperty('--header-height', header.offsetHeight + 'px');
  }

  updateHeaderHeight();
  window.addEventListener('resize', updateHeaderHeight);
  window.addEventListener('load', updateHeaderHeight);   // ロゴ画像が読み込まれたあとの高さにも合わせる
})();