# GD24: the "Group chosen" column made sortable, which would place withheld cells among the names.
s#(->add_field\("\{\$calias\}\.visibility", 'chosengroupvisibility'\)\n\s*->set_is_sortable\()false#${1}true#;
