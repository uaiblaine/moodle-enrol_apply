# QA1: the table's base url is built from url_params() alone, so its anchors leave the table for the grid.
s#\$this->baseurl = queue_view::url\(new moodle_url\('/enrol/apply/manage.php', \$this->url_params\(\)\), false\);#\$this->baseurl = new moodle_url('/enrol/apply/manage.php', \$this->url_params());#;
