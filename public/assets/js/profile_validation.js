// ============================================================
// 会員情報（氏名・郵便番号・住所・電話番号）と、
// パスワード・秘密の質問の回答の文字種チェック
//   使っている画面：register.html / mypage.html / passreset.html
//   サーバー側のチェックは src/lib/validation.php に同じ内容がある。
//   ルールを変えるときは、この2つを一緒に直すこと。
// ============================================================
(function (global) {
  var NAME_MAX    = 50;    // 氏名（文字数）
  var ADDRESS_MAX = 256;   // 住所（文字数）
  var TEL_MAX     = 15;    // 電話番号（桁数）

  // 前後の空白を取り除く（PHP の trim_input() と同じ：半角・全角スペース、NBSP、改行など）
  function trim(value) {
    return String(value == null ? '' : value).replace(/^[\s\u3000\u00a0]+|[\s\u3000\u00a0]+$/g, '');
  }

  // 文字数（絵文字などを 1 文字と数える。PHP の mb_strlen と同じ数え方）
  function charLength(value) {
    return Array.from(value).length;
  }

  // 郵便番号を保存する形式（ハイフンなし7桁）にそろえる。「123-4567」→「1234567」
  function normalizeZip(zip) {
    var m = /^([0-9]{3})-([0-9]{4})$/.exec(zip);
    return m ? m[1] + m[2] : zip;
  }

  // 氏名を保存する形式（語の間は半角スペース1つ）にそろえる。「山田　太郎」「山田  太郎  次郎」→「山田 太郎」「山田 太郎 次郎」
  // 引数は trim() を通した値
  function normalizeName(name) {
    return name.replace(/[\s\u3000\u00a0]+/g, ' ');
  }

  // 4項目をチェックして、すべてのエラーメッセージを配列で返す（問題なければ空配列）
  // 引数は trim() と normalizeName()・normalizeZip() を通した値
  function collectErrors(userName, zipCode, address, tel) {
    var errors = [];

    if (userName === '') {
      errors.push('氏名を入力してください。');
    } else if (charLength(userName) > NAME_MAX) {
      errors.push('氏名は' + NAME_MAX + '文字以内で入力してください。');
    } else if (/[^\p{L}\p{M} ]/u.test(userName)) {
      // 文字（漢字・かな・カナ・英字。「ー」「々」を含む）以外 = 記号・数字は使えない
      errors.push('氏名に記号や数字は使えません。');
    } else if (!/^[\p{L}\p{M}]+( [\p{L}\p{M}]+){1,2}$/u.test(userName)) {
      // 2語（半角スペース1つ）または3語（半角スペース2つ）まで
      errors.push('氏名は2語または3語で、間を半角スペースで区切って入力してください（例：山田 太郎、Tony Shane Miller）。');
    }

    if (zipCode === '') {
      errors.push('郵便番号を入力してください。');
    } else if (!/^[0-9]{7}$/.test(zipCode)) {
      errors.push('郵便番号は半角数字7桁（ハイフンなし、例：1234567）で入力してください。');
    }

    if (address === '') {
      errors.push('住所を入力してください。');
    } else if (charLength(address) > ADDRESS_MAX) {
      errors.push('住所は' + ADDRESS_MAX + '文字以内で入力してください。');
    } else if (!isAllowedZenkakuText(address)) {
      errors.push(zenkakuTextError('住所'));
    }

    if (tel === '') {
      errors.push('電話番号を入力してください。');
    } else if (tel.length > TEL_MAX || !/^[0-9\-]{10,15}$/.test(tel)) {
      errors.push('電話番号は' + TEL_MAX + '桁以内の半角数字（ハイフン可）で入力してください。');
    }

    return errors;
  }

  // 全角文字、半角英数字、半角ハイフン（-）、半角・全角スペースだけか。住所と秘密の質問の回答で使う
  function isAllowedZenkakuText(value) {
    return /^(?:[A-Za-z0-9\- ]|[\u3000-\u303F\u3040-\u309F\u30A0-\u30FF\u3400-\u4DBF\u4E00-\u9FFF\uF900-\uFAFF\uFF01-\uFF60\uFFE0-\uFFE6]|[\u{20000}-\u{3FFFF}])+$/u.test(value);
  }

  function zenkakuTextError(label) {
    return label + 'は全角文字、半角英数字、ハイフン（-）、スペースのみ使用できます。';
  }

  // パスワードの禁止文字（-!"#$%&()*,./:;?[]^_`{}~+<=> と半角・全角スペース。@ は使える）
  function passwordHasForbiddenChar(value) {
    return /[\[\]!"#$%&()*,./:;?^_`{}~+<=> \u3000-]/.test(value);
  }

  function passwordForbiddenCharError() {
    return 'パスワードに半角スペース、全角スペース、および次の記号は使用できません。-!"#$%&()*,./:;?[]^_`{}~+<=>';
  }

  // 4項目をチェックして、エラーメッセージを返す（問題なければ null）
  // 引数は trim() と normalizeName()・normalizeZip() を通した値
  function validate(userName, zipCode, address, tel) {
    if (userName === '' || zipCode === '' || address === '' || tel === '') {
      return 'すべての項目を入力してください。';
    }
    var errors = collectErrors(userName, zipCode, address, tel);
    return errors.length > 0 ? errors[0] : null;
  }

  global.ProfileValidation = {
    trim: trim,
    charLength: charLength,
    normalizeName: normalizeName,
    normalizeZip: normalizeZip,
    collectErrors: collectErrors,
    validate: validate,
    isAllowedZenkakuText: isAllowedZenkakuText,
    zenkakuTextError: zenkakuTextError,
    passwordHasForbiddenChar: passwordHasForbiddenChar,
    passwordForbiddenCharError: passwordForbiddenCharError
  };
})(window);
