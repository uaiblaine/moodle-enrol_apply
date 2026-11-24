# QS2: the mentee condition is dropped, so a mentor's id-less page (and a refused reader's) mounts the grid before W5.
s#return \$view !== self::TABLE && \$listing->mentees === null;#return \$view !== self::TABLE;#;
