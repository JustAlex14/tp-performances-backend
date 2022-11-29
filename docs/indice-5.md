### ℹ️ Indice n°5 : Utiliser des sous-requêtes dans les `INNER JOIN`

En SQL, dans ce contexte précis (par pitié ne le faites pas en production), vous pouvez `INNER JOIN` sur une
sous-requête. L'avantage, c'est que vous aurez des noms de colonnes plus propres sur lesquels vous baser et déjà `CAST`.
Exemple où ici je récupère le l'hôtel (ID et nom) ainsi que l'ID de la chambre la moins chère et son prix :

```sql
SELECT
   user.ID AS hotelId,
   user.display_name AS hotelName,
   postData.ID AS cheapestRoomId,
   postData.price AS price

FROM
    wp_users AS USER
    
    -- room
    INNER JOIN (
      SELECT
         post.ID,
         post.post_author,
         MIN(CAST(priceData.meta_value AS UNSIGNED)) AS price
      FROM
         tp.wp_posts AS post
            -- price
            INNER JOIN tp.wp_postmeta AS priceData ON post.ID = priceData.post_id
            AND priceData.meta_key = 'price'
      WHERE
         post.post_type = 'room'
      GROUP BY
         post.post_author
   ) AS postData ON user.ID = postData.post_author

WHERE
    -- On peut déjà filtrer vu que valeur est déjà castée en numérique
    price < 100

LIMIT 3;
```
Ce qui me retourne :

|  `hotelId`  | `hotelName`       |  `cheapestRoomId`  |  `Price` |
|:-----------:|-------------------|:------------------:|---------:|
|      1      | Roy Gie           |         1          |       57 | 
|      2      | Carpentier SCOP   |        124         |       88 |
|      3      | Schmitt et Dupont |        237         |       99 |
