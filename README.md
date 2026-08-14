# Relayer application

A [Relayer](https://github.com/polidog/relayer) application.

## Run

```bash
composer install
php -S 127.0.0.1:8000 -t public
```

Then open <http://127.0.0.1:8000>.

Or, with no host PHP:

```bash
docker compose up --build
```

Then open <http://localhost:8000>.

## Layout

```
.env                   APP_ENV=dev
composer.json
RELAYER.md             agent/LLM coding conventions (co-versioned)
AGENTS.md              auto-read pointer → RELAYER.md
CLAUDE.md              auto-read pointer → RELAYER.md
.claude/               Claude Code skill + reviewer agent (co-versioned)
Dockerfile             FrankenPHP (PHP 8.5) image
php.ini                PHP overrides (loaded via conf.d)
compose.yaml           `docker compose up` → http://localhost:8000
.dockerignore
config/
  services.yaml        Symfony DI registrations (auto-loaded)
public/
  index.php            single entrypoint: Relayer::boot()->run()
src/
  AppConfigurator.php  register your services here
  Pages/               file-based routes (Next.js App Router-style)
    layout.psx
    page.psx
```

## Production

`APP_ENV=dev` compiles `.psx`, scans routes, and rebuilds the
DI container on the fly. For deploys, unset (or change)
`APP_ENV` and precompile all three once:

```bash
composer install --no-dev --classmap-authoritative
vendor/bin/usephp compile src/Pages      # .psx  -> compiled PHP
vendor/bin/relayer routes:compile         # route map -> PHP
vendor/bin/relayer container:compile      # DI container -> PHP
```

Each step is presence-gated: a missing artifact degrades to
the live path rather than breaking. In production also set
OPcache `validate_timestamps=0` — the production block in
`php.ini` documents this.
