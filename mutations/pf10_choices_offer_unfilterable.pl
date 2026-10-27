# PF10: offer a filter field the queue cannot filter by.
s{if \(self::token\(\$name\) === '' \|\| self::shape\(\$name\) === null\) \{}{if (self::token(\$name) === '') \{}s;
