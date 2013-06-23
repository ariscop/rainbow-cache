Rainbow Cache
=============

Wordpress cache formerly used on Bronystate

supports static caching, Even with comments, and static expiery.

stored in wp-content/cache by default, the object database is under 
/store, the static tree is under /static

TODO
----
no https support, you're either all http or all https
planed to add error pages, never got around to it.

Bugs
----
if the databse connection fails for any reason, you get a blank page.

Recomended .htacces
-------------------
    RewriteEngine on

    #if it exists, serve it
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule . - [S=2]

    #Static cache
    RewriteCond %{QUERY_STRING} ^$
    RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_
    RewriteCond 
    %{DOCUMENT_ROOT}/wp-content/cache/static/%{HTTP_HOST}/%{REQUEST_URI}@/.htaccess -f
    RewriteRule .*   wp-content/cache/static/%{HTTP_HOST}/%{REQUEST_URI}@/index.html [S=1]

    #Everything else
    RewriteRule .* /index.php
