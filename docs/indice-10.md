### ℹ️ Indice n°10 : Comment activer une extension PHP ?

1. Ouvrez un des fichiers de configurations chargés par PHP (fichiers `.ini`) qui sont listés dans les entrées `phpinfo()` :
   - *Loaded Configuration File*
   - *Scan this dir for additional .ini files*
   - *Additional .ini files parsed*
   - ou par la commande 
    ```shell
    php --ini
    ```
2. Ajoutez la ligne 
```ini
extension=__NOM_FICHIER_SO_LIBRAIRIE__
```
3. Redémarrez PHP (dans le cas de Docker on redémarre le container)