# QW2: the wide queue is located in the front page's course context instead of the system's.
s#return \[null, context_system::instance\(\)\];#return [null, context_course::instance(SITEID)];#;
