<?php

/**
 * Point Relaticle's "self-hosted" AI provider at the chat-completions driver.
 *
 * The SDK's `openai` driver always speaks the Responses API (`POST /responses`).
 * The OpenCode Go gateway refuses that shape for models it routes portably:
 *
 *   "store": requires a native Responses target; portable routing requires the full conversation
 *
 * So every chat turn died with HTTP 400. The `openai-compatible` driver speaks
 * `POST /chat/completions`, which the gateway accepts and translates to
 * DeepSeek itself.
 *
 * This patches exactly one line of upstream's config/ai.php and fails the build
 * loudly if that line is no longer where it is expected — better a red build
 * than a silently unpatched image after an upstream bump.
 */
$path = '/var/www/html/config/ai.php';

$source = file_get_contents($path);

if ($source === false) {
    fwrite(STDERR, "patch-ai-config: cannot read {$path}\n");
    exit(1);
}

$count = 0;
$patched = preg_replace(
    "/(\\n\\s*'selfhosted'\\s*=>\\s*\\[\\s*\\n\\s*'driver'\\s*=>\\s*)'openai'/",
    "$1'openai-compatible'",
    $source,
    1,
    $count
);

if ($count !== 1) {
    fwrite(STDERR, "patch-ai-config: the selfhosted provider block changed upstream; update this patch\n");
    exit(1);
}

if (file_put_contents($path, $patched) === false) {
    fwrite(STDERR, "patch-ai-config: cannot write {$path}\n");
    exit(1);
}

echo "patch-ai-config: selfhosted driver -> openai-compatible\n";
