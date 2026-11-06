# EV13: the groups array is declared mappable as one group, which core's log restore would hand
# to get_mappingid() as a single value.
s#'groups' => \['db' => 'groups', 'restore' => base::NOT_MAPPED\]#'groups' => ['db' => 'groups', 'restore' => 'group']#;
