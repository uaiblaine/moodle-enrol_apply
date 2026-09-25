# F3B: register the admin node without the deciding capability, so it falls back to moodle/site:config.
s#new moodle_url\('/enrol/apply/manage\.php'\),\n\s*'enrol/apply:manageapplications'\n#new moodle_url('/enrol/apply/manage.php')\n#s;
