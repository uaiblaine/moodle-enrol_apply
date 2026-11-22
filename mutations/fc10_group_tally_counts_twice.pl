# FC10: a record whose application group is also in its joined list is counted twice there.
s#foreach \(array_unique\(\$groups\) as \$groupid\)#foreach (\$groups as \$groupid)#;
