# XA58: a required question is checked for an answer only when it has a condition.
s#if \(\n(\s+)\$field->get_configdata_property\('required'\)#if (\n$1isset(\$conditions[(int) \$id])\n$1&& \$field->get_configdata_property('required')#s;
