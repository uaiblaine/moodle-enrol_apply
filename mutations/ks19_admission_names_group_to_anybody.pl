# KS19: the admission notice names the key group to every recipient, a mentor and a site-wide
# recipient with no claim on the course's groups included (it is judged by names_group_to(), as the choice is).
s#\$this->names_group_to\(\$admission, \$coursecontext, \(int\) \$recipient->id\) => \$admission->group,#\$admission->group !== '' => \$admission->group,#;
