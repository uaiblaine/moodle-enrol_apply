# IO2: parse an option by casting, which reads '1abc', true and [1] as 1.
s{    protected static function parse\(\$value\): \?int \{\n}{    protected static function parse(\$value): ?int \{\n        if (is_scalar(\$value) || is_array(\$value)) \{\n            return (int) \$value;\n        \}\n};
