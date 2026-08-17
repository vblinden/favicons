# Favicons

Drop-in favicon URLs for any domain. Cached, easily refreshed, and built for hotlinking.

No API key. Responses are PNG. Invalid or unreachable sites get a Star Avatars image, then a generated letter tile.

## API

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/i/{domain}` | Favicon for `{domain}` (letters, digits, `.`, `-`). Default size 32. |
| `GET` | `/i/{domain}?sz={size}` | Square PNG from 16 to 512. |
| `DELETE` | `/r/{domain}` | Force-refresh the cached master (rate limited per IP + domain). |
| `GET` | `/leaderboard` | Most-requested domains. |

Examples:

```
GET /i/github.com
GET /i/github.com?sz=64
DELETE /r/github.com
```

Cold cache misses crawl the site and are rate limited per IP. Cached hits are not. Refresh is limited separately (default 5 per week per IP + domain).

## Local setup

```bash
composer setup
composer run dev
```

GD is required for PNG/ICO handling.

## Tests

```bash
php artisan test --compact
```

## Policies

- [Terms](/terms)
- [Acceptable use](/acceptable-use)
- [Privacy](/privacy)
- [llms.txt](/llms.txt)
