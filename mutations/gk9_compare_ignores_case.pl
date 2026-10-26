# GK9: compare keys case-insensitively, so "turma-b" opens a group whose key is "Turma-B" and a
# key's guessing space shrinks to one case.
s#            if \(hash_equals\(\(string\) \(\$group->enrolmentkey \?\? ''\), \$key\)\) \{#            if (strcasecmp((string) (\$group->enrolmentkey ?? ''), \$key) === 0) {#;
