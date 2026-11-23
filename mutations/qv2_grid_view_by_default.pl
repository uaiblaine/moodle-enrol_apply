# QV2: the grid view mounts without view=grid, replacing the table that decides before stage 4.
s#return \$enrolid > 0 && \$view === self::GRID;#return \$enrolid > 0;#;
