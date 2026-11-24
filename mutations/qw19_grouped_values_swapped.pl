# QW19: the grouped filter's two values are swapped, so "yes" selects what "no" should.
s#(\[\$where\] = \$value) === (self::GROUPED_NO\n)#$1 !== $2#;
