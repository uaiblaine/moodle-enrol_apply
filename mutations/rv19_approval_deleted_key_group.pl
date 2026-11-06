# RV19: a key group deleted while the application waited is handed to groups_add_member(), which throws.
s#return array_values\(array_filter\(\n\s*\$this->key_route_groups\(\$instance, \$key->groupid\),\n.*?\n\s*\)\);#return \$this->key_route_groups(\$instance, \$key->groupid);#s;
