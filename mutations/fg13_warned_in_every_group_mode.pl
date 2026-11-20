# FG13: the C-D27 warnings are given whatever the group mode.
s#(\$courseid = \(int\) \$instance->courseid;\n\s*)if \(!\$this->separate_groups\(\$courseid\)\) \{\n\s*return \$warnings;\n\s*\}#$1#;
