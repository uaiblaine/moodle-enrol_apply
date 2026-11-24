# QW1: locate() reads every id at or below 0 as the wide queue, so a negative id opens it too.
s#if \(\$enrolid === 0\) \{\n(\s*)return \[null, context_system::instance\(\)\];#if (\$enrolid <= 0) {\n$1return [null, context_system::instance()];#;
