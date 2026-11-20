# DR: let the per-field loop write into the status control's values, which is the defect that
# shipped: the status control published the LAST offered field's vocabulary, and the value it then
# posted is one integer_filter refuses - so the as-you-type refresh threw and the search looked
# broken. Invisible on a site with no field filter ticked, which is every site by default.
#
# Repointed in Q2, when the status select became the segmented control's cells: the shared variable
# is spelled as a reference, so every field's options land in the cells as the shared name did.
s{\$fieldoptions = \[\];}{\$fieldoptions = [];\n            \$statuscells = &\$fieldoptions;}s;
