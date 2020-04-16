# README

### What is this repository for?

* Check a list of OCLC numbers for items that might be the last copy in your state.

When withdrawing items from your library, you might want to know if they are the last copy in your state. This tool can check WorldCat to see if there are other copies of a work in your state and give you a list of those for which you might own the only copy in your state. 

### How do I install it?
* Put it on an Apache server with PHP running. 
* Make sure that Apache can write to your `./config/` directory
* Navigate to the served directory in a browser.
* Choose your state, enter your library name, and add your WorldCat API key. A config file will be written in `config/` directory. There is an `.htaccess` file that prevents Apache from serving that directory.

### How do I use it?
Load the site in a browser and either enter OCLC numbers directly into the browser as instructed, or upload a text file with one OCLC number per line.

The tool will check WorldCat's API to see if there are any other copies of the item in your state and provide you with a list of the items that appear to be the only copy in your state for any necessary followup actions.
