# QE8: a sort key is honoured whether or not the reader's catalogue offers it as sortable.
s#if \(!isset\(\$catalogue\[\$sort\]\) \|\| !\$catalogue\[\$sort\]\['sortable'\]\) \{#if (\$sort === '') {#;
