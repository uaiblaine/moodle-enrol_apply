# F2B: name a range whatever the page holds, so an empty queue reads "Showing 1-0 of 0".
s{\$showing = \$from > \$to \? '' : get_string\(}{\$showing = get_string(}s;
