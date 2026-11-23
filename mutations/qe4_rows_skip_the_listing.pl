# QE4: the rows service never asks the queue's own question.
s#\n\s*queueengine::require_listing\(\$params\['enrolid'\]\);\n#\n#;
