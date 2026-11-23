# QV2: the view parameter is ignored, so view=table no longer asks for the table on the method queue.
s#return \$enrolid > 0 && \$view !== self::TABLE;#return \$enrolid > 0;#;
