# AK: make the queue's comment header ignore the instance's own wording, so the question the
# applicant answered and the heading above the answers drift apart again. The instance is now
# reached through the resolved scope rather than handed in, which changes the expression and
# nothing about what this gate is for.
s{\$headers\[\] = \$this->scope->instance === null\n            \? get_string\('applycomment', 'enrol_apply'\)\n            : commentlabel::custom\(\$this->scope->instance\);}{\$headers[] = get_string('applycomment', 'enrol_apply');}s;
