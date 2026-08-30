# F: point the icon at the instance queue instead of the review page.
s{new moodle_url\('/enrol/apply/manage\.php', \['userenrol' => \$ue->id\]\)}{new moodle_url('/enrol/apply/manage.php', ['id' => \$ue->enrolid])}s;
