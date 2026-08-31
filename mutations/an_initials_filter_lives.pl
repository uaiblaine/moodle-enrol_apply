# AN: rename the get_sql_where() override away, so a stored initials preference filters the queue
# again with no control anywhere on the page able to explain the rows that vanished.
s!    public function get_sql_where\(\) \{!    public function get_sql_where_disabled() \{!s;
