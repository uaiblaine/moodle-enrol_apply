# O: read the two new elements bare, as an older archive cannot supply them.
s|\$this->mapped_groups\(\$data->decidedgroups \?\? ''\)|\$this->mapped_groups(\$data->decidedgroups)|s;
s|\$this->get_mappingid\('role', \$data->decidedrole \?\? 0\)|\$this->get_mappingid('role', \$data->decidedrole)|s;
