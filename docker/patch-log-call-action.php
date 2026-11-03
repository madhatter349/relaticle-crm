<?php

/**
 * Put the "Log call" button on the record page.
 *
 * The page's action rail is assembled in HasRecordPageLayout::recordActions();
 * the action only shows itself on Company records, so People and Opportunities
 * keep the rail they had.
 *
 * Guarded twice: the import line and the array insertion both have to land, or
 * the build fails rather than shipping a button that silently does nothing.
 */
$path = '/var/www/html/app/Filament/Concerns/HasRecordPageLayout.php';

$source = file_get_contents($path);

if ($source === false) {
    fwrite(STDERR, "patch-log-call: cannot read {$path}\n");
    exit(1);
}

$use = "use App\\Filament\\Actions\\CopyToClipboardAction;\n";
$useWithAction = $use."use App\\Filament\\Actions\\LogCallAction;\n";

if (! str_contains($source, $use)) {
    fwrite(STDERR, "patch-log-call: import anchor not found\n");
    exit(1);
}

if (! str_contains($source, 'LogCallAction')) {
    $source = str_replace($use, $useWithAction, $source);
}

$anchor = "return Actions::make([\n            EditAction::make()";

if (! str_contains($source, $anchor)) {
    fwrite(STDERR, "patch-log-call: action array anchor not found\n");
    exit(1);
}

if (! str_contains($source, 'LogCallAction::make()')) {
    $source = str_replace(
        $anchor,
        "return Actions::make([\n            LogCallAction::make(),\n            EditAction::make()",
        $source,
    );
}

if (file_put_contents($path, $source) === false) {
    fwrite(STDERR, "patch-log-call: cannot write {$path}\n");
    exit(1);
}

echo "patch-log-call: Log call added to the record action rail\n";
