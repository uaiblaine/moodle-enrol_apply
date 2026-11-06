# RP1: a count at its limit reads as attention instead of over the limit (RP-D3).
s|        if \(\$taken >= \$total\) \{\n            return self::SIGNAL_OVER;|        if (\$taken > \$total) {\n            return self::SIGNAL_OVER;|s;
