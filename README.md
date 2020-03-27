# Tablaturi-bg-API

Custom PHP API for [Tablaturi-BG](http://tablaturi-bg.com) - the biggest website for guitar tabs in Bulgaria.
The API uses a MySQL database. The API was created to be used with the latest [Tablaturi-BG VueJS front end](https://github.com/gryp17/Tablaturi-bg-VueJS).

## Installation

1. Import the database schema

  There are two database schemas that you can choose from:

  InnoDB
  
  > [/db/innodb_schema.sql](https://github.com/gryp17/Tablaturi-bg-API/blob/master/db/innodb_schema.sql)
  
  
  myISAM
  
  > [/db/myISAM_schema.sql](https://github.com/gryp17/Tablaturi-bg-API/blob/master/db/myISAM_schema.sql)


## Configuration

1. API

  The configuration file is located in

  > [/api/config/Config.php](https://github.com/gryp17/Tablaturi-bg-API/blob/master/api/config/Config.php)


  It contains the default database credentials , backing tracks authentication and the default directories paths.

2. .htaccess

  Change the RewriteBase rule based on your domain path.
  
  The .htaccess file is located in the root directory of the project
  
  > [/.htaccess](https://github.com/gryp17/Tablaturi-bg-API/blob/master/.htaccess)
  
  Examples:

  ```apache
  #http://tablaturi-bg.com
  RewriteBase /
  ```
  
  ```apache
  #localhost/Tablaturi-bg-API
  RewriteBase /Tablaturi-bg-API
  ```

