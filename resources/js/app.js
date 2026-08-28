const playground = document.getElementById('favicon-playground');

if (playground instanceof HTMLFormElement) {
    const input = document.getElementById('playground-domain');
    const preview = document.getElementById('playground-preview');
    const snippet = document.getElementById('playground-snippet');
    const copyButton = document.getElementById('playground-copy');

    const normalizeDomain = (value) => {
        const trimmed = value.trim().toLowerCase();

        if (trimmed === '') {
            return null;
        }

        try {
            const url = new URL(trimmed.includes('://') ? trimmed : `https://${trimmed}`);

            return url.hostname || null;
        } catch {
            return trimmed.replace(/[^a-z0-9.-]/g, '') || null;
        }
    };

    const updatePreview = () => {
        if (!(input instanceof HTMLInputElement) || !(preview instanceof HTMLImageElement) || !(snippet instanceof HTMLElement)) {
            return;
        }

        const domain = normalizeDomain(input.value);

        if (domain === null) {
            return;
        }

        const imageUrl = `/i/${encodeURIComponent(domain)}?sz=64`;
        const hotlink = `${window.location.origin}/i/${domain}`;

        preview.src = imageUrl;
        preview.alt = `${domain} favicon`;
        snippet.textContent = `<img src="${hotlink}" alt="" width="32" height="32">`;
    };

    playground.addEventListener('submit', (event) => {
        event.preventDefault();
        updatePreview();
    });

    input?.addEventListener('change', updatePreview);

    copyButton?.addEventListener('click', async () => {
        if (!(snippet instanceof HTMLElement)) {
            return;
        }

        try {
            await navigator.clipboard.writeText(snippet.textContent ?? '');
            copyButton.textContent = 'Copied';
            window.setTimeout(() => {
                copyButton.textContent = 'Copy';
            }, 1500);
        } catch {
            copyButton.textContent = 'Failed';
            window.setTimeout(() => {
                copyButton.textContent = 'Copy';
            }, 1500);
        }
    });
}
