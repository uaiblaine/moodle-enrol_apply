# NG6: the people picked by name beside the group marker are not told.
s#foreach \(\$setting->ids as \$uid\) \{#foreach ((\$setting->group ? [] : \$setting->ids) as \$uid) {#;
