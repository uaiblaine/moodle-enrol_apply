# XA47: the handler answers can_edit() true again, for anybody.
s#(public function can_edit\(field_controller \$field, int \$instanceid = 0\): bool \{\n\s*)return false;#$1return true;#s;
