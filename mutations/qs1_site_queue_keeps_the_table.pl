# QS1: the site-wide queue is refused the grid, as before W3: only a method's listing mounts it.
s#return \$view !== self::TABLE && \$listing->mentees === null;#return \$view !== self::TABLE && \$listing->mentees === null && \$listing->instance !== null;#;
