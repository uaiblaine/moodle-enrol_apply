# SR8: restore an old envelope's keys as the archive carries them.
s#\$key = \$this->restored_answer_key\(\(string\) \$entry\[.key.\]\);#\$key = (string) \$entry[\x27key\x27];#;
