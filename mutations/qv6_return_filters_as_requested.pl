# QV6: the page a decision lands on carries the filters as the request had them, not as the queue's table applied them.
s#\$params = \$table->url_params\(\);#\$params = ['id' => \$enrolid, 'search' => \$search, 'status' => \$status] + array_column(\$initial['filters'], 'value', 'name');#;
