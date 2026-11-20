# RV19: a route group (the key's, or the chosen one) deleted while the application waited is handed to groups_add_member(), which throws.
s#return array_values\(array_filter\(\n\s*\$this->key_route_groups\(\$instance, \$routegroup\),\n.*?\n\s*\)\);#return \$this->key_route_groups(\$instance, \$routegroup);#s;
