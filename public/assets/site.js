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
  on('mobile-menu-button', function () {
    var menu = document.getElementById('mobile-menu');
    if (menu) {
      menu.classList.toggle('hidden');
    }
  });

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
