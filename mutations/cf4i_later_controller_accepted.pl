# CF4I: a later question may control an earlier one.
s{if \(!array_key_exists\(\$controllerid, self::controllers\(\$fields, \$fieldid\)\)\) \{\n\s*return 'conditionerror_later';\n\s*\}\n}{}s;
