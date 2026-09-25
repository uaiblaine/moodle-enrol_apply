# F5B: copy the decided role through instead of mapping it. On a same-site restore a role maps to
# itself, so only a role that no longer exists can tell the two apart.
s{'decidedrole' => \(int\) \$this->get_mappingid\('role', \$data->decidedrole \?\? 0\),}{'decidedrole' => (int) (\$data->decidedrole ?? 0),}s;
