# QA2: the table's course cell links the course for every reader, a mentor included.
s#(\. \()\$this->links_course\(\)\n#$1true\n#;
