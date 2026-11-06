# RP2: attention starts past four fifths instead of at them (RP-D3, the queue meter's rule).
s|        return \$taken \* 5 >= \$total \* 4 \? self::SIGNAL_WARN : self::SIGNAL_OK;|        return \$taken * 5 > \$total * 4 ? self::SIGNAL_WARN : self::SIGNAL_OK;|;
