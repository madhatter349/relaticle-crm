<?php

/**
 * Show the phone number in the People table on a property page.
 *
 * Upstream's related-record column set is name + job title + emails. For an
 * outreach book the number is the point of the row, so phone joins the list.
 *
 * Guarded: if upstream reshuffles this file the build fails instead of quietly
 * shipping a table without the column.
 */
$path = '/var/www/html/app/Filament/Components/Tables/RelatedRecordColumns.php';

$source = file_get_contents($path);

if ($source === false) {
    fwrite(STDERR, "patch-phone-column: cannot read {$path}\n");
    exit(1);
}

$from = '[PeopleField::JOB_TITLE, PeopleField::EMAILS]';
$to = '[PeopleField::JOB_TITLE, PeopleField::EMAILS, PeopleField::PHONE_NUMBER]';

if (! str_contains($source, $from)) {
    fwrite(STDERR, "patch-phone-column: expected column list not found in {$path}\n");
    exit(1);
}

if (file_put_contents($path, str_replace($from, $to, $source, $count)) === false || $count !== 1) {
    fwrite(STDERR, "patch-phone-column: cannot write {$path}\n");
    exit(1);
}

echo "patch-phone-column: phone added to related people tables\n";
