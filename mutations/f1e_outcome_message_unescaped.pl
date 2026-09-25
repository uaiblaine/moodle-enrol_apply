# F1E: put the decider's message into the HTML body unescaped. It is free text somebody typed,
# landing in fullmessagehtml beside the administrator's trusted template.
s{\$content \.= '<br><br>' \. nl2br\(s\(\$outcome\)\);}{\$content .= '<br><br>' . nl2br(\$outcome);};
