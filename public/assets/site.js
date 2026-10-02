/*
 * 公開側のクライアントスクリプト。
 *
 * usePHP の Renderer は要素の子に来た文字列を必ずエスケープするので、
 * ページの中にインライン <script> を書くことができない。JS はすべて
 * このファイルに寄せ、必要な値は data 属性で受け渡す。
 */
(function () {
  'use strict';

  function toggleTheme() {
    var root = document.documentElement;
    var dark = root.classList.toggle('dark');
    try {
      localStorage.setItem('theme', dark ? 'dark' : 'light');
    } catch (e) {
      /* プライベートブラウジングでは保存できないが、切り替え自体は効く */
    }
  }

  function on(id, handler) {
    var element = document.getElementById(id);
    if (element) {
      element.addEventListener('click', handler);
    }
  }

  on('theme-toggle', toggleTheme);
  on('mobile-menu-button', function (event) {
    var menu = document.getElementById('mobile-menu');
    if (!menu) {
      return;
    }

    var open = !menu.classList.toggle('hidden');
    // ボタンの aria-expanded は開閉に合わせて書き換える。HTML に固定値で
    // 置いたままだと、支援技術には常に閉じていると伝わる。
    event.currentTarget.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  /*
   * 見開きの写真を東京のいまの天気に合わせる（雨と雪のときだけ替わる）。
   *
   * 季節の写真（data-photo）と、30 分以内に取った天気は head のインライン
   * スクリプトがもう <html> に付けている。ここは天気がまだ無いときに取りに
   * 行き、雨か雪なら data-photo を上書きするだけ。
   * 写真が出ない幅（lg 未満）では通信もしない。
   *
   * 読者の現在地ではなく東京に固定なのは、位置情報の許可を求めたくない
   * から。Open-Meteo はキー不要で CORS も通る。落ちていても季節の写真は
   * 出ているので、失敗は黙って捨てる。
   */
  var root = document.documentElement;
  if (!root.dataset.weather && document.querySelector('.spread-photo') && window.matchMedia('(min-width: 1024px)').matches) {
    fetch('https://api.open-meteo.com/v1/forecast?latitude=35.68&longitude=139.76&current=weather_code')
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        // WMO の天気コード。71–77 と 85・86 が雪で、51 以上の残り
        // （霧雨・雨・にわか雨・雷雨）は雨。50 未満は晴れ・曇り・霧。
        var code = data.current.weather_code;
        var kind = (code >= 71 && code <= 77) || code === 85 || code === 86 ? 'snow' : code >= 51 ? 'rain' : 'clear';
        root.dataset.weather = kind;
        if (kind !== 'clear') {
          root.dataset.photo = kind;
        }
        try {
          sessionStorage.setItem('weather', JSON.stringify({ kind: kind, at: Date.now() }));
        } catch (e) {
          /* 保存できなくても、このページの写真は替わっている */
        }
      })
      .catch(function () {});
  }

  /*
   * コードブロックのシンタックスハイライト。highlight.js は core だけで
   * 43KB あるので、本文にコードが 1 つも無いページ——トップ・一覧・タグ・
   * アーカイブ——では読まない。記事詳細でも、コードを貼っていない記事なら
   * 落ちてこない（記事の 4 割強にしかコードブロックは無い）。
   */
  if (document.querySelector('pre > code')) {
    import('/assets/highlight-init.js');
  }

  /*
   * Disqus。identifier は Hugo が使っていた値（content からの相対パスの
   * md5）をそのまま渡している。これが変わると過去のコメントが記事から
   * 切り離されるので、サーバ側の値をそのまま信じる。
   */
  var thread = document.getElementById('disqus_thread');
  if (thread && thread.dataset.disqusShortname) {
    window.disqus_config = function () {
      this.page.url = thread.dataset.disqusUrl;
      this.page.identifier = thread.dataset.disqusIdentifier || thread.dataset.disqusUrl;
      this.page.title = thread.dataset.disqusTitle;
    };

    // 記事を開いた人だけが読み込む。一覧やトップには影響しない。
    var script = document.createElement('script');
    script.src = 'https://' + thread.dataset.disqusShortname + '.disqus.com/embed.js';
    script.setAttribute('data-timestamp', String(Date.now()));
    script.async = true;
    document.head.appendChild(script);
  }
})();
