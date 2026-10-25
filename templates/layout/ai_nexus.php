<?php
declare(strict_types=1);

use Crustum\AiNexus\Frontend\Assets;

$variables = $aiNexusVariables ?? [];
$scriptOptions = ['type' => 'module'];
$csrfToken = $this->request->getAttribute('csrfToken');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <?php if ($csrfToken) : ?>
        <meta name="csrf-token" content="<?= h($csrfToken) ?>">
    <?php endif; ?>
    <title>AiNexus</title>
    <style>[v-cloak]{display:none}</style>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600" rel="stylesheet" />
    <?= $this->Html->css(Assets::cssUrls()) ?>
    <style data-scheme="dark" media="max-width: 1px">
<?php
$darkCssPath = Assets::diskFile(Assets::STYLES_DARK_CSS);
if (is_file($darkCssPath)) {
    echo file_get_contents($darkCssPath);
}
?>
    </style>
    <script>
        window.AiNexus = <?= json_encode($variables, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

        (function () {
            var stored = null;

            try {
                stored = localStorage.getItem('ai-nexus-theme');
            } catch (e) {
                stored = null;
            }

            var dark = stored === 'dark' || (
                stored !== 'light' && stored !== 'dark' &&
                window.matchMedia('(prefers-color-scheme: dark)').matches
            );
            var tag = document.querySelector('style[data-scheme="dark"]');

            if (tag) {
                tag.media = dark ? '' : 'max-width: 1px';
            } else {
                document.documentElement.classList.toggle('dark', dark);
            }
        })();
    </script>
</head>
<body>
<?= $this->fetch('content') ?>
<?= $this->Html->script(Assets::jsUrl(), $scriptOptions) ?>
</body>
</html>
