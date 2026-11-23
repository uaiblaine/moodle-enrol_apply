# RX2: view=table no longer asks for core's table.
s#return \$view !== self::TABLE;#return true;#;
