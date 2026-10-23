# KA2: an entry stays valid whatever its age, so the lock lasts as long as the cache does.
s{ && \$now - \(int\) \$stored\['since'\] < self::WINDOW\)}{)};
