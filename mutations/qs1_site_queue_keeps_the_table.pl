# QS1: the queues that span courses are refused the grid, as before W3: only a method's listing mounts it.
s#return \$view !== self::TABLE && \$listing->allowed;#return \$view !== self::TABLE && \$listing->allowed && \$listing->instance !== null;#;
