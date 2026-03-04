<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Completing sign-in…</title>
</head>
<body>
    <p id="status">Completing sign-in…</p>

    <script>
        (function () {
            const payload = @json($payload);
            const message = { ...payload, source: 'spendless-oauth-popup' };

            if (window.opener && !window.opener.closed) {
                window.opener.postMessage(message, window.location.origin);
                window.close();
                return;
            }

            const status = document.getElementById('status');
            if (status) {
                status.textContent = 'Sign-in completed. You can close this window.';
            }
        })();
    </script>
</body>
</html>
