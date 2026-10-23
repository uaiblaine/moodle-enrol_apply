# KA1: the field locks one wrong key after the limit.
s{->count >= self::LIMIT;}{->count > self::LIMIT;};
