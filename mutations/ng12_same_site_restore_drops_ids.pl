# NG12: a restore on the same site drops the ids too.
s#if \(\$samesite \|\| \$stored === '' \|\|#if (\$stored === '' ||#;
