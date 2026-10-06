<?php
/** Isolated dependency regression gate; no database, scheduler or backup access. */
require __DIR__ . '/../vendor/autoload.php';

use League\CommonMark\GithubFlavoredMarkdownConverter;

$mode = $argv[1] ?? 'verify';
$failures = 0;
$checks = 0;
function check(bool $ok, string $name): void {
    global $failures, $checks;
    $checks++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) $failures++;
}

// Match both production release-note views exactly.
$safe = new GithubFlavoredMarkdownConverter(['html_input' => 'strip']);
$fixtures = [
    'headings' => "# Release\n\n## Änderungen\n\n**Sicher** und *schnell*.\n",
    'lists' => "- Fix A\n- Fix B\n\n1. Install\n2. Verify\n",
    'links' => "[Release](https://example.invalid/releases) und `code`.\n",
    'table' => "| Paket | Version |\n| --- | --- |\n| CommonMark | 2.10.2 |\n",
    'unicode-table' => "| Änderung | Status |\n| --- | --- |\n| Größe | grün |\n",
    'escaped-table-pipe' => "| Inhalt | Status |\n| --- | --- |\n| a\\|b | ok |\n",
    'task-list' => "- [x] Geprüft\n- [ ] Offen\n",
    'code-block' => "```sh\ncomposer install --no-dev\n```\n",
    'empty' => '',
];
$snapshot = [];
foreach ($fixtures as $name => $markdown) {
    $snapshot[$name] = (string) $safe->convert($markdown);
}
$root = realpath(__DIR__ . '/..');
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    $relative = substr($file->getPathname(), strlen($root) + 1);
    if ($file->isFile() && strtolower($file->getExtension()) === 'md'
        && !preg_match('~^(vendor|\.git)/~', $relative)) {
        $snapshot['repository:' . $relative] = hash('sha256', (string) $safe->convert(file_get_contents($file->getPathname())));
    }
}
ksort($snapshot);
$snapshotPath = getenv('COMMONMARK_BASELINE') ?: __DIR__ . '/commonmark-baseline.json';
if ($mode === 'snapshot') {
    file_put_contents($snapshotPath, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
    echo 'Saved ' . count($snapshot) . ' baseline renderings.' . PHP_EOL;
    exit(0);
}
if (!is_file($snapshotPath)) {
    fwrite(STDERR, "Missing CommonMark rendering baseline: $snapshotPath\n");
    exit(1);
}
if (is_file($snapshotPath)) {
    $baseline = json_decode(file_get_contents($snapshotPath), true, 512, JSON_THROW_ON_ERROR);
    foreach ($baseline as $name => $expected) {
        check(isset($snapshot[$name]) && $snapshot[$name] === $expected, 'render compatibility: ' . $name);
    }
}

$default = new GithubFlavoredMarkdownConverter();
foreach (['script', 'iframe', 'style', 'textarea'] as $tag) {
    $payload = "<div>\n<" . $tag . "\n\n<span src=\"/evil.js\">\n";
    $html = (string) $default->convert($payload);
    check(str_contains($html, '&lt;' . $tag), 'default GFM escapes bare disallowed tag: ' . $tag);
    $stripped = (string) $safe->convert($payload);
    check(!preg_match('~<(?:script|iframe|style|textarea)(?:\s|>|$)~i', $stripped), 'production strip rejects tag: ' . $tag);
}
check(str_contains((string) $default->convert('<scripts>'), '<scripts>'), 'allowed non-matching tag remains intact');

foreach ([25000, 50000, 100000, 200000] as $lines) {
    $start = hrtime(true);
    $output = (string) $safe->convert(str_repeat("12345678\n", $lines));
    $seconds = (hrtime(true) - $start) / 1e9;
    printf("PERF lines=%d seconds=%.6f bytes=%d\n", $lines, $seconds, strlen($output));
    check(substr_count($output, '12345678') === $lines, 'large paragraph preserves all lines: ' . $lines);
    if ($lines === 200000) check($seconds < 5.0, 'adversarial table-start paragraph finishes under 5 seconds');
}
echo "Checks=$checks Failures=$failures\n";
exit($failures ? 1 : 0);
