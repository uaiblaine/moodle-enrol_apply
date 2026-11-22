# PG4: the unique last key is dropped when the reader sorts.
s#return \(\$sort !== '' \? "\{\$sort\}, " : ''\) \. 'results\.id';#return \$sort !== '' ? \$sort : 'results.id';#;
