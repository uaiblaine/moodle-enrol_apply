# NI1: add_instance() completes nothing, so a method created by core's generic page has NULL settings.
s#return parent::add_instance\(\$course, \(array\) \$fields \+ \$defaults\);#return parent::add_instance(\$course, \$fields);#;
