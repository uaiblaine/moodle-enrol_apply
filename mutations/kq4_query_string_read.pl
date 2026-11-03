# KQ4: the key is read from the query string too, so a link can test one with no try spent.
s#return empty\(\$submission\['_qf__' \. \$this->_formname\]\) \? \[\] : \$submission;#return array_merge(\$_GET, \$submission);#;
