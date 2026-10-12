# CF4T: save() trusts its caller again, so a question of another instance can be given a condition.
s#if \(\$problem !== ..\) \{\n\s*throw new \\invalid_parameter_exception\(self::problem_message\(\$problem\)\);\n\s*\}##s;
