<?php

/**
 * Show phone numbers as "(718) 993-2280" instead of raw digits.
 *
 * The package only formats a stored number when it carries an extension
 * (";ext="), otherwise it prints the stored string verbatim. Both the infolist
 * entry and the table column render through displayText(), so this one method
 * is the whole display path.
 *
 * Storage stays canonical (E.164, "+17189932280"): tel: links, dedupe matching
 * and re-imports all read the same value. Only the presentation changes.
 *
 * Guarded: the build fails if upstream rewrites the method.
 */
$path = '/var/www/html/vendor/relaticle/custom-fields/src/Services/Phone/CountryPhoneService.php';

$source = file_get_contents($path);

if ($source === false) {
    fwrite(STDERR, "patch-phone-format: cannot read {$path}\n");
    exit(1);
}

$from = "    public function displayText(string \$stored): string\n"
    ."    {\n"
    ."        return str_contains(\$stored, ';') ? \$this->formatForDisplay(\$stored) : \$stored;\n"
    ."    }";

$to = "    public function displayText(string \$stored): string\n"
    ."    {\n"
    ."        if (blank(\$stored)) {\n"
    ."            return '';\n"
    ."        }\n"
    ."\n"
    ."        try {\n"
    ."            \$formatted = (new PhoneNumber(Str::before(\$stored, ';'), 'US'))->formatNational();\n"
    ."\n"
    ."            if (str_contains(\$stored, 'ext=')) {\n"
    ."                \$formatted .= ' ext. '.Str::after(\$stored, 'ext=');\n"
    ."            }\n"
    ."\n"
    ."            return \$formatted;\n"
    ."        } catch (Throwable) {\n"
    ."            return \$this->formatForDisplay(\$stored);\n"
    ."        }\n"
    ."    }";

if (! str_contains($source, $from)) {
    fwrite(STDERR, "patch-phone-format: displayText body not found — upstream changed\n");
    exit(1);
}

if (file_put_contents($path, str_replace($from, $to, $source, $count)) === false || $count !== 1) {
    fwrite(STDERR, "patch-phone-format: cannot write {$path}\n");
    exit(1);
}

echo "patch-phone-format: phone numbers render as (718) 993-2280\n";
