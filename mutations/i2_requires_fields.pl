# I2: remove the file-scope requires of the second test file that names the backup classes as
# coverage targets. The suite still loads them through tests/backup_test.php, so only a check that
# reads each file's own source can see it.
s|\nrequire_once\(\$CFG->dirroot \. '/backup/util/includes/backup_includes\.php'\);\nrequire_once\(\$CFG->dirroot \. '/backup/util/includes/restore_includes\.php'\);\nrequire_once\(\$CFG->dirroot \. '/enrol/apply/backup/moodle2/backup_enrol_apply_plugin\.class\.php'\);\nrequire_once\(\$CFG->dirroot \. '/enrol/apply/backup/moodle2/restore_enrol_apply_plugin\.class\.php'\);\n|\n|s;
