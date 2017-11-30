# scrape-indeed
Command-line PHP script to collect job post data from Indeed.com based on your search terms

## Motivation
Indeed.com divides search results into multiple pages, then it repeats certain results - _on every page_. It also includes sponsored results that are located outside my search area _on every page_. That wastes my job search time. I want only essential results.

## A Demonstration of Web Scraping Across Multiple Pages
When run through PHP at the command line, this script will collect and display the basic details of each job item turned up by a search submitted to www.indeed.com using the search terms included on the command line. For each result item, a URL to the job description page is provided. The script filters out results that are outside the Albuquerque, New Mexico area (but that can be adjusted by simple edits to the script - see the comments inside).

## Requirements
- `php-cli`
- `cURL` support enabled for php-cli

## Example
```
$ php scrape-indeed.php javascript OR node.js OR mysql OR php


```

## Disclaimer
This project is intended to be vetted by interested parties merely as a demonstration of the author's PHP knowledge and style. It is not intended for regular use.  
If any party establishes regular use of this script, one should not be surprised when it stops working one day, after the maintainers of Indeed.com discover that their pages are being scraped, if it just so happens that they take exception to that, and they apply any tweak that breaks the expectations built into the script.

