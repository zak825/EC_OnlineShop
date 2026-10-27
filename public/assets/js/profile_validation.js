// ============================================================
// 会員情報（氏名・郵便番号・住所・電話番号）の入力チェック共通処理
//   使っている画面：register.html（新規登録） / mypage.html（会員情報編集）
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

  // 4項目をチェックして、すべてのエラーメッセージを配列で返す（問題なければ空配列）
  // 引数は trim() と normalizeZip() を通した値
  function collectErrors(userName, zipCode, address, tel) {
    var errors = [];

    if (userName === '') {
      errors.push('氏名を入力してください。');
    } else if (charLength(userName) > NAME_MAX) {
      errors.push('氏名は' + NAME_MAX + '文字以内で入力してください。');
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
    }

    if (tel === '') {
      errors.push('電話番号を入力してください。');
    } else if (tel.length > TEL_MAX || !/^[0-9\-]{10,15}$/.test(tel)) {
      errors.push('電話番号は' + TEL_MAX + '桁以内の半角数字（ハイフン可）で入力してください。');
    }

    return errors;
  }

  // 4項目をチェックして、エラーメッセージを返す（問題なければ null）
  // 引数は trim() と normalizeZip() を通した値
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
    normalizeZip: normalizeZip,
    collectErrors: collectErrors,
    validate: validate
  };
})(window);
