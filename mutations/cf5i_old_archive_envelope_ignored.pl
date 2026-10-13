# CF5I: an archive carrying the old JSON envelope restores the record without its answers.
s{\n\s*if \(isset\(\$data->userinfodata\) && is_string\(\$data->userinfodata\)\) \{\n\s*submission::write_answers\(\$id, submission::parse_envelope\(\$data->userinfodata\)\);\n\s*\}\n}{\n}s;
