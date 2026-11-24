# QW17: the wide queue reads the method queues' grid instead of its own.
s#\$stored = queuepreference::read\(null, \$wide\);#\$stored = queuepreference::read();#;
