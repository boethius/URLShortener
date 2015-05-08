# URL Shortener Readme
## Requirements
To run this, you need a functioning webserver. This version only fully supports Apache with URL Rewriting enabled.

### Web Server
* Apache 2.2+
... With Module Rewrite enabled
* PHP 5.5+
### Web Browser
* Javascript enabled
* Supports XML Http Requests

## Installation
### Step 1
* Download or pull from my repository

### Step 2
* Create a file `.htaccess` with the following contents in the directory that will be served
```#Options +FollowSymLinks
#RewriteEngine on
#RewriteCond %{REQUEST_FILENAME} !-f
#RewriteCond %{REQUEST_FILENAME} !-d
#RewriteRule .* /index.php [L]```

### Step 3
* Make sure the directories have the right permissions. If you run PHP as an apache user, make sure the directories are writable.
