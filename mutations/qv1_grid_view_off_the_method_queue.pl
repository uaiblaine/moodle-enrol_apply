# QV1: the grid view mounts whatever the id, so the site-wide and mentee queues (id 0) would take it.
s#return \$enrolid > 0 && \$view === self::GRID;#return \$view === self::GRID;#;
