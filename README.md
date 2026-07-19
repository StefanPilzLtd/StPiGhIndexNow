# StPiGhIndexNow

Lightweight [IndexNow](https://www.indexnow.org/) plugin for **Shopware 6** — instantly notify **Bing, Yandex, Seznam and Naver** when your canonical URLs change, instead of waiting for their crawlers.

![Shopware](https://img.shields.io/badge/Shopware-6.7-189eff)
![PHP](https://img.shields.io/badge/PHP-8.2%20–%208.5-777bb4)
![License](https://img.shields.io/badge/license-MIT-green)

> IndexNow is **not** used by Google. For Google keep using your sitemap and Search Console.

## Features

- Auto-generates an IndexNow key on activation and serves it at `https://<domain>/<key>.txt`.
- Collects **canonical** SEO URLs per sales channel domain (active products, `page` categories, landing pages) — no internal/redirect URLs.
- Console command for manual full or incremental submission.
- Optional daily scheduled task that only submits URLs changed since the last run.
- Skips non-public hosts (`localhost`, `127.*`, private ranges) so you never ping search engines with dev URLs.
- Batches up to 10,000 URLs per request.

## Requirements

- Shopware 6.7
- PHP 8.2 – 8.5
- Public, resolvable domains configured on your sales channels (the key file must be reachable by the search engines).

## Installation

```bash
# copy the plugin to custom/plugins/StPiGhIndexNow, then:
bin/console plugin:refresh
bin/console plugin:install --activate StPiGhIndexNow
bin/console cache:clear
```

## Configuration

Admin → *Extensions → StPiGhIndexNow → Configuration*:

| Setting   | Description                                                                 |
|-----------|-----------------------------------------------------------------------------|
| `enabled` | Turn the **daily scheduled task** on. Default **off** — enable only once your domains are live. |
| `key`     | Auto-generated on activation. The file `/<key>.txt` is written automatically. |
| `endpoint`| IndexNow endpoint. Default `https://api.indexnow.org/indexnow` (fans out to all participating engines). |

## Usage

```bash
# Preview what would be submitted (nothing is sent):
bin/console stpi:indexnow:submit --full --dry-run

# Submit ALL canonical URLs once (e.g. right after go-live):
bin/console stpi:indexnow:submit --full

# Submit only URLs changed since the last run (what the scheduled task does):
bin/console stpi:indexnow:submit

# Submit URLs changed since a given date:
bin/console stpi:indexnow:submit --since="2026-07-01"
```

Options: `--full`, `--since=<datetime>`, `--dry-run`, `--include-local` (preview non-public hosts too).

Once `enabled` is on, the scheduled task `stpi_indexnow.submit` runs daily and submits incrementally.

## How it works

1. On activation a random key is generated and stored, and `public/<key>.txt` is written (proof of ownership).
2. `UrlCollector` reads canonical `seo_url` rows per `sales_channel_domain`.
3. `IndexNowClient` POSTs `{ host, key, keyLocation, urlList }` to the endpoint.

## License

MIT — see [LICENSE](LICENSE).
