# QM5: a sort key outside the view's vocabulary is kept, and echoed, as sent.
s#if \(!in_array\(\$sort, self::METHOD_SORTS, true\)\) \{#if (\$sort === '') {#;
