# Favicons

Drop-in favicon URLs for any domain. Cached, easily refreshed, and built for hotlinking.

No API key. Responses are PNG by default (optional WebP). Invalid or unreachable sites get a Star Avatars image, then a generated letter tile.

## API

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/i/{domain}` | Favicon for `{domain}` (letters, digits, `.`, `-`). Default size 32. |
| `GET` | `/i/{domain}?sz={size}` | Square image from 16 to 512. |
| `GET` | `/i/{domain}?theme=dark\|light` | Prefer matching `prefers-color-scheme` icons. |
| `GET` | `/i/{domain}?format=webp` | Optional WebP output. |
| `GET` | `/m/{domain}` | JSON metadata for the resolved favicon. |
| `GET` | `/b?domains=a.com,b.com` | Batch JSON of image URLs. |
| `GET` | `/s2/favicons?domain={domain}&sz={size}` | Google-compatible alias. |
| `DELETE` | `/r/{domain}` | Force-refresh the cached master (rate limited per IP + domain). |
| `GET` | `/leaderboard` | Most-requested domains. |

Examples:

```
GET /i/github.com
GET /i/github.com?sz=64
GET /i/github.com?format=webp
GET /m/github.com
GET /b?domains=github.com,laravel.com&sz=32
GET /s2/favicons?domain=github.com&sz=64
DELETE /r/github.com
```

Cold cache misses can soft-serve a placeholder and resolve in the background when a queue worker is running. Cached hits are not rate limited. Refresh is limited separately (default 5 per week per IP + domain).

## Local setup

```bash
composer setup
composer run dev
```

GD is required for PNG/ICO handling (WebP support recommended). Run a queue worker for async cold misses.

## Tests

```bash
php artisan test --compact
```

## Policies

- [Terms](/terms)
- [Acceptable use](/acceptable-use)
- [Privacy](/privacy)
- [llms.txt](/llms.txt)
