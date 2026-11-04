# KS25: the key group column is sortable again, by the name a withheld cell sits among.
s#(->add_field\("\{\$galias\}\.visibility", 'keygroupvisibility'\)\n            )->set_is_sortable\(false\)#$1->set_is_sortable(true)#;
