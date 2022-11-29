### ℹ️ Indice n°7 : Comment générer la requête SQL de création d'une table ?
Vous pouvez tout à fait créer vos tables depuis une UI (TablePlus, PHPMyAdmin, ...) et ensuite récupérer le SQL. Il vous suffit d'exécuter la requête SQL :
```sql
SHOW CREATE TABLE `tp`.`wp_postmeta`;
```