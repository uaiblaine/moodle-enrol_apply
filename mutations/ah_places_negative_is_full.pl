# AH: read a negative places number as a limit rather than as "no limit".
s{return \$places > 0 \? \$places : 0;}{return \$places !== 0 ? \$places : 0;};
