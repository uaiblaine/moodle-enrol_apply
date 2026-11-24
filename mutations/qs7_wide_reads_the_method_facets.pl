# QS7: the site-wide queue's first paint reads the method queue's facets, so its method and grouped are lost.
s#foreach \(\$wide \? self::WIDE_FACETS : self::FACETS as \$name\) \{#foreach (self::FACETS as \$name) {#;
