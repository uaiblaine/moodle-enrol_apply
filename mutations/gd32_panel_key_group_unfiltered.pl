# GD32: "Already in this course" names the key's group to a reader it is hidden from.
s#if \(\$group \&\& \$namesgroups \&\& \\enrol_apply\\local\\groupvisibility::visible_to\(\$group, \(int\) \$USER->id\)\) \{#if (\$group) {#;
