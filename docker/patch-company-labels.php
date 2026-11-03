<?php

/**
 * Rename "Companies" to "Properties" in the panel.
 *
 * Relaticle drives its Filament labels from lang files, so this rewrites the
 * three strings that surface in navigation, page headings and the "New …"
 * button. It is deliberately narrow: field labels, onboarding copy and the
 * import wizard's own entity names still say company/companies, and upstream
 * ships them wherever it likes. Guarded so an upstream move fails the build
 * instead of silently reverting the rename.
 */
$path = '/var/www/html/lang/en/filament/resources/company.php';

$source = file_get_contents($path);

if ($source === false) {
    fwrite(STDERR, "patch-company-labels: cannot read {$path}\n");
    exit(1);
}

$replacements = [
    "'label' => 'company'," => "'label' => 'property',",
    "'plural_label' => 'companies'," => "'plural_label' => 'properties',",
    "'navigation_label' => 'Companies'," => "'navigation_label' => 'Properties',",
];

$patched = $source;

foreach ($replacements as $from => $to) {
    if (! str_contains($patched, $from)) {
        fwrite(STDERR, "patch-company-labels: expected {$from} in {$path}\n");
        exit(1);
    }

    $patched = str_replace($from, $to, $patched);
}

if (file_put_contents($path, $patched) === false) {
    fwrite(STDERR, "patch-company-labels: cannot write {$path}\n");
    exit(1);
}

echo "patch-company-labels: company -> property\n";
