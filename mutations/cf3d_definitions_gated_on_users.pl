# CF3D: the definitions are only written to the archive when users are included.
s{\$category->set_source_sql\(}{if (\$this->task->get_setting_value('users')) \$category->set_source_sql(}s;
