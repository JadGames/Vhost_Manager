<VirtualHost *:80>
    ServerName {{DOMAIN}}
    ServerAdmin webmaster@localhost
    DocumentRoot {{DOCROOT}}

    <Directory {{DOCROOT}}>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog {{ERROR_LOG}}
    CustomLog {{ACCESS_LOG}} combined
</VirtualHost>
