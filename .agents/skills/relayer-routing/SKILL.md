---
name: relayer-routing
description: Use when adding or editing routes in this Relayer app — page.psx/.php pages, route.php API endpoints, src/Pages/middleware.php, or React islands. Encodes the autowiring, Response, and CSRF/action contracts that are easy to get wrong.
---

# Relayer routing

`RELAYER.md` at the project root is the authoritative spec —
read it for the full model. This is the short, do-the-task
version; when the two ever disagree, RELAYER.md wins.

## Where routes live

`src/Pages/` is file-based (Next.js App Router-style). A
directory is a **page** (`page.psx`/`page.php` + optional
`layout.psx`) **or** an API endpoint (`route.php`) — never
both. `[param]` directories are dynamic segments; the root
`error.psx` renders any HTTP error (404/403/500…) raised via
`$ctx->abort()` / `notFound()`.

## Pages

Function page (preferred — the thinnest form):

```php
return fn (PageContext $ctx, MyService $s) => <section>…</section>;
```

Class page: `final class X extends PageComponent { public
function render(): Element { … } }`.

- Arguments autowire **by type**: `PageContext`, `Request`,
  `Identity`, and container services. A nullable `?Identity`
  = optional auth; a non-nullable `Identity` makes the page
  auth-required.
- Never read `$_GET` / `$_POST` / `$_SERVER` — take a
  `Request`.
- Forms: `$ctx->action('save', fn (array $form) => …)` — CSRF
  is automatic and the handler runs before render. Redirect
  with `$ctx->redirect('/x')`.

## API routes — `route.php`

```php
use Polidog\Relayer\Http\Response;

return [
    'GET'  => fn (MyRepo $r) => Response::json($r->all()),
    'POST' => fn (Request $req) => Response::json(['ok' => true], 201),
];
```

- A method-keyed map of autowired closures. Every handler
  **must return a `Response`**
  (`Response::json/text/noContent/redirect`) — returning raw
  data is a hard error.
- The file may **only return the map**: no class/function
  declarations (it is re-evaluated per request).
  `OPTIONS`/`HEAD` are synthesized when undeclared.

## Middleware — `src/Pages/middleware.php` (optional)

```php
return function (Request $request, Closure $next): void {
    $next($request);          // omit to short-circuit (401, 429, …)
};
```

One closure, declaration-free. For CORS use
`Cors::middleware([...])` — never hand-roll it.

## React islands

In PSX: `{Island::mount('Chart', ['points' => $data])}`. You
own the React bundle; island↔server talk is `fetch` to your
own `route.php` endpoints. No SSR.

## Before you finish

Run `vendor/bin/relayer routes` and confirm the new route
shows up. Stay minimal — add the thinnest thing that works:
no new Composer deps, no Node/build step, no "just in case"
layers.
