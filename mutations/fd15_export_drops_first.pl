# FD15: the privacy export leaves the first decision out.
s#\x27timefirstdecided\x27 => !empty\(\$row->timefirstdecided\)#\x27timefirstdecidedx\x27 => !empty(\$row->timefirstdecided)#;
