# NV3: every enrolment method of the course linked as an apply queue.
s#static fn\(stdClass \$instance\): bool => \$instance->enrol === 'apply'#static fn(stdClass \$instance): bool => true#;
