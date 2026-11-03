<?php

/**
 * Put previous/next arrows on the record page, ahead of Log call.
 *
 * Guarded: both the import and the array insertion must land, or the build
 * fails instead of shipping arrows that go nowhere.
 */
$path = '/var/www/html/app/Filament/Concerns/HasRecordPageLayout.php';

$source = file_get_contents($path);

if ($source === false) {
    fwrite(STDERR, "patch-record-nav: cannot read {$path}\n");
    exit(1);
}

$use = "use App\\Filament\\Actions\\LogCallAction;\n";

if (! str_contains($source, $use)) {
    fwrite(STDERR, "patch-record-nav: Log call import missing — run the log-call patch first\n");
    exit(1);
}

if (! str_contains($source, 'RecordNavAction')) {
    $source = str_replace($use, $use."use App\\Filament\\Actions\\RecordNavAction;\n", $source);
}

$anchor = "return Actions::make([\n            LogCallAction::make(),";

if (! str_contains($source, $anchor)) {
    fwrite(STDERR, "patch-record-nav: action array anchor not found\n");
    exit(1);
}

if (! str_contains($source, 'RecordNavAction::previous()')) {
    $source = str_replace(
        $anchor,
        "return Actions::make([\n            RecordNavAction::previous(),\n            RecordNavAction::next(),\n            LogCallAction::make(),",
        $source,
    );
}

if (file_put_contents($path, $source) === false) {
    fwrite(STDERR, "patch-record-nav: cannot write {$path}\n");
    exit(1);
}

echo "patch-record-nav: previous/next added to the record action rail\n";
