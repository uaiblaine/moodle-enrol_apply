# QS3: the view parameter is ignored, so view=table no longer asks for the table, on the method queue or the site-wide one.
s#return \$view !== self::TABLE && \$listing->mentees === null;#return \$listing->mentees === null;#;
