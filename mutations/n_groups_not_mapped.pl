# N: carry the archived group ids through without mapping them.
s|'decidedgroups' => \$this->mapped_groups\(\$data->decidedgroups \?\? ''\),|'decidedgroups' => \$data->decidedgroups ?? '',|s;
