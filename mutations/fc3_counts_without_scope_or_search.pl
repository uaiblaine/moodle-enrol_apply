# FC3: the counts keep the conditions appended to the table (the scope, the search).
s#foreach \(\$this->narrowings as \[\$sql, \$narrowparams\]\) \{#foreach ([] as [\$sql, \$narrowparams]) {#;
