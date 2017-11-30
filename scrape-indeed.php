<?php

include "settings.php";

function debugOut($msg) {
  if (!DEVELOPING) return;
  echo($msg);
}

function abortWithWarning() {
  print <<< FAILEDSCRAPE

  Some expected details were not found in the results page.
  This indicates that the HTML structure of an Indeed.com result page
  has been changed since the last time this script was modified.
  The author would be thankful if you would report this at:
  https://github.com/mmraff/scrape-indeed/issues

FAILEDSCRAPE;
  exit;
}

// This function expects a DOMNodeList as 1st arg (designed to be passed an
// element attribute, such as @class, in an XPath query expression).
// The version of XPath implemented for PHP 5.6 does not include the XQuery
// function 'contains-token', so this is a work-around.
function containsToken($nodes, $token) {
  if (empty($nodes)) return FALSE;
  foreach($nodes as $node) {
    $result = preg_match("/\b".$token."\b/", $node->nodeValue);
    if ($result === FALSE) {
      echo "Bad preg_match call: " . preg_last_error() . "\n";
      exit;
    }
    return $result ? TRUE : FALSE;
  }
}

// Credit for the following 2 functions goes to Jacob Ward, author of
// Instant PHP Web Scraping (Packt 2013).
function curlGet($url) {
  $cookie = 'cookie.txt';
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
  curl_setopt($ch, CURLOPT_FAILONERROR, TRUE); // fail *silently* on error
  curl_setopt($ch, CURLOPT_COOKIESESSION, TRUE);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
  curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
  curl_setopt($ch, CURLOPT_USERAGENT, SCR_USERAGENT);
  curl_setopt($ch, CURLOPT_URL, $url);
  $results = curl_exec($ch);
  return $results;
}

function returnXPathObject($item) {
  $xmlPageDom = new DomDocument();
  @$xmlPageDom->loadHTML($item);
  $xmlPageXPath = new DOMXPath($xmlPageDom);
  $xmlPageXPath->registerNamespace("php", "http://php.net/xpath");
  $xmlPageXPath->registerPHPFunctions("containsToken");
  return $xmlPageXPath;
}

function getValueByXPath($path, $xpathObj, $context, $desc) {
  $result = NULL;
  $qres = $xpathObj->query($path, $context);
  if ($qres->length < 1) {
    debugOut("Failed to find $desc\n");
  }
  else {
    $result = trim($qres->item(0)->nodeValue);
  }
  return $result;
}

function isLocalJob($location) {
  global $myLocalPlaces;
  $placeCount = count($myLocalPlaces);
  for ($i = 0; $i < $placeCount; $i++) {
    if (strpos($location, $myLocalPlaces[$i]) !== FALSE)
      return TRUE;
  }
  return FALSE;
}

$sponsoredJobs = array();

// The workhorse.
function scrapeJobs($currPageXPath, $pathMap, &$jobs) {

  $jobPageRows = $currPageXPath->query($pathMap['jobrows']);
  if ($jobPageRows->length < 1) {
    debugOut("No " . $pathMap['type'] . " job listings!?\n");
  }

  for ($i = 0; $i < $jobPageRows->length; $i++) {
    $currRow = $jobPageRows->item($i);

    // Position title --------------------------
    // Note that the title element is a link; we want both the text content
    // and the HREF, which points to the full description page.
    $qres = $currPageXPath->query($pathMap['posttitle'], $currRow);
    if ($qres->length < 1) {
      debugOut("Failed to find 'jobtitle' h2 for " . $pathMap['type'] .
               " listing #" . i+1 . "\n");
      abortWithWarning();
    }
    $jobUrl = $qres->item(0)->attributes->getNamedItem('href')->nodeValue;

    $jobDetails = array();
    $jobDetails['title'] = trim($qres->item(0)->nodeValue);

    // Company name ----------------------------
    $jobDetails['company'] = getValueByXPath(
      $pathMap['postcompany'],
      $currPageXPath, $currRow,
      "company name (" . $jobDetails['title'] . ")"
    );
    if (!$jobDetails['company']) abortWithWarning();

    // Company location ------------------------
    $jobDetails['location'] = getValueByXPath(
      $pathMap['postlocation'],
      $currPageXPath, $currRow,
      "company location (" . $jobDetails['title'] . ", " . $jobDetails['company'] . ")"
    );
    if (!$jobDetails['location']) abortWithWarning();
    if (!isLocalJob($jobDetails['location'])) continue;

    // At this point, we're sure we have enough information to look up the
    // listing in our current set of jobs, and determine if it's a duplicate.
    // If it's a sponsored listing, we construct a unique key from three fields;
    // if a regular listing, we just use the URL.
    $postKey = ($pathMap['type'] == 'sponsored') ?
               "{$jobDetails['title']}|{$jobDetails['company']}|{$jobDetails['location']}" :
               $jobUrl;

    // Skip duplicate listing
    if (array_key_exists($postKey, $jobs)) continue;

    // Position description (extract) ----------
    $jobDetails['extract'] = getValueByXPath(
      $pathMap['postsummary'],
      $currPageXPath, $currRow,
      "job summary (" . $jobDetails['title'] . ", " . $jobDetails['company'] . ")"
    );
    if (!$jobDetails['extract']) abortWithWarning();

    // How long since posted -------------------
    if ($pathMap['post_age']) {
      $postAge = getValueByXPath(
        $pathMap['post_age'],
        $currPageXPath, $currRow,
        "post age (" . $jobDetails['title'] . ", " . $jobDetails['company'] . ")"
      );
      // Some postings don't have a post age value (the sponsored ones),
      // so be forgiving.
      if ($postAge) {
        $jobDetails['posted'] = $postAge;
      }
    }

    // If we're currently scraping a sponsored job, we must include the URL as
    // a property, because we don't use it as the key of the listing like we
    // do for regular listings.
    if ($pathMap['type'] == 'sponsored') {
      $jobDetails['url'] = $jobUrl;
    }
    $jobs[$postKey] = $jobDetails;
  }

  return $jobs;
} // End function scrapeJobs

/*
 *** MAIN SCRIPT ***************************************
 */
// 1. Construct the initial request URL
$urlquerystr = "";
if ($argc > 1) {
  $myArgs = array_slice($argv, 1);
  $urlquerystr = "q=" . join('+', $myArgs) . '&';
}
if (US_CITY && US_STATE) {
  // See settings file for US_CITY and US_STATE
  $urlquerystr .= "l=" . US_CITY . "%2C+" . US_STATE;
}
$resultsInitUrl = JOBS_URLBASE . ($urlquerystr ? "/jobs?$urlquerystr" : "/jobs");

echo "Using initial URL: $resultsInitUrl\n\n";

// 2. Fetch first page of results
$resultsPageSrc = curlGet($resultsInitUrl);
//debugOut($resultsPageSrc . "\n\n");

// 3. convert it to a DOM object
$resultsPage1XPath = returnXPathObject($resultsPageSrc);

// 4. Extract the job posts data from 1st page to start the lists
$regularJobs = array();
scrapeJobs($resultsPage1XPath, $REGULAR_PATHMAP, $regularJobs);
$sponsoredJobs = array();
scrapeJobs($resultsPage1XPath, $SPONSORED_PATHMAP, $sponsoredJobs);

// 5. Find the links to the remaining pages
$resultsPageNodes = $resultsPage1XPath->query('//div[@class="pagination"]/a/@href');
if ($resultsPageNodes->length < 1) {
  echo "Failed to find results links on first results page.";
}
else {
  // 6. Process the link addresses into URLs we can pass to curlGet()
  $resultPageUrls = array();
  for ($i = 0; $i < $resultsPageNodes->length; $i++) {
    // Each URL starts with "/jobs", so we must prefix with site address
    $resultPageUrls[] = JOBS_URLBASE . $resultsPageNodes->item($i)->nodeValue;
  }

  // 7. Ensure we have only unique URLs
  $uniqueResultsPages = array_values(array_unique($resultPageUrls));

  // 8. Get, parse, and scrape the remaining pages of results
  foreach ($uniqueResultsPages as $nextResultsUrl) {
    if ($nextResultsUrl === $resultsInitUrl) continue;

    // Be a polite netizen, give the remote server a break
    sleep(rand(1, 3));

    echo "Requesting URL $nextResultsUrl\n";
    $resultsPageSrc = curlGet($nextResultsUrl);
    if (!$resultsPageSrc) {
      echo "Failed to fetch $nextResultsUrl\n";
      continue;
    }
    $resultsPageXPath = returnXPathObject($resultsPageSrc);
    scrapeJobs($resultsPageXPath, $REGULAR_PATHMAP, $regularJobs);
    scrapeJobs($resultsPageXPath, $SPONSORED_PATHMAP, $sponsoredJobs);
  }
}

function displayJobs($jobArray) {
  foreach ($jobArray as $jobUrl => $jobDetails) {
    echo "\n";
    foreach ($jobDetails as $key => $val) {
      if ($key == 'url') {
        $jobUrl = $val;
        continue;
      }
      echo strtoupper($key) . ": " . "$val\n";
    }
    echo "URL: " . JOBS_URLBASE . "$jobUrl\n";
  }
}

// OUTPUT
displayJobs($regularJobs);
if (count($sponsoredJobs) > 0) {
  echo "\n--------------------\nSponsored Listings:\n--------------------\n";
  displayJobs($sponsoredJobs);
}

?>

