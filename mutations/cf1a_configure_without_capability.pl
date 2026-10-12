# CF1A: anybody who can reach an apply instance's handler configures its questions.
s{return \$context !== null && has_capability\('enrol/apply:config', \$context\);}{return \$context !== null;}s;
