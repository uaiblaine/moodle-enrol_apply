# GC17: the chosen group is named whatever the applicant may now see.
s#if \(!\$group \|\| !groupvisibility::visible_to\(\$group, \$chosen->userid\)\) \{#if (!\$group) {#;
