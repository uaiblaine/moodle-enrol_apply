# C: drop the timeend half of the shared object predicate.
s{\n        \$timeend = \(int\) \$userenrolment->timeend;\n\n        return \$timeend === 0 \|\| \$timeend > time\(\);\n}{\n        return true;\n}s;
