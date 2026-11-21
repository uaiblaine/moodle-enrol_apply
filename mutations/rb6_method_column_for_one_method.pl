# RB6: the method column is added for a course with a single apply method.
s#if \(count\(\$this->instances\(\)\) > 1\) \{(\s*)\$catalogue\['method'\]#if (count(\$this->instances()) > 0) {$1\$catalogue['method']#s;
