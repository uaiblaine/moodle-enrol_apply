# QT1: the allowed condition is dropped from wanted(), so a refused listing (an ordinary user's id-less page) mounts the grid.
s#return \$view !== self::TABLE && \$listing->allowed;#return \$view !== self::TABLE;#;
