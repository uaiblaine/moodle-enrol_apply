# GD11: the managers' notice is routed by the key group alone.
s#\$keyroute \? max\(0, \$keygroupid\) : \$chosen,#max(0, \$keygroupid),#;
