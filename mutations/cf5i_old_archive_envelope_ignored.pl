# CF5I: an archive carrying the old JSON envelope restores the record without its answers.
s{\n\s*if \(isset\(\$data->userinfodata\) && is_string\(\$data->userinfodata\)\) \{\n.*?\n\s*submission::write_answers\(\$id, \$entries\);\n\s*\}\n}{\n}s;
