# Z: read a negative cap as a limit rather than as "uncapped", which turns an instance whose
# customint3 db/upgrade.php nulled into a permanently full one.
s{return \$limit > 0 \? \$limit : 0;}{return \$limit !== 0 ? \$limit : 0;};
