# PG5: the reader's sort is dropped from the page's ids.
s#return \(\$sort !== '' \? "\{\$sort\}, " : ''\) \. 'results\.id';#return 'results.id';#;
