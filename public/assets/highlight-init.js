/*
 * 記事本文のコードブロックに色を付ける。
 *
 * site.js から動的 import される。読むのは「ページに <pre><code> がある
 * とき」だけなので、トップや一覧やタグページでは 1 バイトも落ちてこない。
 *
 * 記事本文は保存時に CommonMark が組み立てた HTML がそのまま DB に入って
 * いて、コードブロックは <pre><code class="language-xxx"> の形で来る
 * （Hugo 時代の chroma と違い、色は付いていない）。
 */
import hljs from '/assets/hljs/highlight.min.js';

/*
 * 記事の言語指定のうち、highlight.js の組み込みエイリアスでは解決しない
 * ものだけを書く。js / ts / tsx / html / yml / sh / py / rb / txt / toml
 * などは hljs 自身が別名として持っているので、ここには要らない。
 *
 * 20 年ぶんの記事から実際に拾った指定なので、思いつきで足さないこと
 * （DB を数えれば何が使われているかは出る）。
 */
const ALIASES = {
  coffee: 'coffeescript',
  ejs: 'handlebars',
  config: 'ini',
  conf: 'ini',
  compose: 'yaml',
  'docker-compose': 'yaml',
  composer: 'json', // composer.json を貼っている
  hsell: 'bash', // 記事側の打ち間違い
  // 以下は「色を付けない」に倒す。実行結果やログの貼り付けなので、
  // 何かの言語として解釈させると嘘の色が付く。
  log: 'plaintext',
  dockerignore: 'plaintext',
  s: 'plaintext',
};

/*
 * common ビルドに入っていない言語。明示指定を見つけたときだけ取りに行く。
 * bin/build-hljs.sh が public/assets/hljs/languages/ に置いたものと対で、
 * 片方だけ足すと 404 を踏むので必ず両方直すこと。
 */
const LAZY = ['dockerfile', 'twig', 'nginx', 'apache', 'coffeescript', 'handlebars'];

/*
 * 言語指定なしのブロックを autodetect にかけるときの候補。common の 36
 * 言語すべてを突き合わせると、記事に 1 本も出てこない swift や vbnet や
 * wasm に化けることがある。実在する言語だけを母集団にしたほうが当たる。
 */
const DETECT = [
  'bash',
  'php',
  'javascript',
  'typescript',
  'json',
  'yaml',
  'sql',
  'xml',
  'python',
  'ini',
  'go',
  'rust',
  'java',
  'ruby',
  'lua',
  'css',
  'diff',
  'markdown',
  'plaintext',
];

/*
 * autodetect の足切り。highlightAuto が返す relevance がこれ未満なら、
 * 判定を捨てて無着色のままにする。
 *
 * 言語指定なしのブロックは 720 本あるが、その半分近くはコマンドの実行結果
 * やエラーログで、どの言語でもない。足切り無し（0）だと 95% に何かしらの
 * 色が付き、その多くが嘘になる——`$cfg['Servers'][$i]` という PHP が bash
 * に、`public class SampleClass {` という Java が TypeScript に、日本語の
 * 文章が YAML に化ける。
 *
 * 10 だと 720 本中 211 本（29%）だけが着色される。上げるほど「自信がある
 * ものだけ」に寄り、0 にすると全部に色が付く。
 */
const DETECT_MIN_RELEVANCE = 10;

/** <code> の class から言語名を取り出して正規化する。無ければ null。 */
function languageOf(code) {
  const match = /\blanguage-([^\s]+)/.exec(code.className);
  if (!match) {
    return null;
  }

  const raw = match[1].toLowerCase();

  return ALIASES[raw] || raw;
}

async function run() {
  const blocks = Array.from(document.querySelectorAll('pre > code'));
  if (0 === blocks.length) {
    return;
  }

  const languages = blocks.map(languageOf);

  // 遅延ロードが要る言語を先に揃える。1 記事に同じ言語が何度も出るので
  // Set で潰してから取りに行く。
  const needed = new Set(languages.filter((lang) => LAZY.includes(lang)));

  await Promise.all(
    Array.from(needed).map(async (lang) => {
      try {
        const module = await import(`/assets/hljs/languages/${lang}.min.js`);
        hljs.registerLanguage(lang, module.default);
      } catch (e) {
        // 取れなくても素のまま表示されるだけ。本文は読める。
      }
    }),
  );

  // highlightElement は要素の class をそのまま言語名として読むので、
  // ALIASES で名前を寄せただけでは効かない。hljs 側に別名として教える。
  // 遅延ロードの後でないと coffeescript / handlebars が未登録になる。
  Object.entries(ALIASES).forEach(([alias, target]) => {
    if (hljs.getLanguage(target)) {
      hljs.registerAliases(alias, { languageName: target });
    }
  });

  hljs.configure({
    languages: DETECT,
    // 本文は CommonMark が組んだ HTML で、コードブロックの中身は
    // エスケープ済み。Hugo 時代の生 HTML 混入で hljs が誤警告を出す
    // ことがあるが、実害が無いので黙らせる。
    ignoreUnescapedHTML: true,
  });

  blocks.forEach((code, index) => {
    const lang = languages[index];

    if (null === lang) {
      // 言語指定なし。highlightElement に渡すと hljs が内部で autodetect
      // して無条件に適用してしまい、足切りを挟む余地が無い。自前で判定を
      // 受け取って、relevance を見てから当てる。
      const result = hljs.highlightAuto(code.textContent || '');

      if (result.language && result.relevance >= DETECT_MIN_RELEVANCE) {
        // result.value は hljs がエスケープ済みの HTML を組んだもの。
        code.innerHTML = result.value;
        code.classList.add('hljs', 'language-' + result.language);
      }

      return;
    }

    // highlight.js が知らない言語（pug・vue・apex・fish・prisma）は
    // 触らない。highlightElement に渡すと警告を出したうえで、
    // 結局ハイライトせずに終わる。
    if (!hljs.getLanguage(lang)) {
      return;
    }

    hljs.highlightElement(code);
  });
}

run();
