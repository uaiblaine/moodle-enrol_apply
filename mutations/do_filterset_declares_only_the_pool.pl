# DO: declare only the fields the administrator ticked. get.php validates a filter NAME before it
# constructs the table and before validate_context()/has_capability(), so the declared set becomes
# readable by anyone logged in - one name at a time, from which exception comes back.
s{\$names = array_unique\(array_merge\(\\core_user\\fields::get_identity_fields\(null\), queuefilter::pool\(\)\)\);}{\$names = queuefilter::pool();}s;
