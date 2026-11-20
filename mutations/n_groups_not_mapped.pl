# N: carry the archived group ids through without mapping them.
s|'decidedgroups' => \$pending \? '' : \$this->mapped_groups\(\$data->decidedgroups \?\? ''\),|'decidedgroups' => \$pending ? '' : (\$data->decidedgroups ?? ''),|s;
