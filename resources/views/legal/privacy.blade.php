<x-document
    title="Privacy Policy"
    description="Privacy Policy for the Favicons drop-in favicon API."
>
    <x-legal-article heading="Privacy Policy" updated="16 August 2026">
        <p>
            This Privacy Policy explains how Favicons (the “Service”) handles information when you
            use {{ url('/') }}. The Service is a favicon resolution and caching API. It does not
            require user accounts.
        </p>

        <h2>Who we are</h2>
        <p>
            The Service is operated by the maintainer of the
            <a href="https://github.com/vblinden/favicons">vblinden/favicons</a>
            project. For privacy questions, open an issue on that repository.
        </p>

        <h2>Information we process</h2>
        <p>Depending on how you use the Service, we may process:</p>
        <ul>
            <li>
                <strong class="font-medium text-ink">Request data</strong>
                — domains you ask us to resolve, optional size parameters, timestamps, and aggregate
                request counts used for caching and the public leaderboard
            </li>
            <li>
                <strong class="font-medium text-ink">Technical data</strong>
                — IP address, user agent, and similar HTTP metadata used for security, rate limiting,
                debugging, and operating the Service
            </li>
            <li>
                <strong class="font-medium text-ink">Cached favicon assets</strong>
                — image files and related metadata (such as source URL, content type, and dimensions)
                retrieved from third-party websites
            </li>
            <li>
                <strong class="font-medium text-ink">Error and performance data</strong>
                — application logs and, when configured, error reports, traces, or profiles sent to
                our monitoring provider (Sentry)
            </li>
        </ul>
        <p>
            We do not intentionally collect names, email addresses, payment details, or account
            profiles through the Service.
        </p>

        <h2>Why we process it</h2>
        <ul>
            <li>Provide, cache, and serve favicon images</li>
            <li>Operate public features such as the leaderboard</li>
            <li>Enforce rate limits and prevent abuse</li>
            <li>Maintain security, reliability, and performance</li>
            <li>Diagnose errors and improve the Service</li>
            <li>Comply with legal obligations where applicable</li>
        </ul>

        <h2>Cookies and local storage</h2>
        <p>
            The public pages of the Service are primarily informational and do not depend on
            advertising cookies. The application stack may set a session cookie when Laravel’s web
            middleware runs. We do not use that session data to build marketing profiles.
        </p>

        <h2>Sharing</h2>
        <p>We may share information with:</p>
        <ul>
            <li>Infrastructure and hosting providers that run the Service</li>
            <li>Error and performance monitoring providers such as Sentry, when enabled</li>
            <li>Authorities when required by law or to protect the Service and its users</li>
        </ul>
        <p>
            We do not sell personal information. Third-party websites contacted during favicon
            fetching receive standard HTTP requests from our servers and are outside this Privacy
            Policy.
        </p>

        <h2>Retention</h2>
        <p>
            Cached favicons and domain request counters are kept while useful for operating the
            Service and may be refreshed or removed over time. Rate-limit records are temporary.
            Logs and monitoring events are retained only as long as needed for operations, security,
            and troubleshooting, unless a longer period is required by law.
        </p>

        <h2>International transfers</h2>
        <p>
            The Service and its vendors may process data in countries other than your own. Where we
            use providers such as Sentry, their processing is subject to their own terms and
            safeguards.
        </p>

        <h2>Your rights</h2>
        <p>
            Depending on where you live, you may have rights to access, correct, delete, or restrict
            certain personal data, or to object to certain processing. Because the Service is largely
            anonymous and request-based, we may need enough information from you to locate relevant
            records. Contact us via
            <a href="https://github.com/vblinden/favicons/issues">GitHub Issues</a>.
        </p>

        <h2>Children</h2>
        <p>
            The Service is not directed at children and is intended for general technical use.
        </p>

        <h2>Changes</h2>
        <p>
            We may update this Privacy Policy from time to time. The “Last updated” date at the top
            of this page will change when we do.
        </p>
    </x-legal-article>
</x-document>
