# RB6: the method column is added for a course with a single apply method.
s#if \(count\(\$this->instances\(\)\) > 1\) \{(\s*)\$this->add_column\(\$this->method_column\(\)\);#if (count(\$this->instances()) > 0) {$1\$this->add_column(\$this->method_column());#s;
