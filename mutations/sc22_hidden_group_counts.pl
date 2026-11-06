# SC22: a group hidden from its own member scopes them into it.
s#if \(groupvisibility::visible_to\(\$group, \$userid\)\) \{#if (true) {#;
