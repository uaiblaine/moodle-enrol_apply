# DF: let a field filter's value reach LIKE unescaped. A percent sign an applicant typed becomes a
# wildcard, so a filter hunting one institution is handed every institution whose name shares a
# prefix - with nothing on the page to say why.
# Re-pointed when the stage-1 headless queue split the listing's narrowing into one entry per filter.
s{\[\$name => '%' \. \$DB->sql_like_escape\(\$value\) \. '%'\],}{[\$name => '%' . \$value . '%'],}s;
