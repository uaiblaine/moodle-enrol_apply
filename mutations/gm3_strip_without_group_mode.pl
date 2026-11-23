# GM3: the summary strip leaves the course's group mode out.
s#\n\s*\$facts\[\] = get_string\(\n\s*'summarygroupmode',\n\s*'enrol_apply',\n\s*\$this->group_mode_name\(\$this->group_mode\(\(int\) \$instance->courseid\)\)\n\s*\);##;
