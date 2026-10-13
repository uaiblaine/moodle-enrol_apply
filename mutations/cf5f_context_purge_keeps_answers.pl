# CF5F: purging a course context deletes the records and leaves the answers behind.
s{\n\s*submission::delete_answers\('courseid = :courseid', \['courseid' => \$context->instanceid\]\);}{}s;
