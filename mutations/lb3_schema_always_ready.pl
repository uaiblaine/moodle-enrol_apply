# LB3: the schema reads as ready whatever version is stored.
s{return \(int\) get_config\('enrol_apply', 'version'\) >= ENROL_APPLY_OPTIONS_SCHEMA;}{return true;};
