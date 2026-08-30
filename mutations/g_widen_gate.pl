# G: widen the icon's gate to can_manage_application(), the alternative the docblock forbids.
# Anchored on "return $actions;", which is unique to the icon method - the identical
# has_capability line in get_bulk_operations() returns [] instead.
s{(\n        if \(!)has_capability\('enrol/apply:manageapplications', \$manager->get_context\(\)\)(\) \{\n            return \$actions;\n        \}\n)}{${1}\$this->can_manage_application\(\(int\) \$manager->get_course\(\)->id, \(int\) \$ue->userid\)${2}}s;
