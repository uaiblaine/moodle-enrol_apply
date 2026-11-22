# RX2: view=table no longer asks for core's table.
s#return self::available\(\) && \$view !== self::TABLE;#return self::available();#;
