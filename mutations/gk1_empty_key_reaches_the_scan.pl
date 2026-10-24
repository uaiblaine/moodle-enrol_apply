# GK1: the empty key is no longer refused before the scan, so it pairs with a keyless group.
s{if \(\$key === '' \|\| !self::enabled\(\$instance\)\) \{}{if (!self::enabled(\$instance)) \{};
