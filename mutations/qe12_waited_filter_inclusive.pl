# QE12: the waited filter lists a row exactly on its threshold, which the facet does not count.
s#'ue\.timecreated < :waitedcut'#'ue.timecreated <= :waitedcut'#;
