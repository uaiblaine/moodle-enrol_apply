# CX: build the base url from the scope alone, as it did before the filters existed. Page one of a
# filtered queue is correct and page two is the unfiltered queue - and so is every sort. No
# row-level assertion can see it, which is the point of asserting the emitted url instead.
s{        \$this->baseurl = new moodle_url\('/enrol/apply/manage\.php', \$this->url_params\(\)\);}{        \$this->baseurl = \$this->scope->url;}s;
