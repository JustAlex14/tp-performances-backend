### ℹ️ Indice n°8 : Comment insérer du contenu dans une table à partir du retour d'une requête ?
Commencez par créer d'abord vos tables. Ici pour l'exemple j'ai une table avec deux colonnes : `id` et `name` :
```sql
CREATE TABLE `myTableName` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
```

Si les colonnes retournées par votre requête **ont exactement le même nom que les colonnes de votre nouvelle table**, vous pouvez directement faire `INSERT INTO myTableName ( SELECT ... )`. Vous pouvez tout à fait utiliser des alias (`AS`) sur vos champs de `SELECT` pour les faire correspondre aux champs de votre table : 
```sql
INSERT INTO myTableName (SELECT
    ID AS id,
    display_name AS name
FROM
    wp_users
WHERE 
    ID < 10);
```