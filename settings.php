<?php

// To activate debug output
define('DEVELOPING', true);

// We need Curl to send a reasonable user agent string with each request
define('SCR_USERAGENT',
       "Mozilla/5.0 (X11; Linux x86_64; rv:45.0) Gecko/20100101 Firefox/45.0"
);

define('JOBS_URLBASE', "https://www.indeed.com");

// Location strings for parameterizing the request URL.
// Change to suit your needs, even set them empty/NULL for universal search.
define('US_CITY', 'Albuquerque');
define('US_STATE', 'NM');

// Despite the location strings defined above, the Indeed results tend to include
// a few job postings from other places (like "United States" - not very helpful).
// The following will be used by the script to filter further.
// Add/modify to suit your purposes.
$myLocalPlaces = array(
  'Albuquerque',
  'Kirtland'
);

$REGULAR_PATHMAP = array(
  'type' => 'regular',
  'jobrows' => '//td/div[php:function("containsToken", @class, "row")' .
               ' and php:function("containsToken", @class, "result")]',
  'posttitle' => 'h2[@class="jobtitle"]/a',
  'postcompany' => 'span[@class="company"]',
  'postlocation' => 'span[@class="location"]',
  'postsummary' => 'table/tr/td/div[@class=""]/span[@class="summary"]',
  'post_age' => 'table/tr/td/div/div[@class="result-link-bar"]/span[@class="date"]'
);

$SPONSORED_PATHMAP = array(
  'type' => 'sponsored',
  'jobrows' => '//div/div[php:function("containsToken", @class, "row")' .
               ' and php:function("containsToken", @class, "result")]',
  'posttitle' => 'a[@data-tn-element="jobTitle"]',
  'postcompany' => 'div[@class="sjcl"]/span[@class="company"]',
  'postlocation' => 'div[@class="sjcl"]/span[@class="location"]',
  'postsummary' => 'div[@class=""]/table/tr/td/span[@class="summary"]',
  'post_age' => NULL
);


?>
