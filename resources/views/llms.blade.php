# Favicons
> Drop-in favicon URLs for any domain. Cached, easily refreshed, and great for hotlinking.

Favicons is an HTTP image API. Request a domain and receive a favicon image response. No API key is required. Respect rate limits on refresh requests and the Acceptable Use Policy.

API:
- `GET /i/{domain}` — return a favicon for `{domain}` (letters, digits, `.`, `-` only)
- `GET /i/{domain}?sz={size}` — optional square size from 16 to 512 (default 32)
- `DELETE /r/{domain}` — force-refresh the cached favicon for `{domain}` (rate limited per IP + domain)
- `GET /leaderboard` — HTML list of most-requested domains

## Docs
- [Home]({{ url('/') }}): Landing page with live examples and endpoint summary
- [Leaderboard]({{ url('/leaderboard') }}): Most requested favicon domains

## Policies
- [Terms of Service]({{ url('/terms') }}): Terms for using the Service
- [Acceptable Use Policy]({{ url('/acceptable-use') }}): Allowed and prohibited uses
- [Privacy Policy]({{ url('/privacy') }}): How request and technical data are handled

## Optional
- [GitHub repository](https://github.com/vblinden/favicons): Source code and issue tracker
