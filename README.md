# URL Shortener v0.1
## What does it do?
Creates a shorter url from any valid url by generating a random key. You can define the key length (minum 6), 6-8 characters is recommended. 

## Requirements
To run this, you need a functioning webserver. URLShortener fully supports Apache with URL Rewriting enabled.

### Web Server
* Apache 2.2+
* fopen and and Module Rewrite enabled
* PHP 5.5+
* File system that supports minum 1'500'625 files
* The program expects to be run in the root web folder

### Web Browser
* Javascript enabled
* Supports XML Http Requests

## Installation
### Step 1
* Download or pull from my repository

### Step 2
* Create a file `.htaccess` with the following contents in the directory that will be served
```
#Options +FollowSymLinks
#RewriteEngine on
#RewriteCond %{REQUEST_FILENAME} !-f
#RewriteCond %{REQUEST_FILENAME} !-d
#RewriteRule .* /index.php [L]
```

### Step 3
* Make sure the directories have the right permissions. If you run PHP as an apache user, make sure the directories are writable.

### Step 4
* Test and verify that the application works by submitting a few urls, copy pasting the link and opening it in another browser.

## Notes

### Limitations
* If you pick a key of length 6, this tool will generate 1'225 subfolders and 1'500'625 files at maximum capacity. Make sure your file system can support this.