# BY: write the filter value whether or not the report has that filter. On a course with a single
# apply method there is none - it is added only where it can narrow - so this leaves a value in
# the reader's preferences that nothing ever reads, and reports success for a scope that did not
# happen.
s#        if \(!array_key_exists\(self::METHOD_FILTER, \$this->get_filter_instances\(\)\)\) \{\n            return false;\n        \}\n\n##s;
