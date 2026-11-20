# SD4: no membership of the plugin scopes anybody, a deciding one included.
s#\(gm\.component <> :gmcomponent OR \{\$ownmembership\}\)#(gm.component <> :gmcomponent AND {\$ownmembership})#;
