# QE6: the preference service never asks the queue's own question.
s#\n\s*queueengine::require_listing\(\$params\['enrolid'\]\);\n#\n#;
