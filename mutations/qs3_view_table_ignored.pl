# QS3: the view parameter is ignored, so view=table no longer asks for the table, on any queue.
s#return \$view !== self::TABLE && \$listing->allowed;#return \$listing->allowed;#;
