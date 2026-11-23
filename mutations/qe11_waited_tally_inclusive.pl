# QE11: the waited facet counts a row exactly on a threshold, which the filter does not list.
s#WHEN ue\.timecreated < :facetwaited#WHEN ue.timecreated <= :facetwaited#;
