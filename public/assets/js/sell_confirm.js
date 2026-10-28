// 販売確認画面

// 二重送信対策：販売するボタンを押したらボタンを押せなくする
// disabled にしたボタンの値は送信されないので、hidden で action=sell を追加してから送信する
var sellButton = document.getElementById('sell_button');
if (sellButton !== null) {
  sellButton.addEventListener('click', function(event) {
    event.preventDefault();

    var form = document.getElementById('confirm_form');
    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'action';
    hidden.value = 'sell';
    form.appendChild(hidden);

    sellButton.disabled = true;
    form.submit();
  });
}

// 登録完了後：3秒後にメイン画面へ移動する
var mainLink = document.getElementById('main_link');
if (mainLink !== null) {
  var completeBox = document.getElementById('complete_box');
  if (completeBox !== null) {
    window.setTimeout(function() {
      var pageHeight = Math.max(
        document.body.scrollHeight,
        document.documentElement.scrollHeight
      );
      window.scrollTo({
        top: Math.max(0, pageHeight - window.innerHeight + 24),
        behavior: 'smooth'
      });
    }, 120);
  }
  setTimeout(function() {
    location.href = mainLink.href;
  }, 3000);
}
