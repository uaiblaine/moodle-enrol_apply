# QV5: the page size is read from the address whatever its size, for the first paint and for the page a decision lands on.
s#'perpage' => in_array\(\$perpage, queueengine::PERPAGES, true\) \? \$perpage : queueengine::PERPAGE,#'perpage' => \$perpage,#;
