# QE9: every question the method asks is a column, whatever the reader may see.
s#static fn\(string \$key\): bool => formatter::shows\(\$key, \$visible\),#static fn(string \$key): bool => true,#;
