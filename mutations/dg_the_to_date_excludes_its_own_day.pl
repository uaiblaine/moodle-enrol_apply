# DG: make the upper date bound that day's own midnight rather than the next one. Every application
# made on the "to" date then falls outside the range the operator asked for - the commonest thing
# anybody types into a date range is the same day twice, and it would return nothing.
s{        return \[self::midnight\(\$from, 0\), self::midnight\(\$to, 1\)\];}{        return [self::midnight(\$from, 0), self::midnight(\$to, 0)];}s;
