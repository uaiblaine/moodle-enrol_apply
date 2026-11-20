# RS3: copy the archived chosen group id through unmapped.
s{\? \(int\) \$this->get_mappingid\('group', \$data->chosengroupid \?\? 0\)}{? (int) (\$data->chosengroupid ?? 0)};
