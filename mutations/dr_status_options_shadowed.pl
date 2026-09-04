# DR: let the per-field loop reassign the status select's option list, which is the defect that
# shipped: the status control published the LAST offered field's vocabulary, and the value it then
# posted is one integer_filter refuses - so the as-you-type refresh threw and the search looked
# broken. Invisible on a site with no field filter ticked, which is every site by default.
s{\$fieldoptions = \[\];}{\$statusoptions = [];\n            \$fieldoptions = [];}s;
