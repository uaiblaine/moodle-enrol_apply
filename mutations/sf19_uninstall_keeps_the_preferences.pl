# SF19: the uninstall leaves the user preferences.
s/\n\s*\$DB->delete_records_select\(.user_preferences., "name \$insql", \$params\);//;
