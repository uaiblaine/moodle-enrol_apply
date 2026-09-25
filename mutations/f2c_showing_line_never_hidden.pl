# F2C: never hide the "Showing" line, so a page with no rows keeps an empty but visible line.
s#\{\{\^hasshowing\}\} d-none\{\{/hasshowing\}\}##s;
