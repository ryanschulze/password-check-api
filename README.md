# Password check API
A simple and fast API that can be used to check if a password is in a database.
It assumes that the passwords are stored as SHA1 hashes in the database and is intended to be used together with the password lists from [Troy Hunt](https://haveibeenpwned.com/Passwords). That being said there is nothing stopping you from using your own lists or adding additional entries.

The reason behind this API is to make it easier to follow the latest [NIST 800-63b guidelines](https://pages.nist.gov/800-63-3/sp800-63b.html) in regards to warning users if their password is included in previous breach corpuses.

The API itself uses the [Slim Framework](https://www.slimframework.com/) for the heavy lifting.

Now let's get started, there are three things you will need to setup:
  * [Database](#database-setup)
  * [Webserver](#webserver-configuration)
  * [Application](#application-setup)


## Database setup
We will be using MySQL. First we create the database and an user to access it (change `secretpassword`)

    CREATE DATABASE pwdcheck;
    GRANT SELECT ON pwdcheck.* TO 'pwdcheck'@'localhost' IDENTIFIED BY 'secretpassword';
    USE pwdcheck;
    CREATE TABLE pwdlist
    (
      pwd CHAR(40),
      PRIMARY KEY (pwd)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;

Now we will import the password data (change the path/filenames of your password data if necessary).
The example is based of the passwords currently available from https://haveibeenpwned.com/Passwords

    CREATE TABLE pwdlist_new LIKE pwdlist;
    ALTER TABLE pwdlist_new DISABLE KEYS;

    LOAD DATA LOCAL INFILE '/var/tmp/pwned-passwords-2.0.txt'
    INTO TABLE pwdlist_new
    FIELDS
      TERMINATED BY ":"
    LINES
      TERMINATED BY "\n"
    ( @hash, @counter )
    SET
      pwd=@hash
    ;

    ALTER TABLE pwdlist_new ENABLE KEYS;
    RENAME TABLE pwdlist to pwdlist_old, pwdlist_new to pwdlist;
    DROP TABLE pwdlist_old;

This SQL create an empty copy of the pwdlist table, fill that copy with data and then switches the two tables (this is an atomic procedure), and then deletes the old password table.


## Webserver Configuration
The [Slim Framework](https://www.slimframework.com/docs/start/web-servers.html) has advice on configurations for various webservers. On top of that I'd suggest going with HTTPS instead of HTTP since we are dealing with sensitive data.

Here is an example of a nginx vhost configuration. Change `server_name`, `root` (to the public directory of your API), `ssl_certificate` and `ssl_certificate_key` (and `fastcgi_pass` depending on your setup).

    server {
        listen              443 ssl;
        server_name         fqdn.of.application;
        root                /var/www/application/public;

        ssl                 on;
        ssl_certificate     /etc/nginx/ssl/certicifate_chain.pem;
        ssl_certificate_key /etc/nginx/ssl/certificate.key;
        ssl_protocols       TLSv1 TLSv1.1 TLSv1.2;
        ssl_ciphers         HIGH:!aNULL:!MD5;

        index               index.php;

        location / {
            try_files $uri /index.php$is_args$args;
        }

        location ~ \.php {
            fastcgi_split_path_info ^(.+\.php)(/.+)$;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            fastcgi_param SCRIPT_NAME $fastcgi_script_name;
            fastcgi_index index.php;
            fastcgi_pass unix:/var/run/php/php7.0-fpm.sock;
        }
    }


## Application setup
* Drop the git files from src/ onto your webserver
* Use [composer](https://getcomposer.org/) to install dependencies by going to the directory with composer.json and running `composer install`
* Copy `db_config.php.dist` to `db_config.php` and fill in the values (i.e. the mysql password you set earlier)

All done, now on to how to use the API.

## API Usage
The result is passed via the HTTP status code, the body of the response is empty.
### Endpoints

__GET /sha1/{$sha1sum}__

_Description_

Checks if the supplied password is in a list of compromised passwords. The unsalted SHA1 hash of the password is supplied so that the users clear text password is never unnecessarily exposed.
This is the suggested way to use this service.

_Responses_
> 204 - Password was not found in the database.
>
> 406 - Password was found in the database and should not be used.
>
> 400 - Supplied hash was not recognized as a SHA1 hash (should be alphanumeric, exactly 40 characters long).

_Example_
`GET https://check.foo.bar/sha1/FFFFFFFEE791CBAC0F6305CAF0CEE06BBE131160`


__GET /cleartext/{$password}__

_Description_

Checks if the supplied password is in a list of compromised passwords.
! Whenever possible, the /sha1/ endpoint should be used instead of the /cleartext/ endpoint, to avoid the password being exposed to additional systems.
Using the /sha1/ endpoint also eliminates potential problems with special characters in the password being checked.

_Responses_
> STATUS 204 - Password was not found in the database.
>
> STATUS 406 - Password was found in the database and should not be used.

_Example_
`GET https://check.foo.bar/cleartext/test`

### Code examples

#### bash
```bash
curl -s -k -w '%{http_code}\n' https://check.foo.bar/sha1/FFFFFFFEE791CBAC0F6305CAF0CEE06BBE131160
```

#### PHP
```PHP
$url = 'https://check.foo.bar/sha1/FFFFFFFEE791CBAC0F6305CAF0CEE06BBE131160';
$curl = curl_init($url);
curl_setopt($curl, CURLOPT_HEADER, true);
curl_setopt($curl, CURLOPT_NOBODY, true);
curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
curl_setopt($curl, CURLOPT_TIMEOUT,10);
$output = curl_exec($curl);
$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

echo $status;
```

