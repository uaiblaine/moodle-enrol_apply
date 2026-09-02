# CD: build the base url without the scope. Paging and sorting emit real anchors from it, so the
# second page of one method's queue would land on the site-wide one - silently, and only for the
# operator who turned a page.
s{        \$this->baseurl = \$this->scope->url;}{        \$this->baseurl = new moodle_url\('/enrol/apply/manage\.php'\);}s;
