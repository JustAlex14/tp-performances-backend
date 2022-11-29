### ℹ️ Indice n°9 : Comment inspecter les requêtes qui sont effectuées sur la DB ?

1. Connectez-vous à PHPMyAdmin et exécutez les requêtes suivantes
```sql
SET GLOBAL general_log=1;
SET GLOBAL general_log_file='/var/log/mysql/mysql.log';
SET GLOBAL log_output = 'file';
```
2. Ouvrez le terminal du container `db` et exécutez-y la commande
```shell
tail -f /var/log/mysql/mysql.log
```
3. Pour quitter, faites `CTRL+C`.

### ⚠️ ATTENTION ️⚠️
Le fichier de log peut rapidement devenir volumineux.
- Supprimez-le régulièrement avec la commande 
```shell
rm /var/log/mysql/mysql.log
```
- Désactivez la génération de log dès que vous avez fini d'inspecter vos requêtes depuis PHPMyAdmin
```sql
SET GLOBAL general_log=0;
```