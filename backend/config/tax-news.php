<?php

return [
    'feeds' => [
        ['source' => 'ATO Tax professionals', 'url' => 'https://www.ato.gov.au/rss.aspx?category=2216'],
        ['source' => 'ATO Individuals', 'url' => 'https://www.ato.gov.au/rss.aspx?category=1470'],
        ['source' => 'ATO Media releases', 'url' => 'https://www.ato.gov.au/rss.aspx?category=1837'],
        ['source' => 'Treasury', 'url' => env('TAX_NEWS_TREASURY_FEED', '')],
    ],
];
