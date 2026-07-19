# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-07-19

### Added
- Initial release.
- Auto-generates an IndexNow key on activation and serves it at `/<key>.txt`.
- `stpi:indexnow:submit` console command with `--full`, `--since`, `--dry-run` and `--include-local` options.
- Daily scheduled task `stpi_indexnow.submit` for incremental submission (only URLs changed since the last run).
- Collects **canonical** URLs per sales channel domain (active products, `page` categories, landing pages); skips non-public hosts (`localhost`, private ranges).
- Batches up to 10,000 URLs per request; endpoint configurable (default `https://api.indexnow.org/indexnow`).
