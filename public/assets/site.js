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
