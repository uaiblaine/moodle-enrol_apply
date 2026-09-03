# CJ: let a meter draw past the end of its own bar. Both counts can legitimately exceed their
# limit - an administrator can enrol past the places cap by hand, and the applicant limit can be
# lowered under applications already held - so the overflow is reachable, and it reads as a
# rendering fault rather than as the over-capacity state it is.
s{        return \(int\) min\(100, max\(0, round\(\$value \* 100 / \$total\)\)\);}{        return (int) round(\$value * 100 / \$total);}s;
