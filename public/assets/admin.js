/*
 * 管理画面のクライアントスクリプト。
 *
 * 公開側と同じ理由でインライン <script> が書けない（usePHP の Renderer は
 * children の文字列を必ずエスケープする）ので、JS はこのファイルに寄せ、
 * サーバからの値は data 属性で受け取る。読み込むのは AdminLayout だけ
 * （公開側のページには 1 バイトも足さない）。
 *
 * 触る相手は AdminComponents::editor() が出す DOM で、契約は data 属性:
 *
 *   [data-editor]                フォーム本体。data-preview / data-preview-url /
 *                                data-upload-url / data-draft-key を持つ
 *   [data-editor-body]           本文の textarea
 *   [data-editor-preview]        プレビューの差し込み先
 *   [data-editor-preview-pane]   プレビュー側のスクロール箱（同期する相手）
 *   [data-editor-preview-toggle] プレビューの開閉
 *   [data-editor-count]          文字数
 *   [data-editor-status]         アップロードなどの途中経過
 *   [data-editor-dirty]          未保存インジケータ
 *   [data-editor-file]           「画像」ボタンが開くファイル選択
 *   [data-editor-draft]          書きかけの復元を促す帯（既定は hidden）
 *   [data-editor-draft-label]    その文言の差し込み先
 *   [data-editor-draft-action]   restore / discard
 *   [data-md]                    Markdown の記法ボタン
 *   [data-copy]                  クリップボードにコピー（画像一覧）
 *   [data-confirm]               押す前に確認する（削除）
 *
 * 記事一覧の一括操作（AdminComponents::postTable + bulkBar）はこの契約:
 *
 *   [data-bulk]                  一括操作のフォーム
 *   [data-bulk-item]             行のチェックボックス。値はその記事の状態
 *   [data-bulk-all]              このページの下書きを全選択
 *   [data-bulk-count]            選択件数の差し込み先
 *   [data-bulk-submit]           実行ボタン。0 件のあいだ disabled
 */
(function () {
  'use strict';

  var PREVIEW_KEY = 'admin:preview';
  var DRAFT_PREFIX = 'admin:draft:';
  var INDENT = '  ';

  /*
   * 行頭の記法。Enter の継続と Tab のネストが見るのはこれ 1 本。
   *
   *   1: インデント  2: 箇条書きの記号  3: 番号  4: 番号の後ろ（. か )）
   *
   * 引用は `>` のあとの空白が無くても成立するので `\s?`。番号リストを
   * `\d+` で拾うのは、継続のときに +1 して振り直すため。
   */
  var LINE_MARKER = /^([ \t]*)(?:([-*+])[ \t]+|(\d+)([.)])[ \t]+|>[ \t]?)/;

  function initConfirm() {
    var buttons = document.querySelectorAll('[data-confirm]');
    for (var i = 0; i < buttons.length; i++) {
      buttons[i].addEventListener('click', function (event) {
        if (!window.confirm(event.currentTarget.dataset.confirm)) {
          event.preventDefault();
        }
      });
    }
  }

  function initCopy() {
    var buttons = document.querySelectorAll('[data-copy]');
    for (var i = 0; i < buttons.length; i++) {
      buttons[i].addEventListener('click', function (event) {
        var button = event.currentTarget;
        var label = button.textContent;

        // クリップボード API は https か localhost でしか使えない。
        // 使えない環境では黙って失敗せず、その旨を出す。
        if (!navigator.clipboard) {
          button.textContent = 'コピーできません';
          window.setTimeout(function () {
            button.textContent = label;
          }, 1500);
          return;
        }

        navigator.clipboard.writeText(button.dataset.copy).then(function () {
          button.textContent = 'コピーしました';
          window.setTimeout(function () {
            button.textContent = label;
          }, 1500);
        });
      });
    }
  }

  function initEditor() {
    var form = document.querySelector('[data-editor]');
    if (!form) {
      return;
    }

    var body = form.querySelector('[data-editor-body]');
    var preview = form.querySelector('[data-editor-preview]');
    var previewPane = form.querySelector('[data-editor-preview-pane]');
    var toggle = form.querySelector('[data-editor-preview-toggle]');
    var count = form.querySelector('[data-editor-count]');
    var status = form.querySelector('[data-editor-status]');
    var dirtyMark = form.querySelector('[data-editor-dirty]');
    var file = form.querySelector('[data-editor-file]');
    var dirty = false;
    var timer = null;
    var rendered = null;

    function setStatus(text) {
      if (status) {
        status.textContent = text;
      }
    }

    function updateCount() {
      if (count) {
        count.textContent = body.value.length.toLocaleString('ja-JP') + ' 字';
      }
    }

    function markDirty() {
      dirty = true;
      if (dirtyMark) {
        dirtyMark.removeAttribute('hidden');
      }
    }

    /*
     * プレビューはサーバの MarkdownRenderer に投げる。ブラウザ側にパーサを
     * 持たないのは、保存時と変換結果がずれると「プレビューでは正しかった
     * のに」が起きるため（/admin/preview の route.php にも同じ注意がある）。
     */
    function render() {
      if (form.dataset.preview !== 'on' || body.value === rendered) {
        return;
      }

      rendered = body.value;

      window
        .fetch(form.dataset.previewUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: 'body=' + encodeURIComponent(rendered),
        })
        .then(function (response) {
          if (!response.ok) {
            throw new Error(String(response.status));
          }
          return response.json();
        })
        .then(function (data) {
          // 中身は自分が書いた Markdown をサーバが変換した HTML。
          // 記事本文と同じものなので、記事と同じように表示する。
          preview.innerHTML = data.html;
        })
        .catch(function () {
          preview.textContent = 'プレビューを読み込めませんでした。';
        });
    }

    function schedule() {
      window.clearTimeout(timer);
      timer = window.setTimeout(render, 400);
    }

    function setPreview(on) {
      form.dataset.preview = on ? 'on' : 'off';
      if (toggle) {
        toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
      }
      try {
        window.localStorage.setItem(PREVIEW_KEY, on ? 'on' : 'off');
      } catch (e) {
        /* プライベートブラウジングでは覚えられないが、開閉自体は効く */
      }
      if (on) {
        render();
      }
    }

    /*
     * 挿入は execCommand を通す。deprecated だが、textarea の undo 履歴に
     * 残る挿入手段はこれしかない（value を直接書き換えると Ctrl+Z で
     * 戻せなくなり、書いている最中のエディタとしては致命的）。
     * 使えない環境では setRangeText に落とす。
     *
     * 空文字を渡すと「選択範囲を消す」の意味になる。execCommand は
     * insertText に空文字を渡すと false を返す実装があるので、削除は
     * delete コマンドでやる（こちらも undo 履歴に残る）。
     */
    function insert(text) {
      body.focus();

      var done = false;
      if (document.execCommand) {
        done = '' === text
          ? document.execCommand('delete')
          : document.execCommand('insertText', false, text);
      }

      if (!done) {
        var start = body.selectionStart;
        body.setRangeText(text, start, body.selectionEnd, 'end');
      }

      markDirty();
      updateCount();
      schedule();
    }

    // カーソル位置 pos を含む行の範囲。
    function lineAt(pos) {
      var end = body.value.indexOf('\n', pos);

      return {
        start: body.value.lastIndexOf('\n', pos - 1) + 1,
        end: end < 0 ? body.value.length : end,
      };
    }

    /*
     * 選択を before / after で囲む。すでに囲まれていれば外す —— 太字に
     * したい気持ちと戻したい気持ちは同じキーで来るので、⌘B は必ずトグル。
     * 囲んだあとも選択を保つのは、続けて別の装飾を重ねられるようにするため。
     */
    function surround(before, after) {
      var start = body.selectionStart;
      var end = body.selectionEnd;
      var selected = body.value.slice(start, end);

      // 選択の「外側」が囲みになっている（**|foo|** の形で中だけ選んだ）
      if (
        body.value.slice(start - before.length, start) === before &&
        body.value.slice(end, end + after.length) === after
      ) {
        body.setSelectionRange(start - before.length, end + after.length);
        insert(selected);
        body.setSelectionRange(start - before.length, start - before.length + selected.length);
        return;
      }

      // 選択が囲みごと（|**foo**| の形で囲みも含めて選んだ）
      if (
        selected.length >= before.length + after.length &&
        selected.slice(0, before.length) === before &&
        selected.slice(selected.length - after.length) === after
      ) {
        var inner = selected.slice(before.length, selected.length - after.length);
        insert(inner);
        body.setSelectionRange(start, start + inner.length);
        return;
      }

      insert(before + selected + after);
      body.setSelectionRange(start + before.length, start + before.length + selected.length);
    }

    /*
     * 行頭の記法（見出し・引用・箇条書き）。選択が複数行なら全部の行に付ける。
     * 全部の行がすでに付いていれば外す（囲みと同じくトグル）。
     *
     * @param {function(number): string} prefix 行番号 → 付ける文字列
     */
    function prefixLines(prefix) {
      var first = lineAt(body.selectionStart);
      var last = lineAt(body.selectionEnd);
      var lines = body.value.slice(first.start, last.end).split('\n');

      var attached = lines.every(function (line, i) {
        return line.indexOf(prefix(i)) === 0;
      });

      var next = lines
        .map(function (line, i) {
          if (attached) {
            return line.slice(prefix(i).length);
          }

          // 別の行頭記法が付いていれば置き換える（H2 → 引用が 1 手で済む）
          var marker = LINE_MARKER.exec(line);
          var heading = /^#{1,6} /.exec(line);
          var bare = line.slice(marker ? marker[0].length : heading ? heading[0].length : 0);

          return prefix(i) + bare;
        })
        .join('\n');

      body.setSelectionRange(first.start, last.end);
      insert(next);
      body.setSelectionRange(first.start, first.start + next.length);
    }

    function fixed(text) {
      return function () {
        return text;
      };
    }

    /*
     * リンク。URL は空にして括弧の中にキャレットを置く —— そこへ貼り付ける
     * のがいちばん多い動きで、ダミーの https:// は毎回消す手間になる。
     */
    function insertLink() {
      var start = body.selectionStart;
      var selected = body.value.slice(start, body.selectionEnd);
      var caret = start + selected.length + 3;

      insert('[' + selected + ']()');
      body.setSelectionRange(caret, caret);
    }

    function insertTable() {
      var head = '\n| 見出し | 見出し |\n| --- | --- |\n|  |  |\n';
      var caret = body.selectionStart + head.indexOf('|  |') + 2;

      insert(head);
      body.setSelectionRange(caret, caret);
    }

    function applyTool(key) {
      if (key === 'bold') {
        surround('**', '**');
      } else if (key === 'italic') {
        surround('*', '*');
      } else if (key === 'inlineCode') {
        surround('`', '`');
      } else if (key === 'link') {
        insertLink();
      } else if (key === 'code') {
        surround('\n```\n', '\n```\n');
      } else if (key === 'h2') {
        prefixLines(fixed('## '));
      } else if (key === 'h3') {
        prefixLines(fixed('### '));
      } else if (key === 'quote') {
        prefixLines(fixed('> '));
      } else if (key === 'list') {
        prefixLines(fixed('- '));
      } else if (key === 'olist') {
        prefixLines(function (i) {
          return i + 1 + '. ';
        });
      } else if (key === 'table') {
        insertTable();
      } else if (key === 'image') {
        if (file) {
          file.click();
        }
      }
    }

    /*
     * Enter でリスト・引用を継げる。項目が空のまま Enter を押したときは
     * 記法のほうを外す（箇条書きを終える動き）——ここが無いと、リストを
     * 抜けるたびに手で行頭を消すことになる。
     *
     * 戻り値 true で既定の改行を止める。
     */
    function continueLine() {
      if (body.selectionStart !== body.selectionEnd) {
        return false;
      }

      var line = lineAt(body.selectionStart);
      var before = body.value.slice(line.start, body.selectionStart);
      var marker = LINE_MARKER.exec(before);

      if (!marker) {
        return false;
      }

      // 記法だけの行 → 記法を消して抜ける
      if ('' === before.slice(marker[0].length).trim()) {
        body.setSelectionRange(line.start, body.selectionStart);
        insert('');
        return true;
      }

      // 番号リストだけは振り直す。ほかは同じ記法をそのまま次の行へ。
      insert(
        '\n' +
          (undefined === marker[3]
            ? marker[0]
            : marker[1] + (parseInt(marker[3], 10) + 1) + marker[4] + ' ')
      );

      return true;
    }

    /*
     * Tab でネスト、Shift+Tab で戻す。奪うのは「リスト・引用の行にいる」か
     * 「複数行を選んでいる」ときだけ —— それ以外で奪うと、キーボードだけで
     * 本文欄から出られなくなる。
     */
    function indent(back) {
      var first = lineAt(body.selectionStart);
      var last = lineAt(body.selectionEnd);
      var multiline = first.start !== last.start;

      if (!multiline && !LINE_MARKER.test(body.value.slice(first.start, first.end))) {
        return false;
      }

      var lines = body.value.slice(first.start, last.end).split('\n');
      var next = lines
        .map(function (line) {
          return back ? line.replace(/^[ \t]{1,2}/, '') : INDENT + line;
        })
        .join('\n');

      var caret = body.selectionStart - first.start;
      var shift = next.split('\n')[0].length - lines[0].length;

      body.setSelectionRange(first.start, last.end);
      insert(next);

      if (multiline) {
        body.setSelectionRange(first.start, first.start + next.length);
      } else {
        // 行頭にキャレットがある状態で戻すと first.start より前を指しうる
        var at = first.start + Math.max(0, caret + shift);
        body.setSelectionRange(at, at);
      }

      return true;
    }

    /*
     * 画像。ドロップと貼り付けの両方から同じ口に流す。アップロード先は
     * /admin/media/upload で、CSRF トークンはフォームに埋まっているものを
     * そのまま渡す（route.php は CSRF を自動では見ないので、受け側で
     * 明示的に検証している）。
     */
    function uploadFiles(files) {
      var token = form.querySelector('input[name="_usephp_csrf"]');
      var images = [];

      for (var i = 0; i < files.length; i++) {
        if (files[i].type.indexOf('image/') === 0) {
          images.push(files[i]);
        }
      }

      if (images.length === 0 || !token) {
        return;
      }

      // 落とした位置を覚えておく。アップロードを待つあいだも書き続けられる
      // ので、完了したときのカーソルは別の場所にあることがある。
      var anchor = body.selectionStart;

      setStatus('アップロード中…');

      var pending = images.map(function (file) {
        var data = new FormData();
        data.append('file', file);
        data.append('_usephp_csrf', token.value);

        return window
          .fetch(form.dataset.uploadUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: data,
          })
          .then(function (response) {
            return response.json().then(function (json) {
              if (!response.ok) {
                throw new Error(json.error || String(response.status));
              }
              return json.url;
            });
          });
      });

      Promise.all(pending)
        .then(function (urls) {
          var at = Math.min(anchor, body.value.length);
          body.setSelectionRange(at, at);
          insert(
            urls
              .map(function (url) {
                return '![](' + url + ')';
              })
              .join('\n') + '\n'
          );
          setStatus('');
        })
        .catch(function (error) {
          setStatus('アップロードに失敗: ' + error.message);
        });
    }

    /*
     * 書きかけの退避。beforeunload の確認だけでは、タブが落ちたときや
     * 「このページを離れる」を押し間違えたときに戻せない。localStorage に
     * 逃がしておき、次に同じ編集画面を開いたときに復元を持ちかける。
     *
     * キーは保存先の URL（記事ごとに違い、新規と編集も別）。
     */
    var draftKey = DRAFT_PREFIX + (form.dataset.draftKey || form.getAttribute('action') || '');
    var draftNode = form.querySelector('[data-editor-draft]');
    var draftLabel = form.querySelector('[data-editor-draft-label]');
    var title = form.querySelector('input[name="title"]');
    var draftTimer = null;

    function writeDraft() {
      try {
        window.localStorage.setItem(
          draftKey,
          JSON.stringify({
            title: title ? title.value : '',
            body: body.value,
            at: new Date().toISOString(),
          })
        );
      } catch (e) {
        /* 容量やプライベートブラウジングで書けなくても編集自体は続く */
      }
    }

    function clearDraft() {
      try {
        window.localStorage.removeItem(draftKey);
      } catch (e) {
        /* 消せなくても、次に開いたとき本文が同じなら持ちかけない */
      }
    }

    function scheduleDraft() {
      window.clearTimeout(draftTimer);
      draftTimer = window.setTimeout(writeDraft, 1000);
    }

    function offerDraft() {
      var draft = null;
      try {
        draft = JSON.parse(window.localStorage.getItem(draftKey) || 'null');
      } catch (e) {
        return;
      }

      // サーバにある本文と同じなら、退避は用済み。
      if (!draft || !draftNode || draft.body === body.value) {
        clearDraft();
        return;
      }

      if (draftLabel) {
        draftLabel.textContent =
          '保存していない書きかけがあります（' +
          new Date(draft.at).toLocaleString('ja-JP', {
            month: 'numeric',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
          }) +
          '）。';
      }

      draftNode.removeAttribute('hidden');

      var buttons = draftNode.querySelectorAll('[data-editor-draft-action]');
      for (var n = 0; n < buttons.length; n++) {
        buttons[n].addEventListener('click', function (event) {
          if (event.currentTarget.dataset.editorDraftAction === 'restore') {
            if (title && draft.title) {
              title.value = draft.title;
            }
            // 全選択してから流し込む。undo 履歴に残るので Ctrl+Z で戻せる。
            body.focus();
            body.setSelectionRange(0, body.value.length);
            insert(draft.body);
          } else {
            clearDraft();
          }
          draftNode.setAttribute('hidden', 'hidden');
        });
      }
    }

    function setDragging(on) {
      if (on) {
        body.setAttribute('data-dragging', 'on');
      } else {
        body.removeAttribute('data-dragging');
      }
    }

    /*
     * 本文とプレビューのスクロールを比率で合わせる。行単位で対応を取るには
     * Markdown を行までさかのぼって追う必要があり、サーバ変換の方針
     * （ブラウザにパーサを置かない）と噛み合わないので、割り切って比率で。
     *
     * こちらが動かした相手からも scroll が飛んでくるので、それを跳ね返すと
     * 2 つの箱が押し合いになる。直前に動かされた側からの scroll は少しの
     * あいだ無視する —— 動かしている側が主、で決まる。
     *
     * 時刻で見るのは、requestAnimationFrame が止まる状況（画面に出ていない
     * タブなど）でも解除されるようにするため。フラグを rAF で戻す作りだと、
     * そこで固まったまま片方向が死ぬ。
     */
    var pushedTo = null;
    var pushedAt = 0;

    function bindSync(source, target) {
      source.addEventListener('scroll', function () {
        if (form.dataset.preview !== 'on') {
          return;
        }

        if (source === pushedTo && new Date().getTime() - pushedAt < 120) {
          return;
        }

        var range = source.scrollHeight - source.clientHeight;
        var targetRange = target.scrollHeight - target.clientHeight;

        if (range <= 0 || targetRange <= 0) {
          return;
        }

        pushedTo = target;
        pushedAt = new Date().getTime();
        target.scrollTop = (source.scrollTop / range) * targetRange;
      });
    }

    body.addEventListener('input', function () {
      markDirty();
      updateCount();
      schedule();
      scheduleDraft();
    });

    /*
     * IME の変換中は何もしない。変換確定の Enter がここまで来ると、
     * 日本語を打つたびにリストが増える。
     */
    body.addEventListener('keydown', function (event) {
      if (event.isComposing || event.keyCode === 229) {
        return;
      }

      var mod = event.metaKey || event.ctrlKey;

      if (mod && !event.altKey) {
        var key = event.key.toLowerCase();

        if (key === 'b' || key === 'i' || key === 'k') {
          event.preventDefault();
          applyTool(key === 'b' ? 'bold' : key === 'i' ? 'italic' : 'link');
        }

        return;
      }

      if (event.key === 'Enter' && !event.shiftKey && !event.altKey) {
        if (continueLine()) {
          event.preventDefault();
        }

        return;
      }

      if (event.key === 'Tab' && !event.altKey && indent(event.shiftKey)) {
        event.preventDefault();
      }
    });

    body.addEventListener('dragover', function (event) {
      event.preventDefault();
      setDragging(true);
    });

    body.addEventListener('dragleave', function () {
      setDragging(false);
    });

    body.addEventListener('drop', function (event) {
      setDragging(false);

      if (event.dataTransfer && event.dataTransfer.files.length > 0) {
        event.preventDefault();
        uploadFiles(event.dataTransfer.files);
      }
    });

    body.addEventListener('paste', function (event) {
      if (!event.clipboardData) {
        return;
      }

      if (event.clipboardData.files.length > 0) {
        event.preventDefault();
        uploadFiles(event.clipboardData.files);
        return;
      }

      // 文字を選んだまま URL を貼ったらリンクにする。
      var text = (event.clipboardData.getData('text/plain') || '').trim();

      if (/^https?:\/\/\S+$/.test(text) && body.selectionStart !== body.selectionEnd) {
        event.preventDefault();
        insert('[' + body.value.slice(body.selectionStart, body.selectionEnd) + '](' + text + ')');
      }
    });

    if (file) {
      file.addEventListener('change', function () {
        if (file.files.length > 0) {
          uploadFiles(file.files);
        }
        // 同じ画像をもう一度選べるようにする（change が飛ばなくなるため）
        file.value = '';
      });
    }

    if (toggle) {
      toggle.addEventListener('click', function () {
        setPreview(form.dataset.preview !== 'on');
      });
    }

    if (previewPane) {
      bindSync(body, previewPane);
      bindSync(previewPane, body);
    }

    var tools = form.querySelectorAll('[data-md]');
    for (var i = 0; i < tools.length; i++) {
      tools[i].addEventListener('click', function (event) {
        applyTool(event.currentTarget.dataset.md);
      });
    }

    form.addEventListener('input', markDirty);
    form.addEventListener('submit', function () {
      dirty = false;
      window.clearTimeout(draftTimer);
      clearDraft();
    });

    window.addEventListener('beforeunload', function (event) {
      if (dirty) {
        writeDraft();
        event.preventDefault();
        event.returnValue = '';
      }
    });

    // Ctrl/Cmd + S で保存。本文が長いほどマウスに手を伸ばす回数が効いてくる。
    document.addEventListener('keydown', function (event) {
      if ((event.metaKey || event.ctrlKey) && event.key === 's') {
        event.preventDefault();
        form.requestSubmit();
      }
    });

    updateCount();
    offerDraft();

    var stored = null;
    try {
      stored = window.localStorage.getItem(PREVIEW_KEY);
    } catch (e) {
      /* 読めなくても既定（閉じている）で動く */
    }
    if (stored === 'on') {
      setPreview(true);
    }
  }

  /*
   * 記事一覧の一括公開。選択が 0 件のあいだボタンを disabled にするのは
   * クラスの付け外しを避けるため —— Tailwind は src/** しかスキャンせず、
   * ここにだけ現れるクラスは CSS に出力されない。
   */
  function initBulk() {
    var form = document.querySelector('[data-bulk]');
    if (!form) {
      return;
    }

    var items = form.querySelectorAll('[data-bulk-item]');
    var all = form.querySelector('[data-bulk-all]');
    var count = form.querySelector('[data-bulk-count]');
    var submit = form.querySelector('[data-bulk-submit]');

    function selected() {
      var n = 0;
      for (var i = 0; i < items.length; i++) {
        if (items[i].checked) {
          n++;
        }
      }
      return n;
    }

    function sync() {
      var n = selected();
      if (count) {
        count.textContent = n + ' 件を選択中';
      }
      if (submit) {
        submit.disabled = n === 0;
      }
    }

    for (var i = 0; i < items.length; i++) {
      items[i].addEventListener('change', sync);
    }

    if (all) {
      all.addEventListener('change', function (event) {
        var on = event.currentTarget.checked;
        for (var n = 0; n < items.length; n++) {
          // 公開済みの行まで巻き込まない。全選択が欲しくなるのは
          // 「下書きをまとめて出す」ときだけなので、対象もそれに合わせる。
          if (items[n].dataset.bulkItem === 'draft') {
            items[n].checked = on;
          }
        }
        sync();
      });
    }

    form.addEventListener('submit', function (event) {
      var n = selected();
      if (n === 0) {
        event.preventDefault();
        return;
      }
      if (!window.confirm(n + ' 件を公開します。よろしいですか？')) {
        event.preventDefault();
      }
    });

    sync();
  }

  initConfirm();
  initCopy();
  initEditor();
  initBulk();
})();
