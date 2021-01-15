# Single PHP Proxy

<a href="https://imgur.com/c6ZC7fL"><img src="https://i.imgur.com/c6ZC7fL.png" title="" alt="" /></a>

**A messy PHP script with no security enhancement. USE AT YOUR OWN RISK!**

Single-file web proxy application with full features in PHP.

## Configuration:

Setup a PHP environment and put the SinglePHPProxy.php in the web folder (The filename of the SinglePHPProxy.php can be changed as you wish). That's it.

## Features:

- Full proxy - Normal resources such as .js, .css and image files are downloaded to the server and the links in the webpage are fixed;

- Hrefs to resources in the same site are fixed; a loop searching function would be executed to ensure that the server could find the resources;

- Hrefs are fixed in webpage to keep new page proxied;

- Automatically clear temp files downloaded by server;

- Cookies supported; cookies are stored on server for permanent use; users can clear the cookies by executing the 'Clear all cookies' function;

- HTTPS supported;

- UTF-8 encoding supported;

## TODO list:

- SSRF protection;

- Supporting POST method;

- Supporting local storage;

- AD blocking function;
