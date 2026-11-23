# NG7: the group marker is read anywhere in the list, not only first.
s#if \(\$items\[0\] === self::GROUP\) \{#if (in_array(self::GROUP, \$items, true)) {#;
