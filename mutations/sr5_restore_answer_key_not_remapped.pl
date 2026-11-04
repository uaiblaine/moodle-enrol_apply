# SR5: keep an answer to the instance's own question under its archived id.
s#return answers::key\(\$newid\);#return \$key;#;
